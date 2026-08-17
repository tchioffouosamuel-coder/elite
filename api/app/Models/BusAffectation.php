<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    /**
     * Part de la souscription déjà réglée, tous versements non annulés
     * confondus — dérivée du registre de versements, jamais stockée : c'est le
     * même principe que pour le reste de la scolarité (cf. `DossierScolarite`).
     */
    public function getMontantRegleAttribute(): int
    {
        return (int) VersementLigne::where('affectation', 'bus')
            ->whereHas(
                'versement',
                fn ($q) => $q->valides()->whereHas(
                    'dossier',
                    fn ($q2) => $q2->where('eleve_id', $this->eleve_id)->where('annee_scolaire_id', $this->annee_scolaire_id)
                )
            )
            ->sum('montant');
    }

    public function getStatutPaiementAttribute(): string
    {
        if (! $this->tarif_mensuel) {
            return 'sans_frais';
        }

        $regle = $this->montant_regle;

        return match (true) {
            $regle <= 0 => 'impaye',
            $regle >= $this->tarif_mensuel => 'solde',
            default => 'partiel',
        };
    }
}
