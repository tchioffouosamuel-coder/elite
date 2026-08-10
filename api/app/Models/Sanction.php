<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sanction extends Model
{
    protected $fillable = [
        'eleve_id', 'classe_id', 'trimestre_id', 'type', 'duree_jours', 'motif', 'date_sanction', 'enregistre_par',
    ];

    protected function casts(): array
    {
        return ['date_sanction' => 'date'];
    }

    public function scopeForSchool(Builder $query, int $schoolId): Builder
    {
        return $query->whereHas('eleve', fn ($q) => $q->where('school_id', $schoolId));
    }

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    public function trimestre(): BelongsTo
    {
        return $this->belongsTo(Trimestre::class);
    }

    public function enregistrePar(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'enregistre_par');
    }
}
