<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Situation financière d'un élève pour une année scolaire.
 *
 * C'est ici que se calcule ce que doit la famille. Les totaux ne sont pas
 * stockés : un montant figé en base finit toujours par diverger des
 * versements qu'il est censé résumer — il suffit d'une annulation de reçu
 * pour que le solde mente. Ils sont donc dérivés, et les écrans qui listent
 * des centaines d'élèves préchargent les relations plutôt que de recalculer
 * ligne à ligne (cf. `avecTotaux`).
 */
class DossierScolarite extends Model
{
    protected $table = 'dossiers_scolarite';

    protected $fillable = [
        'school_id',
        'annee_scolaire_id',
        'eleve_id',
        'montant_scolarite',
        'remise',
        'report_dette',
        'observation',
    ];

    protected function casts(): array
    {
        return [
            'montant_scolarite' => 'integer',
            'remise' => 'integer',
            'report_dette' => 'integer',
        ];
    }

    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return is_array($schoolId) ? $query->whereIn('school_id', $schoolId) : $query->where('school_id', $schoolId);
    }

    /** Relations nécessaires au calcul des totaux, en une seule requête. */
    public function scopeAvecTotaux(Builder $query): Builder
    {
        return $query->with([
            'fraisAnnexes',
            'versements' => fn ($q) => $q->valides()->with('lignes'),
            'busAffectations.trajet',
        ]);
    }

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function anneeScolaire(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class);
    }

    public function fraisAnnexes(): HasMany
    {
        return $this->hasMany(DossierFraisAnnexe::class);
    }

    public function versements(): HasMany
    {
        return $this->hasMany(Versement::class);
    }

    /**
     * Toutes les souscriptions bus de l'élève, sans filtre d'année : le
     * rapprochement avec l'année du dossier se fait en mémoire dans
     * `bus_actif` plutôt que dans la relation elle-même, pour rester
     * eager-loadable sans piéger `with()` sur l'instance qui sert de gabarit
     * (`$this->annee_scolaire_id` y vaudrait toujours `null`).
     */
    public function busAffectations(): HasMany
    {
        return $this->hasMany(BusAffectation::class, 'eleve_id', 'eleve_id');
    }

    /** Souscription bus active de l'élève pour l'année de ce dossier, s'il y en a une. */
    public function getBusActifAttribute(): ?BusAffectation
    {
        return $this->busAffectations
            ->where('annee_scolaire_id', $this->annee_scolaire_id)
            ->where('statut', 'actif')
            ->first();
    }

    /** Scolarité nette de remise, hors frais annexes et hors reliquat antérieur. */
    public function getScolariteNetteAttribute(): int
    {
        return max(0, $this->montant_scolarite - $this->remise);
    }

    public function getTotalFraisAnnexesAttribute(): int
    {
        return (int) $this->fraisAnnexes->sum('montant');
    }

    /** Souscription au bus, si active — vient du trajet et se fige à l'entrée. */
    public function getMontantBusAttribute(): int
    {
        return (int) ($this->bus_actif?->tarif_mensuel ?? 0);
    }

    /** Tout ce que la famille doit sur l'année, reliquat de l'an dernier et bus compris. */
    public function getTotalDuAttribute(): int
    {
        return $this->scolarite_nette + $this->total_frais_annexes + $this->report_dette + $this->montant_bus;
    }

    public function getTotalPayeAttribute(): int
    {
        return (int) $this->versements->whereNull('annule_le')->sum('montant');
    }

    /** Négatif en cas d'avance : la famille a versé plus que dû. */
    public function getSoldeAttribute(): int
    {
        return $this->total_du - $this->total_paye;
    }

    public function getResteAPayerAttribute(): int
    {
        return max(0, $this->solde);
    }

    /** Trop-perçu, à reporter ou à rembourser. */
    public function getAvanceAttribute(): int
    {
        return max(0, -$this->solde);
    }

    /**
     * `impaye` distingué de `partiel` à dessein : la relance n'est pas la même
     * pour une famille qui n'a rien versé et pour une qui s'acquitte par
     * tranches.
     */
    public function getStatutPaiementAttribute(): string
    {
        return match (true) {
            $this->total_du === 0 => 'sans_frais',
            $this->solde < 0 => 'avance',
            $this->solde === 0 => 'solde',
            $this->total_paye === 0 => 'impaye',
            default => 'partiel',
        };
    }

    public function getTauxRecouvrementAttribute(): float
    {
        return $this->total_du > 0 ? round($this->total_paye * 100 / $this->total_du, 2) : 0.0;
    }

    /**
     * Décompose ce qui est dû en postes distincts — reliquat, scolarité,
     * chaque frais annexe, bus — avec ce qui est déjà réglé sur chacun.
     * Sert à la fois à ventiler automatiquement un encaissement
     * (`ScolariteService::ventilationAutomatique`) et à l'afficher au
     * comptoir avant de l'enregistrer, pour que la famille sache sur quoi
     * porte son versement plutôt qu'un seul total indifférencié.
     *
     * @return list<array{cle: string, dossier_frais_annexe_id: ?int, libelle: string, montant_du: int, montant_paye: int, reste: int}>
     */
    public function getRubriquesAttribute(): array
    {
        $this->loadMissing(['fraisAnnexes', 'versements.lignes']);

        $postes = [];

        if ($this->report_dette > 0) {
            $postes[] = ['report_dette', null, 'Reliquat année précédente', $this->report_dette];
        }

        $postes[] = ['scolarite', null, 'Frais de scolarité', $this->scolarite_nette];

        foreach ($this->fraisAnnexes as $frais) {
            $postes[] = ['frais_annexe', $frais->id, $frais->libelle, $frais->montant];
        }

        if ($this->montant_bus > 0) {
            $nomTrajet = $this->bus_actif?->trajet?->nom;
            $postes[] = ['bus', null, 'Transport scolaire'.($nomTrajet ? " — {$nomTrajet}" : ''), $this->montant_bus];
        }

        $regle = [];
        foreach ($this->versements->whereNull('annule_le') as $versement) {
            foreach ($versement->lignes as $ligne) {
                $cle = $ligne->affectation.':'.($ligne->dossier_frais_annexe_id ?? '');
                $regle[$cle] = ($regle[$cle] ?? 0) + $ligne->montant;
            }
        }

        return array_map(function (array $poste) use ($regle) {
            [$affectation, $fraisId, $libelle, $du] = $poste;
            $cle = $affectation.':'.($fraisId ?? '');
            $paye = min($du, $regle[$cle] ?? 0);

            return [
                'cle' => $affectation,
                'dossier_frais_annexe_id' => $fraisId,
                'libelle' => $libelle,
                'montant_du' => $du,
                'montant_paye' => $paye,
                'reste' => max(0, $du - $paye),
            ];
        }, $postes);
    }
}
