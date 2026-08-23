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
 *
 * Une leçon porte directement sa fiche de préparation, au format du gabarit
 * de l'établissement (cf. `CHAMPS_FICHE`) : il en existe deux, un pour
 * maternelle/primaire et un pour le secondaire, qui partagent l'essentiel de
 * leurs colonnes — seules `competence` (primaire) et
 * `teaching_learning_strategies` (secondaire) sont propres à un cycle,
 * l'écran n'affichant que celles qui concernent le sien.
 */
class ProgressionItem extends Model
{
    public const TYPES = ['module', 'chapitre', 'lecon'];

    protected $fillable = [
        'classe_matiere_id', 'parent_id', 'type', 'titre', 'description', 'ordre',
        'sequence_id', 'duree_prevue',
        'expected_learning_outcomes', 'topic', 'sous_topic', 'competence',
        'entry_behaviour', 'teaching_aids', 'teaching_learning_strategies',
        'learners_activities', 'facilitators_activities',
        'assessment', 'assignment', 'remarks',
        'semaine', 'date_prevue', 'date_realisee', 'duree', 'colonnes_libres',
    ];

    /**
     * Champs texte de la fiche, communs aux deux gabarits — dans l'ordre où
     * les deux templates les font se succéder. Sert à la validation, à
     * l'import et à l'affichage : une seule liste à tenir à jour.
     *
     * `competence` (Competency) n'existe que sur le gabarit primaire/maternelle
     * ; `teaching_learning_strategies` (Teaching / Strategy) n'existe que sur
     * le secondaire. Les deux sont acceptés côté serveur quel que soit le
     * cycle — c'est l'écran qui n'affiche que la colonne pertinente — plutôt
     * que de dupliquer la liste des champs par cycle ici.
     */
    public const CHAMPS_FICHE = [
        'topic', 'sous_topic', 'competence', 'expected_learning_outcomes',
        'entry_behaviour', 'teaching_aids', 'teaching_learning_strategies',
        'facilitators_activities', 'learners_activities',
        'assessment', 'assignment', 'remarks',
    ];

    /** Repères de calendrier de la ligne, en plus des champs de texte libre. */
    public const CHAMPS_CALENDRIER = ['semaine', 'date_prevue', 'date_realisee', 'duree'];

    protected $casts = [
        'date_prevue' => 'date',
        'date_realisee' => 'date',
        'colonnes_libres' => 'array',
    ];

    /** Scopé par école via la classe qui porte l'affectation. */
    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return $query->whereHas('classeMatiere.classe', fn ($q) => is_array($schoolId) ? $q->whereIn('school_id', $schoolId) : $q->where('school_id', $schoolId));
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

    /**
     * Gabarit de fiche applicable : maternelle et primaire partagent le même
     * (avec sa colonne Competency), le secondaire a le sien (avec Teaching
     * Strategy et un cartouche Department/Specialty/Module-Competency).
     */
    public static function cyclePour(?string $typeEcole): string
    {
        return in_array($typeEcole, ['maternelle', 'primaire'], true) ? 'primaire' : 'secondaire';
    }
}
