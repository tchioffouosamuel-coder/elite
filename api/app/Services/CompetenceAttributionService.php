<?php

namespace App\Services;

use App\Models\Classe;
use App\Models\ClasseCompetence;
use App\Models\ClasseMatiere;
use App\Models\Competence;
use App\Models\Matiere;
use Illuminate\Support\Collection;

/**
 * Attribution des compétences aux classes du primaire et de la maternelle.
 *
 * On n'attribue plus les matières une à une : choisir « Langue et
 * communication » pour une classe y installe d'office la lecture, l'écriture et
 * la langue nationale. Les affectations de matières continuent d'exister —
 * l'emploi du temps, les séances et la progression s'y accrochent — mais elles
 * découlent de la compétence au lieu d'être saisies à la main.
 *
 * L'enseignant est porté par chaque matière, pas par la compétence : un
 * enseignant par matière, y compris au primaire (cf. {@see ClasseMatiere}).
 * Sans enseignant désigné, une matière nouvellement installée prend par
 * défaut le titulaire de la classe — qui reste de toute façon seul habilité à
 * saisir les notes de toutes les compétences ({@see \App\Services\NotePrimaireService::peutSaisir()}).
 */
class CompetenceAttributionService extends BaseService
{
    /**
     * Attribue des compétences à une classe et installe leurs matières.
     *
     * Idempotent : une compétence déjà attribuée voit seulement ses matières
     * manquantes complétées — réattribuer après avoir ajouté une matière au
     * référentiel est le geste normal.
     *
     * @param  list<int>  $competenceIds
     * @return array{attribuees: int, matieres: int}
     */
    public function attribuer(Classe $classe, array $competenceIds): array
    {
        $competences = Competence::where('school_id', $classe->school_id)
            ->whereIn('id', $competenceIds)
            ->with('matieres')
            ->get();

        return $this->transaction(function () use ($classe, $competences) {
            $attribuees = 0;
            $matieres = 0;

            foreach ($competences as $competence) {
                // `firstOrCreate` : une compétence déjà attribuée n'a plus rien
                // à mettre à jour (l'enseignant vit désormais sur la matière) —
                // seules ses matières manquantes sont complétées.
                $attribution = ClasseCompetence::firstOrCreate(
                    ['classe_id' => $classe->id, 'competence_id' => $competence->id],
                );

                // `refresh()` charge les valeurs par défaut posées en base
                // (`groupe`, `statut`) : sans lui, le modèle tout juste créé les
                // porte à null et les recopie telles quelles sur les matières.
                $attribution->refresh();

                $attribuees++;
                $matieres += $this->installerMatieres($attribution, $competence->matieres);
            }

            return ['attribuees' => $attribuees, 'matieres' => $matieres];
        });
    }

    /**
     * Retire une compétence d'une classe, et avec elle les affectations de ses
     * matières — mais seulement celles-là : une matière installée autrement
     * n'a pas à disparaître parce qu'un bloc voisin est retiré.
     */
    public function retirer(ClasseCompetence $attribution): void
    {
        $this->transaction(function () use ($attribution) {
            $matiereIds = Matiere::where('competence_id', $attribution->competence_id)->pluck('id');

            ClasseMatiere::where('classe_id', $attribution->classe_id)
                ->whereIn('matiere_id', $matiereIds)
                ->delete();

            $attribution->delete();
        });
    }

    /**
     * Propage une matière nouvellement rattachée à une compétence vers toutes
     * les classes qui portent déjà cette compétence.
     *
     * Sans cela, ajouter une matière au référentiel n'atteindrait que les
     * classes attribuées ensuite, et l'établissement devrait repasser sur
     * chacune — exactement la corvée que la compétence supprime.
     */
    public function propagerMatiere(Matiere $matiere): int
    {
        if ($matiere->competence_id === null) {
            return 0;
        }

        $attributions = ClasseCompetence::where('competence_id', $matiere->competence_id)->get();

        return $this->transaction(function () use ($attributions, $matiere) {
            $installees = 0;

            foreach ($attributions as $attribution) {
                $installees += $this->installerMatieres($attribution, collect([$matiere]));
            }

            return $installees;
        });
    }

    /**
     * Installe les matières d'une compétence dans la classe, sans écraser une
     * affectation déjà en place — un enseignant remplacé sur une matière
     * précise doit survivre à une réattribution du bloc.
     *
     * Sans enseignant désigné, une matière nouvellement installée prend par
     * défaut le titulaire de la classe : c'est lui qui l'enseigne tant que
     * personne d'autre n'a été affecté explicitement (via
     * `ClasseMatiereController`), et c'est de toute façon lui seul qui est
     * habilité à saisir les notes de la compétence.
     *
     * @param  Collection<int, Matiere>  $matieres
     */
    private function installerMatieres(ClasseCompetence $attribution, Collection $matieres): int
    {
        $installees = 0;

        foreach ($matieres as $matiere) {
            $existante = ClasseMatiere::where('classe_id', $attribution->classe_id)
                ->where('matiere_id', $matiere->id)
                ->first();

            if ($existante !== null) {
                continue;
            }

            ClasseMatiere::create([
                'classe_id' => $attribution->classe_id,
                'matiere_id' => $matiere->id,
                'personnel_id' => $attribution->classe->titulaire_id,
                'groupe' => $attribution->groupe ?? 1,
            ]);

            $installees++;
        }

        return $installees;
    }
}
