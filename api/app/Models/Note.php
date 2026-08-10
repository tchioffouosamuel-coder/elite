<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Note extends Model
{
    /** Volets d'évaluation du primaire ; le secondaire n'utilise que 'unique'. */
    public const COMPOSANTES_PRIMAIRE = ['oral', 'ecrit', 'savoir_etre', 'pratique'];

    protected $fillable = ['eleve_id', 'classe_matiere_id', 'sequence_id', 'composante', 'valeur', 'saisi_par'];

    protected function casts(): array
    {
        return ['valeur' => 'decimal:2'];
    }

    public function scopeForSchool(Builder $query, int $schoolId): Builder
    {
        return $query->whereHas('eleve', fn ($q) => $q->where('school_id', $schoolId));
    }

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    public function classeMatiere(): BelongsTo
    {
        return $this->belongsTo(ClasseMatiere::class);
    }

    public function sequence(): BelongsTo
    {
        return $this->belongsTo(Sequence::class);
    }

    public function saisiPar(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'saisi_par');
    }
}
