<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DocumentReference extends Model
{
    protected $fillable = [
        'school_id', 'type', 'annee_scolaire_id', 'numero', 'genere_par',
    ];

    public function scopeForSchool(Builder $query, int $schoolId): Builder
    {
        return $query->where('school_id', $schoolId);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function anneeScolaire(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class);
    }

    public function generateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'genere_par');
    }

    public function referencable(): MorphTo
    {
        return $this->morphTo();
    }

    /** Numéro d'ordre affiché sur le document, complété à 3 chiffres (001, 002…). */
    public function numeroFormate(): string
    {
        return str_pad((string) $this->numero, 3, '0', STR_PAD_LEFT);
    }
}
