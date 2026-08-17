<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Evaluation extends Model
{
    public const TYPES = ['interrogation', 'devoir', 'examen'];

    protected $fillable = [
        'school_id', 'classe_matiere_id', 'progression_item_id', 'titre', 'type',
        'date_prevue', 'bareme', 'competences', 'cree_par',
    ];

    protected function casts(): array
    {
        return ['date_prevue' => 'date'];
    }

    public function scopeForSchool(Builder $query, int $schoolId): Builder
    {
        return $query->where('school_id', $schoolId);
    }

    public function classeMatiere(): BelongsTo
    {
        return $this->belongsTo(ClasseMatiere::class);
    }

    public function progressionItem(): BelongsTo
    {
        return $this->belongsTo(ProgressionItem::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(EvaluationQuestion::class)->orderBy('ordre');
    }

    public function creePar(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'cree_par');
    }
}
