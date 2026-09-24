<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

class EmploiDuTemps extends Model
{
    protected $table = 'emplois_du_temps';

    protected $fillable = [
        'school_id',
        'classe_id',
        'classe_matiere_id',
        'type',
        'libelle',
        'emploi_du_temps_element_id',
        'jour',
        'heure_debut',
        'heure_fin',
        'salle',
        'salle_id',
    ];

    protected function casts(): array
    {
        return ['jour' => 'integer'];
    }

    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return is_array($schoolId) ? $query->whereIn('school_id', $schoolId) : $query->where('school_id', $schoolId);
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    public function classeMatiere(): BelongsTo
    {
        return $this->belongsTo(ClasseMatiere::class);
    }

    public function element(): BelongsTo
    {
        return $this->belongsTo(EmploiDuTempsElement::class, 'emploi_du_temps_element_id');
    }

    public function salleReference(): BelongsTo
    {
        return $this->belongsTo(Salle::class);
    }

    /**
     * Classes qui rejoignent la classe porteuse sur ce créneau. Vide pour un
     * cours ordinaire.
     */
    public function classesAssociees(): BelongsToMany
    {
        return $this->belongsToMany(Classe::class, 'emploi_du_temps_classe')->withTimestamps();
    }

    /**
     * Remplace les classes associées, en faisant avancer `updated_at` si la
     * liste change : le pivot n'a pas d'horodatage propre côté synchronisation
     * mobile (cf. RegistreSync, `classes_associees`), c'est le créneau qui
     * doit redescendre pour que le client apprenne le changement.
     *
     * @param  array<int, int>  $classeIds
     */
    public function synchroniserClassesAssociees(array $classeIds): void
    {
        $changements = $this->classesAssociees()->sync($classeIds);

        if ($changements['attached'] !== [] || $changements['detached'] !== []) {
            $this->touch();
        }
    }

    /**
     * Un tronc commun ne se déclare pas, il se constate : dès qu'une classe
     * rejoint le créneau, le cours en est un. Pas de drapeau à maintenir en
     * accord avec le pivot.
     */
    public function estTroncCommun(): bool
    {
        return $this->classesAssociees()->exists();
    }

    /**
     * Toutes les classes du cours, porteuse comprise — c'est ce périmètre qui
     * définit qui doit être à l'appel.
     *
     * @return Collection<int, Classe>
     */
    public function toutesLesClasses(): Collection
    {
        $this->loadMissing('classe', 'classesAssociees');

        return collect([$this->classe])
            ->filter()
            ->concat($this->classesAssociees)
            ->unique('id')
            ->values();
    }

    /** @return list<int> */
    public function idsDesClasses(): array
    {
        return $this->toutesLesClasses()->pluck('id')->all();
    }
}
