<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Compétence évaluée du primaire et de la maternelle : l'unité que le bulletin
 * note et que le livret officiel nomme.
 *
 * Elle porte tout ce qui relève de l'évaluation — barème, volets, répartition
 * des points — là où la matière ne garde que le contenu enseigné. « Langue et
 * communication » est une compétence ; la lecture, l'écriture et la langue
 * nationale sont les matières qui la composent.
 */
class Competence extends Model
{
    /** Volets systématiques ; le pratique s'ajoute quand la compétence s'y prête. */
    public const VOLETS_BASE = ['oral', 'ecrit', 'savoir_etre'];

    protected $fillable = [
        'school_id', 'label_fr', 'label_en', 'abbreviation',
        'notation', 'evalue_pratique', 'repartition_volets', 'ordre', 'statut',
    ];

    protected function casts(): array
    {
        return [
            'notation' => 'integer',
            'evalue_pratique' => 'boolean',
            'repartition_volets' => 'array',
            'ordre' => 'integer',
        ];
    }

    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return is_array($schoolId) ? $query->whereIn('school_id', $schoolId) : $query->where('school_id', $schoolId);
    }

    public function scopeActives(Builder $query): Builder
    {
        return $query->where('statut', 'actif');
    }

    /**
     * Volets évalués, dans l'ordre d'affichage du bulletin.
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
     * Une compétence sans répartition explicite (barème par défaut réparti à
     * parts égales, ou une compétence de maternelle qui n'évalue par nature
     * aucun volet en points) garde tous ses volets structurels : l'absence de
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
     * Points attribués à chaque volet. À défaut de répartition explicite, le
     * barème se partage à parts égales — une compétence tout juste créée reste
     * ainsi notable sans réglage préalable.
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

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /** Contenu enseigné au titre de cette compétence. */
    public function matieres(): HasMany
    {
        return $this->hasMany(Matiere::class);
    }

    public function classeCompetences(): HasMany
    {
        return $this->hasMany(ClasseCompetence::class);
    }
}
