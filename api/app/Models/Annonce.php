<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Annonce extends Model
{
    protected $fillable = ['school_id', 'titre', 'contenu', 'publie_par', 'publiee_le'];

    protected function casts(): array
    {
        return ['publiee_le' => 'datetime'];
    }

    public function scopeForSchool(Builder $query, int $schoolId): Builder
    {
        return $query->where('school_id', $schoolId);
    }

    public function publiePar(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'publie_par');
    }
}
