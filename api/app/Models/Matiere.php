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
        'notation', 'evalue_pratique', 'statut',
    ];

    protected function casts(): array
    {
        return ['evalue_pratique' => 'boolean'];
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

    public function scopeForSchool(Builder $query, int $schoolId): Builder
    {
        return $query->where('school_id', $schoolId);
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
