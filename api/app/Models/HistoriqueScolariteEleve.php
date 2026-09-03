<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une ligne par (élève, année scolaire) : le parcours résumé de l'élève,
 * consultable à tout moment indépendamment de l'archive pédagogique complète
 * ({@see ArchiveClasseAnnee}) — celle-ci porte le détail lourd (notes,
 * absences), celle-ci n'est que « il était en telle classe, avec tel
 * résultat, telle année ».
 */
class HistoriqueScolariteEleve extends Model
{
    protected $table = 'historiques_scolarite_eleves';

    protected $fillable = [
        'eleve_id', 'school_id', 'annee_scolaire_id', 'classe_id', 'classe_nom', 'niveau_libelle',
        'moyenne_annuelle', 'rang_annuel', 'decision', 'gracie', 'motif',
    ];

    protected function casts(): array
    {
        return [
            'moyenne_annuelle' => 'float',
            'gracie' => 'boolean',
        ];
    }

    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return is_array($schoolId) ? $query->whereIn('school_id', $schoolId) : $query->where('school_id', $schoolId);
    }

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    public function anneeScolaire(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class);
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }
}
