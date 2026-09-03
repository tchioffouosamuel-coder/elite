<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Conseil de classe de fin d'année — un par (classe, année scolaire). Tant
 * que `statut` est `brouillon`, ses décisions peuvent encore être ajustées
 * (seuil, exclusion, grâce) ; `valide` le fige et déclenche l'archivage et
 * les mutations sur les fiches élèves, cf. {@see \App\Services\ConseilClasseService::valider()}.
 */
class ConseilClasse extends Model
{
    protected $table = 'conseils_classe';

    protected $fillable = [
        'school_id', 'annee_scolaire_id', 'classe_id', 'seuil_moyenne', 'motif_seuil',
        'classe_destination_id', 'statut', 'valide_le', 'valide_par',
    ];

    protected function casts(): array
    {
        return [
            'seuil_moyenne' => 'float',
            'valide_le' => 'datetime',
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

    public function classeDestination(): BelongsTo
    {
        return $this->belongsTo(Classe::class, 'classe_destination_id');
    }

    public function valideParUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par');
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(ConseilClasseDecision::class);
    }
}
