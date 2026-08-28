<?php

namespace App\Repositories;

use App\Models\Eleve;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class EleveRepository extends BaseRepository
{
    public function __construct(Eleve $model)
    {
        parent::__construct($model);
    }

    /**
     * Le compte borne la liste à ses classes : un surveillant général chargé
     * de six classes ne feuillette pas les 1 800 élèves de l'établissement.
     *
     * @param  int|array<int>  $schoolId
     */
    public function paginateForSchool(?User $user, int|array $schoolId, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->query()
            ->forSchool($schoolId)
            ->dansPerimetre($user)
            ->with(['classe.niveau', 'school:id,name,code,type', 'tuteurs.telephones'])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nom_complet', 'like', "%{$search}%")
                        ->orWhere('matricule', 'like', "%{$search}%");
                });
            })
            ->when($filters['classe_id'] ?? null, fn ($query, $id) => $query->where('classe_id', $id))
            ->when($filters['sexe'] ?? null, fn ($query, $sexe) => $query->where('sexe', $sexe))
            ->when($filters['statut'] ?? null, fn ($query, $statut) => $query->where('statut', $statut))
            ->orderBy('nom_complet')
            ->paginate($perPage);
    }

    /**
     * Recherche transverse : nom de l'élève, matricule, ou nom/téléphone d'un
     * de ses tuteurs — un secrétariat qui répond au téléphone n'a souvent que
     * le nom du parent ou le numéro affiché, pas celui de l'élève. Bornée à
     * un nombre raisonnable de résultats : c'est une recherche rapide, pas un
     * export, et un terme trop court remonterait autrement des centaines de
     * correspondances inexploitables.
     *
     * @param  int|array<int>  $schoolId
     * @return Collection<int, Eleve>
     */
    public function rechercheGlobale(int|array $schoolId, ?User $user, string $terme, int $limite = 50): Collection
    {
        return $this->query()
            ->forSchool($schoolId)
            ->dansPerimetre($user)
            ->with(['classe.niveau', 'school:id,name,code,type', 'tuteurs.telephones'])
            ->where(function ($query) use ($terme) {
                $query->where('nom_complet', 'like', "%{$terme}%")
                    ->orWhere('matricule', 'like', "%{$terme}%")
                    ->orWhereHas('tuteurs', function ($tuteurs) use ($terme) {
                        $tuteurs->where('nom_complet', 'like', "%{$terme}%")
                            ->orWhere('telephone', 'like', "%{$terme}%");
                    });
            })
            ->orderBy('nom_complet')
            ->limit($limite)
            ->get();
    }
}
