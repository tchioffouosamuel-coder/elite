<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Annonce extends Model
{
    protected $fillable = ['school_id', 'titre', 'contenu', 'publie_par', 'publiee_le', 'cible_type', 'cible_data'];

    protected function casts(): array
    {
        return ['publiee_le' => 'datetime', 'cible_data' => 'array'];
    }

    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return is_array($schoolId) ? $query->whereIn('school_id', $schoolId) : $query->where('school_id', $schoolId);
    }

    public function publiePar(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'publie_par');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
