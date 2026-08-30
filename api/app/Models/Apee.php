<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Apee extends Model
{
    protected $table = 'apee';

    protected $fillable = [
        'school_id',
        'annee_scolaire_id',
        'legalisee',
        'date_legalisation',
        'numero_recepisse',
        'banque',
        'numero_compte',
        'president_nom',
        'president_fonction',
        'president_telephone',
        'date_ag_elective',
        'fin_mandat',
        'taux_par_eleve',
        'montant_percu',
        'montant_depense',
        'realisations',
    ];

    protected function casts(): array
    {
        return [
            'legalisee' => 'boolean',
            'date_legalisation' => 'date',
            'date_ag_elective' => 'date',
            'taux_par_eleve' => 'integer',
            'montant_percu' => 'integer',
            'montant_depense' => 'integer',
        ];
    }

    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return is_array($schoolId) ? $query->whereIn('school_id', $schoolId) : $query->where('school_id', $schoolId);
    }

    /** Solde restant dans le compte de l'APEE — jamais stocké, toujours déduit. */
    public function getMontantRestantAttribute(): int
    {
        return $this->montant_percu - $this->montant_depense;
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function anneeScolaire(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class);
    }
}
