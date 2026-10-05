<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Attribution d'une compétence à une classe : c'est ici que vit le bloc
 * d'affichage du bulletin, et c'est ici que vit l'évaluation.
 *
 * La compétence dit ce que l'on évalue ; l'attribution dit comment on le note
 * DANS CETTE CLASSE — barème, volets retenus, répartition des points. Une même
 * compétence pèse ainsi 20 au CM2 et 10 au CP, et n'évalue le volet pratique
 * que là où la classe le travaille.
 *
 * Attribuer une compétence crée d'office l'affectation de chacune de ses
 * matières à la classe (cf. `CompetenceAttributionService`) : l'utilisateur
 * choisit un bloc, pas une liste de matières une à une. L'enseignant, lui,
 * est porté par chaque matière ({@see ClasseMatiere::enseignant()}), pas par
 * la compétence — un enseignant par matière, y compris au primaire.
 */
class ClasseCompetence extends Model
{
    /** Volets systématiques ; le pratique s'ajoute quand la classe l'évalue. */
    public const VOLETS_BASE = ['oral', 'ecrit', 'savoir_etre'];

    protected $fillable = [
        'classe_id', 'competence_id', 'notation', 'evalue_pratique',
        'repartition_volets', 'groupe', 'statut',
    ];

    protected function casts(): array
    {
        return [
            'notation' => 'integer',
            'evalue_pratique' => 'boolean',
            'repartition_volets' => 'array',
            'groupe' => 'integer',
        ];
    }

    /** Filtre par école via la classe : la table n'a pas de school_id en propre. */
    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return $query->whereHas(
            'classe',
            fn ($q) => is_array($schoolId) ? $q->whereIn('school_id', $schoolId) : $q->where('school_id', $schoolId),
        );
    }

    /** Barème de la compétence dans cette classe ; 20 à défaut de réglage. */
    public function bareme(): int
    {
        return (int) ($this->notation ?? 20);
    }

    /**
     * Volets évalués dans cette classe, dans l'ordre d'affichage du bulletin.
     *
     * @return list<string>
     */
    public function volets(): array
    {
        return $this->evalue_pratique
            ? [...self::VOLETS_BASE, 'pratique']
            : self::VOLETS_BASE;
    }

    /**
     * Volets réellement notés : {@see volets()} amputé de ceux auxquels aucun
     * point n'a été explicitement alloué (0, ou laissé de côté — cf.
     * `repartitionVolets()`). Un volet à 0 point n'a rien à faire dans la
     * grille de saisie ni sur le bulletin : personne ne remplit ni ne lit une
     * colonne qui ne peut porter aucune note.
     *
     * Une attribution sans répartition explicite (barème par défaut réparti à
     * parts égales, ou une classe de maternelle qui n'évalue par nature aucun
     * volet en points) garde tous ses volets structurels : l'absence de
     * réglage n'est pas un volet volontairement désactivé.
     *
     * @return list<string>
     */
    public function voletsNotes(): array
    {
        if (! $this->repartition_volets) {
            return $this->volets();
        }

        return array_values(array_filter(
            $this->volets(),
            fn (string $volet) => (float) ($this->repartition_volets[$volet] ?? 0) > 0,
        ));
    }

    /**
     * Points attribués à chaque volet dans cette classe. À défaut de
     * répartition explicite, le barème se partage à parts égales — une
     * attribution tout juste posée reste ainsi notable sans réglage préalable.
     *
     * @return array<string, float>
     */
    public function repartitionVolets(): array
    {
        $volets = $this->volets();

        if ($this->repartition_volets) {
            return collect($volets)
                ->mapWithKeys(fn (string $volet) => [$volet => (float) ($this->repartition_volets[$volet] ?? 0)])
                ->all();
        }

        $part = $volets !== [] ? round((float) $this->notation / count($volets), 2) : 0.0;

        return array_fill_keys($volets, $part);
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    public function competence(): BelongsTo
    {
        return $this->belongsTo(Competence::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }
}
