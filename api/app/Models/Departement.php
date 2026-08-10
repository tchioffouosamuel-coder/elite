<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Departement extends Model
{
    protected $fillable = ['school_id', 'nom', 'head_personnel_id'];

    public function scopeForSchool(Builder $query, int $schoolId): Builder
    {
        return $query->where('school_id', $schoolId);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function personnels(): HasMany
    {
        return $this->hasMany(Personnel::class);
    }

    public function matieres(): HasMany
    {
        return $this->hasMany(Matiere::class);
    }

    public function headPersonnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'head_personnel_id');
    }
}
