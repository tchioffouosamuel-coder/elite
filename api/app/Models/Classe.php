<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Classe extends Model
{
    protected $fillable = [
        'school_id', 'niveau_id', 'annee_scolaire_id', 'professeur_principal_id',
        'surveillant_general_id', 'censeur_id', 'conseiller_orientation_id',
        'nom', 'filiere', 'code_examen', 'capacite',
    ];

    public function scopeForSchool(Builder $query, int $schoolId): Builder
    {
        return $query->where('school_id', $schoolId);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function niveau(): BelongsTo
    {
        return $this->belongsTo(Niveau::class);
    }

    public function anneeScolaire(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class);
    }

    public function professeurPrincipal(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'professeur_principal_id');
    }

    public function surveillantGeneral(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'surveillant_general_id');
    }

    public function censeur(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'censeur_id');
    }

    public function conseillerOrientation(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'conseiller_orientation_id');
    }

    public function emploiDuTemps(): HasMany
    {
        return $this->hasMany(EmploiDuTemps::class);
    }

    public function seances(): HasMany
    {
        return $this->hasMany(Seance::class);
    }

    public function eleves(): HasMany
    {
        return $this->hasMany(Eleve::class);
    }

    public function classeMatieres(): HasMany
    {
        return $this->hasMany(ClasseMatiere::class);
    }
}
