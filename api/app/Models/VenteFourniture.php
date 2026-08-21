<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Vente au point de vente des fournitures. Ne se supprime pas : la facture
 * remise porte un numéro de série, seule une annulation tracée la neutralise
 * — même principe que {@see Versement} pour le reçu de scolarité.
 */
class VenteFourniture extends Model
{
    // Laravel devinerait « vente_fournitures » : le pluriel porte sur le premier mot.
    protected $table = 'ventes_fournitures';

    protected $fillable = [
        'school_id', 'annee_scolaire_id', 'numero_facture', 'date_vente', 'montant',
        'mode', 'eleve_id', 'client', 'vendu_par', 'note',
        'annule_le', 'annule_par', 'motif_annulation',
    ];

    protected function casts(): array
    {
        return [
            'date_vente' => 'date',
            'annule_le' => 'datetime',
            'montant' => 'integer',
        ];
    }

    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return is_array($schoolId) ? $query->whereIn('school_id', $schoolId) : $query->where('school_id', $schoolId);
    }

    /** Seules les ventes non annulées comptent dans une recette ou une marge. */
    public function scopeValides(Builder $query): Builder
    {
        return $query->whereNull('annule_le');
    }

    public function estAnnulee(): bool
    {
        return $this->annule_le !== null;
    }

    /** Coût des articles sortis : le pendant du montant facturé, pour la marge. */
    public function getCoutAttribute(): int
    {
        return (int) $this->lignes->sum(fn (VenteFournitureLigne $ligne) => $ligne->quantite * (int) $ligne->cout_unitaire);
    }

    public function getMargeAttribute(): int
    {
        return $this->montant - $this->cout;
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(VenteFournitureLigne::class);
    }

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    public function vendeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendu_par');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /** Écritures du journal issues de cette vente, contrepassées à l'annulation. */
    public function ecritures(): MorphMany
    {
        return $this->morphMany(EcritureComptable::class, 'origine');
    }
}
