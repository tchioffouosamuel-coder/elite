<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Élément du programme annuel : module, chapitre ou leçon. Les trois vivent
 * dans la même table car une matière peut n'en utiliser qu'une partie —
 * modules puis chapitres puis leçons, chapitres puis leçons, ou leçons seules.
 */
class ProgressionItem extends Model
{
    public const TYPES = ['module', 'chapitre', 'lecon'];

    protected $fillable = [
        'classe_matiere_id', 'parent_id', 'type', 'titre', 'description', 'ordre',
        'sequence_id', 'duree_prevue',
    ];

    /** Scopé par école via la classe qui porte l'affectation. */
    public function scopeForSchool(Builder $query, int $schoolId): Builder
    {
        return $query->whereHas('classeMatiere.classe', fn ($q) => $q->where('school_id', $schoolId));
    }

    public function scopeLecons(Builder $query): Builder
    {
        return $query->where('type', 'lecon');
    }

    public function classeMatiere(): BelongsTo
    {
        return $this->belongsTo(ClasseMatiere::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function enfants(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('ordre');
    }

    public function sequence(): BelongsTo
    {
        return $this->belongsTo(Sequence::class);
    }

    public function seances(): BelongsToMany
    {
        return $this->belongsToMany(Seance::class, 'lecon_seance')->withTimestamps();
    }

    /** Une leçon est traitée dès qu'une séance l'a couverte. */
    public function estTraitee(): bool
    {
        return $this->seances()->exists();
    }
}
