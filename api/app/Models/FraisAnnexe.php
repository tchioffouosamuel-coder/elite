<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Catalogue des frais annexes : les « accessoires » du reçu de l'établissement.
 *
 * Sans classe rattachée (`classes` vide), un frais s'applique à toute
 * l'école — c'est le comportement d'origine. Une ou plusieurs classes en
 * restreignent la portée (une classe précise, ou un groupe de classes).
 */
class FraisAnnexe extends Model
{
    protected $table = 'frais_annexes';

    protected $fillable = ['school_id', 'annee_scolaire_id', 'libelle', 'montant', 'obligatoire', 'is_active'];

    protected function casts(): array
    {
        return ['montant' => 'integer', 'obligatoire' => 'boolean', 'is_active' => 'boolean'];
    }

    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return is_array($schoolId) ? $query->whereIn('school_id', $schoolId) : $query->where('school_id', $schoolId);
    }

    public function anneeScolaire(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class);
    }

    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(Classe::class, 'frais_annexe_classe');
    }

    /**
     * @param  list<int>  $classeIds  Vide = portée école entière.
     */
    public function synchroniserClasses(array $classeIds): void
    {
        $this->classes()->sync($classeIds);
    }
}
