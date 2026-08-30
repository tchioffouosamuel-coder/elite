<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipementMobilier extends Model
{
    protected $table = 'equipements_mobiliers';

    protected $fillable = [
        'school_id',
        'nature',
        'quantite',
        'besoin_quantite',
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
