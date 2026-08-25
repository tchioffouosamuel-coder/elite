<?php

namespace App\Repositories;

use App\Models\Classe;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class ClasseRepository extends BaseRepository
{
    public function __construct(Classe $model)
    {
        parent::__construct($model);
    }

    /**
     * Le compte est passé pour borner la liste à son périmètre : la direction
     * reçoit toutes les classes de l'établissement, un enseignant ou un
     * surveillant général seulement les siennes.
     */
    public function forSchool(?User $user, int|array $schoolId, array $filters = []): Collection
    {
        return $this->query()
            ->forSchool($schoolId)
            ->dansPerimetre($user)
            ->when($filters['niveau_id'] ?? null, fn ($query, $id) => $query->where('niveau_id', $id))
            ->withCount([
                'eleves',
                'eleves as garcons_count' => fn ($query) => $query->where('sexe', 'M'),
                'eleves as filles_count' => fn ($query) => $query->where('sexe', 'F'),
                // Sert la page « Séances & appel » : le trimestre actif borne
                // le compte, sinon une classe affiche toujours le cumul de
                // toute sa scolarité au lieu de « ce qu'il reste à faire ».
                'seances as seances_count' => fn ($query) => $query->whereHas(
                    'trimestre',
                    fn ($t) => $t->where('is_active', true)
                ),
            ])
            ->with(['niveau', 'niveauScolaire', 'sousSysteme', 'professeurPrincipal', 'titulaire', 'school:id,name,code,type'])
            ->orderBy('nom')
            ->get();
    }
}
