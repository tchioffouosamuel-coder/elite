<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Détail pédagogique figé d'une classe pour une année révolue — le point de
 * vérité une fois l'année archivée, indépendant des tables vivantes qui
 * continuent d'évoluer (une classe est un gabarit permanent, réutilisé
 * l'année suivante). Les colonnes JSON sont des tableaux de scalaires
 * construits par {@see \App\Services\ArchivageService}, jamais un dump direct
 * de modèles Eloquent — pour ne jamais dépendre de leur forme future.
 */
class ArchiveClasseAnnee extends Model
{
    protected $table = 'archives_classe_annee';

    protected $fillable = [
        'school_id', 'annee_scolaire_id', 'classe_id', 'classe_nom', 'niveau_libelle', 'conseil_classe_id',
        'effectif', 'roster_json', 'notes_json', 'absences_json', 'discipline_json', 'infirmerie_json',
        'archive_par', 'archive_le',
    ];

    protected function casts(): array
    {
        return [
            'roster_json' => 'array',
            'notes_json' => 'array',
            'absences_json' => 'array',
            'discipline_json' => 'array',
            'infirmerie_json' => 'array',
            'archive_le' => 'datetime',
        ];
    }

    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return is_array($schoolId) ? $query->whereIn('school_id', $schoolId) : $query->where('school_id', $schoolId);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function anneeScolaire(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class);
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    public function conseilClasse(): BelongsTo
    {
        return $this->belongsTo(ConseilClasse::class);
    }

    public function archiveParUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archive_par');
    }
}
