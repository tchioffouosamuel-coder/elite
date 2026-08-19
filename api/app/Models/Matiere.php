<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Matiere extends Model
{
    protected $fillable = [
        'school_id', 'departement_id', 'nom', 'nom_en', 'abbreviation',
        'notation', 'evalue_pratique', 'repartition_volets', 'statut',
    ];

    protected function casts(): array
    {
        return [
            'evalue_pratique' => 'boolean',
            'repartition_volets' => 'array',
        ];
    }

    /**
     * Volets d'évaluation de la matière au primaire : oral, écrit et
     * savoir-être systématiquement, pratique seulement si la matière s'y prête.
     *
     * @return list<string>
     */
    public function composantes(): array
    {
        return $this->evalue_pratique
            ? ['oral', 'ecrit', 'savoir_etre', 'pratique']
            : ['oral', 'ecrit', 'savoir_etre'];
    }

    /**
     * Nombre de points de chaque volet, tel que défini à la création de la
     * matière. À défaut de répartition explicite (matières créées avant
     * l'ajout de cette fonctionnalité), le barème est partagé à parts égales
     * entre les volets — le comportement historique.
     *
     * @return array<string, float>
     */
    public function repartitionVolets(): array
    {
        $composantes = $this->composantes();

        if ($this->repartition_volets) {
            return collect($composantes)
                ->mapWithKeys(fn (string $composante) => [
                    $composante => (float) ($this->repartition_volets[$composante] ?? 0),
                ])
                ->all();
        }

        $bareme = (float) ($this->notation ?? 20);
        $part = $composantes !== [] ? round($bareme / count($composantes), 2) : 0.0;

        return array_fill_keys($composantes, $part);
    }

    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return is_array($schoolId) ? $query->whereIn('school_id', $schoolId) : $query->where('school_id', $schoolId);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function departement(): BelongsTo
    {
        return $this->belongsTo(Departement::class);
    }

    public function classeMatieres(): HasMany
    {
        return $this->hasMany(ClasseMatiere::class);
    }
}
