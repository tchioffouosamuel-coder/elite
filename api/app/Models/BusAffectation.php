<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class BusAffectation extends Model
{
    public const OPTIONS_TRAJET = ['aller_simple', 'retour_simple', 'aller_retour'];

    protected $fillable = [
        'eleve_id', 'trajet_id', 'arret_id', 'annee_scolaire_id', 'tarif_mensuel', 'option_trajet', 'statut',
    ];

    protected function casts(): array
    {
        return ['tarif_mensuel' => 'integer'];
    }

    /** Une affectation suspendue ne compte plus dans l'effectif transporté du trajet. */
    public function scopeActives(Builder $query): Builder
    {
        return $query->where('statut', 'actif');
    }

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    public function trajet(): BelongsTo
    {
        return $this->belongsTo(BusTrajet::class, 'trajet_id');
    }

    public function arret(): BelongsTo
    {
        return $this->belongsTo(BusArret::class, 'arret_id');
    }

    public function anneeScolaire(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class);
    }

    public function versements(): HasMany
    {
        return $this->hasMany(BusVersement::class);
    }

    /**
     * Mois dus par cette souscription : du mois de souscription (elle ne doit
     * rien pour les mois écoulés avant qu'elle n'existe) au mois de fin
     * d'année scolaire — chacun représenté par son premier jour. Une
     * souscription sans année rattachée (jamais censée arriver en usage réel)
     * ne doit rien plutôt que planter l'écran de paiement.
     */
    public function getMoisCouvertureAttribute(): Collection
    {
        $anneeDebut = $this->anneeScolaire?->date_debut;
        $anneeFin = $this->anneeScolaire?->date_fin;

        if (! $anneeDebut || ! $anneeFin) {
            return collect();
        }

        $debut = Carbon::parse($this->created_at)->startOfMonth();
        $anneeDebutMois = Carbon::parse($anneeDebut)->startOfMonth();
        if ($debut->lessThan($anneeDebutMois)) {
            $debut = $anneeDebutMois;
        }

        $fin = Carbon::parse($anneeFin)->startOfMonth();
        $mois = collect();
        $curseur = $debut->copy();

        while ($curseur->lessThanOrEqualTo($fin)) {
            $mois->push($curseur->copy());
            $curseur = $curseur->addMonthNoOverflow();
        }

        return $mois;
    }

    /**
     * Détail mois par mois : ce qui est dû, ce qui est réglé, et le statut qui
     * en découle — c'est ce qu'affiche l'écran de paiement, mois par mois
     * plutôt qu'un seul total indifférencié comme pour la scolarité annuelle.
     *
     * @return list<array{mois: string, du: int, paye: int, reste: int, statut: string}>
     */
    public function getSituationMensuelleAttribute(): array
    {
        $this->loadMissing('versements');

        $payeParMois = $this->versements
            ->whereNull('annule_le')
            ->groupBy(fn (BusVersement $v) => $v->mois->format('Y-m'))
            ->map(fn ($groupe) => (int) $groupe->sum('montant'));

        $tarif = (int) ($this->tarif_mensuel ?? 0);
        $remiseParMois = $this->versements
            ->whereNull('annule_le')
            ->groupBy(fn (BusVersement $v) => $v->mois->format('Y-m'))
            ->map(fn ($groupe) => (int) $groupe->sum('remise'));

        return $this->mois_couverture->map(function (Carbon $mois) use ($payeParMois, $remiseParMois, $tarif) {
            $du = max(0, $tarif - $remiseParMois->get($mois->format('Y-m'), 0));
            $paye = min($du, $payeParMois->get($mois->format('Y-m'), 0));

            return [
                'mois' => $mois->format('Y-m-d'),
                'du' => $du,
                'paye' => $paye,
                'reste' => max(0, $du - $paye),
                'statut' => match (true) {
                    $du === 0 => 'sans_frais',
                    $paye <= 0 => 'impaye',
                    $paye >= $du => 'solde',
                    default => 'partiel',
                },
            ];
        })->values()->all();
    }

    public function getTotalDuAttribute(): int
    {
        return (int) collect($this->situation_mensuelle)->sum('du');
    }

    public function getTotalPayeAttribute(): int
    {
        $this->loadMissing('versements');

        return (int) $this->versements->whereNull('annule_le')->sum('montant');
    }

    public function getResteAPayerAttribute(): int
    {
        return max(0, $this->total_du - $this->total_paye);
    }

    public function getStatutPaiementAttribute(): string
    {
        if (! $this->tarif_mensuel) {
            return 'sans_frais';
        }

        return match (true) {
            $this->total_paye <= 0 => 'impaye',
            $this->reste_a_payer === 0 => 'solde',
            default => 'partiel',
        };
    }
}
