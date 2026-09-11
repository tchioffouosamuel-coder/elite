<?php

namespace App\Services;

use App\Models\ChampPersonnalise;
use App\Models\ClasseMatiere;
use App\Models\EmploiDuTemps;
use App\Models\Evaluation;
use App\Models\Matiere;
use App\Models\Note;
use App\Models\ProgressionColonne;
use App\Models\ProgressionItem;
use App\Models\Revendication;
use App\Models\Seance;

/**
 * Fusionne deux matières en doublon (même contenu, orthographe différente —
 * « Law and government » / « Law and gouvernment ») : les affectations de
 * classe de la matière supprimée rejoignent celles de la matière conservée,
 * puis la matière supprimée disparaît.
 *
 * Quand les deux matières sont déjà enseignées dans la même classe, il y a
 * deux affectations à réconcilier plutôt qu'une seule à déplacer : tout ce
 * que portait celle du doublon (notes, progression, séances…) rejoint celle
 * de la matière conservée avant que la première ne disparaisse.
 */
class MatiereFusionService extends BaseService
{
    /**
     * @return array{classes_deplacees: int, classes_fusionnees: int, notes_ignorees: int}
     */
    public function fusionner(Matiere $conservee, Matiere $supprimee): array
    {
        return $this->transaction(function () use ($conservee, $supprimee) {
            $deplacees = 0;
            $fusionnees = 0;
            $notesIgnorees = 0;

            foreach ($supprimee->classeMatieres()->get() as $source) {
                $cible = ClasseMatiere::where('classe_id', $source->classe_id)
                    ->where('matiere_id', $conservee->id)
                    ->first();

                if ($cible === null) {
                    $source->update(['matiere_id' => $conservee->id]);
                    $deplacees++;

                    continue;
                }

                $notesIgnorees += $this->fusionnerAffectation($source, $cible);
                $fusionnees++;
            }

            $supprimee->delete();

            return ['classes_deplacees' => $deplacees, 'classes_fusionnees' => $fusionnees, 'notes_ignorees' => $notesIgnorees];
        });
    }

    /**
     * Rattache à `$cible` tout ce que portait `$source`, puis supprime cette
     * dernière. Seules les notes peuvent entrer en conflit — une même case
     * élève/séquence ne peut exister qu'une fois par affectation : celle de
     * la matière conservée l'emporte, celle du doublon est abandonnée plutôt
     * que de bloquer la fusion.
     *
     * @return int notes abandonnées faute de pouvoir rejoindre `$cible`
     */
    private function fusionnerAffectation(ClasseMatiere $source, ClasseMatiere $cible): int
    {
        $ignorees = 0;

        foreach ($source->notes()->get() as $note) {
            $conflit = Note::where('classe_matiere_id', $cible->id)
                ->where('eleve_id', $note->eleve_id)
                ->where('sequence_id', $note->sequence_id)
                ->exists();

            if ($conflit) {
                $ignorees++;

                continue;
            }

            $note->update(['classe_matiere_id' => $cible->id]);
        }

        foreach ([ProgressionColonne::class, ProgressionItem::class, ChampPersonnalise::class, Evaluation::class, EmploiDuTemps::class, Seance::class, Revendication::class] as $modele) {
            $modele::where('classe_matiere_id', $source->id)->update(['classe_matiere_id' => $cible->id]);
        }

        $source->delete();

        return $ignorees;
    }
}
