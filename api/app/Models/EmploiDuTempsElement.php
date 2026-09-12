<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class EmploiDuTempsElement extends Model
{
    protected $table = 'emploi_du_temps_elements';

    protected $fillable = ['school_id', 'type', 'nom', 'heure_debut', 'heure_fin', 'jours', 'actif'];

    protected function casts(): array
    {
        return ['jours' => 'array', 'actif' => 'boolean'];
    }

    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return is_array($schoolId) ? $query->whereIn('school_id', $schoolId) : $query->where('school_id', $schoolId);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(Classe::class, 'emploi_du_temps_element_classe');
    }
}
