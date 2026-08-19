<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisiteInfirmerie extends Model
{
    protected $table = 'visites_infirmerie';

    protected $fillable = [
        'eleve_id',
        'classe_id',
        'date_visite',
        'raison',
        'soins_prodiges',
        'cout_soins',
        'observations',
        'enregistre_par',
    ];

    protected function casts(): array
    {
        return [
            'date_visite' => 'datetime',
            'cout_soins' => 'integer',
        ];
    }

    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return $query->whereHas('eleve', fn ($q) => is_array($schoolId) ? $q->whereIn('school_id', $schoolId) : $q->where('school_id', $schoolId));
    }

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    public function enregistrePar(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'enregistre_par');
    }
}
