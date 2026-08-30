<?php

namespace App\Services;

use App\Models\EquipementMobilier;
use App\Models\Infrastructure;
use Illuminate\Support\Collection;

class InfrastructureService
{
    /** @param int|array<int> $schoolId */
    public function listInfrastructures(int|array $schoolId): Collection
    {
        return Infrastructure::forSchool($schoolId)->orderBy('type')->orderBy('libelle')->get();
    }

    public function createInfrastructure(int $schoolId, array $attributes): Infrastructure
    {
        return Infrastructure::create([...$attributes, 'school_id' => $schoolId]);
    }

    public function updateInfrastructure(Infrastructure $infrastructure, array $attributes): Infrastructure
    {
        $infrastructure->update($attributes);

        return $infrastructure;
    }

    public function deleteInfrastructure(Infrastructure $infrastructure): void
    {
        $infrastructure->delete();
    }

    /** @param int|array<int> $schoolId */
    public function listEquipements(int|array $schoolId): Collection
    {
        return EquipementMobilier::forSchool($schoolId)->orderBy('nature')->get();
    }

    public function createEquipement(int $schoolId, array $attributes): EquipementMobilier
    {
        return EquipementMobilier::create([...$attributes, 'school_id' => $schoolId]);
    }

    public function updateEquipement(EquipementMobilier $equipement, array $attributes): EquipementMobilier
    {
        $equipement->update($attributes);

        return $equipement;
    }

    public function deleteEquipement(EquipementMobilier $equipement): void
    {
        $equipement->delete();
    }

    /**
     * Rapport façon rentrée MINEDUB (tableaux 18-20) : les salles de classe
     * et le bloc administratif croisés matériau × état, les autres
     * infrastructures en quantité brute, et le mobilier avec ses besoins.
     *
     * @param  int|array<int>  $schoolId
     * @return array{
     *     salles_classe: array<string, array<string, int>>,
     *     bloc_administratif: array<string, array<string, int>>,
     *     autres: array<string, int>,
     *     equipements: \Illuminate\Support\Collection<int, EquipementMobilier>,
     * }
     */
    public function rapport(int|array $schoolId): array
    {
        $infrastructures = Infrastructure::forSchool($schoolId)->get();
        $materiaux = ['dur', 'semi_dur', 'provisoire'];
        $etats = ['bon', 'assez_bon', 'mauvais'];

        $grille = static function (Collection $lignes) use ($materiaux, $etats): array {
            $grille = [];
            foreach ($materiaux as $materiau) {
                foreach ($etats as $etat) {
                    $grille[$materiau][$etat] = 0;
                }
            }

            foreach ($lignes as $ligne) {
                if (! in_array($ligne->materiau, $materiaux, true) || ! in_array($ligne->etat, $etats, true)) {
                    continue;
                }
                $grille[$ligne->materiau][$etat = $ligne->etat] += $ligne->quantite;
            }

            return $grille;
        };

        $autresTypes = ['wc', 'cloture', 'point_eau', 'electricite', 'aire_jeu', 'logement_maitre', 'autre'];
        $autres = [];
        foreach ($autresTypes as $type) {
            $autres[$type] = (int) $infrastructures->where('type', $type)->sum('quantite');
        }

        return [
            'salles_classe' => $grille($infrastructures->where('type', 'salle_classe')),
            'bloc_administratif' => $grille($infrastructures->where('type', 'bloc_administratif')),
            'autres' => $autres,
            'equipements' => $this->listEquipements($schoolId),
        ];
    }
}
