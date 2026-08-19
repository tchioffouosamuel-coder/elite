<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChampPersonnalise extends Model
{
    public const TYPES = ['texte', 'nombre', 'case'];

    // Eloquent ne pluralise que le dernier segment du nom de classe
    // (« champ_personnalises ») ; la migration porte le nom plus naturel
    // « champs_personnalises ».
    protected $table = 'champs_personnalises';

    protected $fillable = ['classe_matiere_id', 'libelle', 'type', 'ordre'];

    /** Scopé par école via la classe qui porte l'affectation, comme ProgressionItem. */
    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return $query->whereHas('classeMatiere.classe', fn ($q) => is_array($schoolId) ? $q->whereIn('school_id', $schoolId) : $q->where('school_id', $schoolId));
    }

    public function classeMatiere(): BelongsTo
    {
        return $this->belongsTo(ClasseMatiere::class);
    }
}
