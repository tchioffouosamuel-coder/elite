<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Infrastructure extends Model
{
    protected $fillable = [
        'school_id',
        'type',
        'libelle',
        'materiau',
        'etat',
        'quantite',
        'besoin_quantite',
        'observations',
    ];

    protected function casts(): array
    {
        return [
            'quantite' => 'integer',
            'besoin_quantite' => 'integer',
        ];
    }

    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return is_array($schoolId) ? $query->whereIn('school_id', $schoolId) : $query->where('school_id', $schoolId);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
