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
 * Le barème, lui, est porté par l'attribution et non par la compétence : la
 * même compétence ne se note pas de la même façon d'une classe à l'autre.
 *
 * L'enseignant, lui, se nomme sur l'attribution, et seulement par exception :
 * laissée vide, la compétence revient au titulaire de la classe, qui la tient
 * et la saisit. La renseigner confie la compétence à un intervenant qui n'est
 * pas titulaire — et les matières qu'elle installe dans la classe lui
 * reviennent ({@see ClasseCompetence::enseignantEffectifId()}).
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
     * `$bareme` (notation, volet pratique, répartition des points) s'applique à
     * toutes les compétences du lot et vaut pour CETTE classe seule : c'est là
     * qu'il vit désormais ({@see ClasseCompetence}). Vide, il laisse une
     * attribution existante intacte — réattribuer pour compléter des matières
     * n'a pas à écraser un barème réglé à la main.
     *
     * @param  list<int>  $competenceIds
     * @param  array<string, mixed>  $bareme
     * @return array{attribuees: int, matieres: int}
     */
    public function attribuer(Classe $classe, array $competenceIds, array $bareme = []): array
    {
        $competences = Competence::where('school_id', $classe->school_id)
            ->whereIn('id', $competenceIds)
            ->with('matieres')
            ->get();

        return $this->transaction(function () use ($classe, $competences, $bareme) {
            $attribuees = 0;
            $matieres = 0;

            foreach ($competences as $competence) {
                // `updateOrCreate` plutôt que `firstOrCreate` : réimporter un
                // fichier dont le barème a changé doit corriger l'attribution
                // existante, pas la laisser sur l'ancien réglage. Sans barème
                // fourni, l'appel se comporte comme avant — rien à mettre à
                // jour, seules les matières manquantes sont complétées.
                $attribution = ClasseCompetence::updateOrCreate(
                    ['classe_id' => $classe->id, 'competence_id' => $competence->id],
                    $bareme,
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
    /**
     * Confie la compétence à un enseignant — ou la rend au titulaire en
     * passant `null` — et aligne les matières qu'elle a installées dans la
     * classe : laisser une matière sur l'ancien responsable ferait apparaître
     * deux enseignants pour un même bloc, l'un à l'écran des compétences,
     * l'autre dans l'emploi du temps et les séances.
     */
    public function confier(ClasseCompetence $attribution, ?int $personnelId): ClasseCompetence
    {
        return $this->transaction(function () use ($attribution, $personnelId) {
            $attribution->update(['personnel_id' => $personnelId]);
            $attribution->refresh();

            $matiereIds = Matiere::where('competence_id', $attribution->competence_id)->pluck('id');

            ClasseMatiere::where('classe_id', $attribution->classe_id)
                ->whereIn('matiere_id', $matiereIds)
                ->update(['personnel_id' => $attribution->enseignantEffectifId()]);

            return $attribution;
        });
    }

    /**
     * Recopie des attributions vers d'autres classes : la compétence, son
     * barème et ses volets, et avec eux les matières qu'elle installe.
     *
     * Une compétence déjà attribuée dans la classe visée est ignorée, jamais
     * écrasée — on ne défait pas un barème réglé à la main en recopiant un
     * voisin.
     *
     * L'enseignant ne se recopie pas : au primaire, c'est le titulaire de la
     * classe d'arrivée qui tient les compétences. Y reporter le responsable
     * nommé dans la classe source désignerait un agent qui n'y met pas les
     * pieds (même raison que `ClasseMatiereController::enseignantPour()`).
     *
     * @param  Collection<int, ClasseCompetence>  $attributions
     * @param  Collection<int, Classe>  $classesCibles
     * @return array{copiees: int, ignorees: int, matieres: int}
     */
    public function copier(Collection $attributions, Collection $classesCibles): array
    {
        return $this->transaction(function () use ($attributions, $classesCibles) {
            $copiees = 0;
            $ignorees = 0;
            $matieres = 0;

            foreach ($classesCibles as $classe) {
                $deja = ClasseCompetence::where('classe_id', $classe->id)->pluck('competence_id');

                foreach ($attributions as $source) {
                    if ($deja->contains($source->competence_id)) {
                        $ignorees++;

                        continue;
                    }

                    $copie = ClasseCompetence::create([
                        'classe_id' => $classe->id,
                        'competence_id' => $source->competence_id,
                        'notation' => $source->notation,
                        'evalue_pratique' => $source->evalue_pratique,
                        'repartition_volets' => $source->repartition_volets,
                        'groupe' => $source->groupe,
                        'statut' => $source->statut,
                    ]);
                    $copie->setRelation('classe', $classe);

                    $matieres += $this->installerMatieres(
                        $copie,
                        Matiere::where('competence_id', $source->competence_id)->get(),
                    );
                    $copiees++;
                }
            }

            return ['copiees' => $copiees, 'ignorees' => $ignorees, 'matieres' => $matieres];
        });
    }

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
     * La matière revient à l'enseignant de l'attribution — le responsable
     * nommé, à défaut le titulaire de la classe
     * ({@see ClasseCompetence::enseignantEffectifId()}). Elle peut ensuite
     * être réaffectée individuellement via `ClasseMatiereController`.
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
                'personnel_id' => $attribution->enseignantEffectifId(),
                'groupe' => $attribution->groupe ?? 1,
            ]);

            $installees++;
        }

        return $installees;
    }
}
