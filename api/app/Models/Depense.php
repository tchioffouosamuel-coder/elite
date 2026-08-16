<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Depense extends Model
{
    protected $fillable = [
        'school_id', 'annee_scolaire_id', 'compte_comptable_id', 'date_depense', 'libelle', 'montant',
        'mode', 'beneficiaire', 'reference_facture', 'responsable', 'saisi_par', 'justificatif_path',
        'statut', 'annule_le', 'annule_par', 'motif_annulation',
    ];

    protected function casts(): array
    {
        return ['date_depense' => 'date', 'annule_le' => 'datetime', 'montant' => 'integer'];
    }

    public function scopeForSchool(Builder $query, int $schoolId): Builder
    {
        return $query->where('school_id', $schoolId);
    }

    /** Une depense annulee ne pese ni sur le bilan ni sur la tresorerie. */
    public function scopeValides(Builder $query): Builder
    {
        return $query->where('statut', '!=', 'annulee');
    }

    public function compte(): BelongsTo
    {
        return $this->belongsTo(CompteComptable::class, 'compte_comptable_id');
    }

    public function anneeScolaire(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class);
    }

    public function saisisseur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'saisi_par');
    }
}
