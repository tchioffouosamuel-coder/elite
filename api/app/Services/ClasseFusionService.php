<?php

namespace App\Services;

use App\Models\Classe;
use App\Models\ClasseCompetence;
use App\Models\ClasseMatiere;
use App\Models\EmploiDuTemps;
use App\Models\Evaluation;
use App\Models\Note;
use App\Models\ProgressionColonne;
use App\Models\ProgressionItem;
use App\Models\Revendication;
use App\Models\Seance;
use Illuminate\Support\Facades\DB;

class ClasseFusionService extends BaseService
{
    /** @return array{eleves: int, affectations: int, affectations_fusionnees: int, creneaux: int} */
    public function fusionner(Classe $conservee, Classe $supprimee): array
    {
        return $this->transaction(function () use ($conservee, $supprimee) {
            $eleves = $supprimee->eleves()->update(['classe_id' => $conservee->id]);
            $affectations = 0;
            $fusionnees = 0;

            foreach ($supprimee->classeMatieres()->get() as $source) {
                $cible = ClasseMatiere::where('classe_id', $conservee->id)
                    ->where('matiere_id', $source->matiere_id)->first();
                if ($cible === null) {
                    $source->update(['classe_id' => $conservee->id]);
                    $affectations++;
                    continue;
                }

                $this->fusionnerAffectation($source, $cible);
                $fusionnees++;
            }

            foreach ($supprimee->classeCompetences()->get() as $source) {
                $cible = ClasseCompetence::where('classe_id', $conservee->id)
                    ->where('competence_id', $source->competence_id)->first();
                if ($cible === null) {
                    $source->update(['classe_id' => $conservee->id]);
                } else {
                    Note::where('classe_competence_id', $source->id)->update(['classe_competence_id' => $cible->id]);
                    $source->delete();
                }
            }

            $creneaux = EmploiDuTemps::where('classe_id', $supprimee->id)->update(['classe_id' => $conservee->id]);
            Seance::where('classe_id', $supprimee->id)->update(['classe_id' => $conservee->id]);

            $this->deplacerReferencesSimples($supprimee->id, $conservee->id);
            $this->remplacerClasseDansPivot('emploi_du_temps_classe', 'emploi_du_temps_id', $supprimee->id, $conservee->id);
            $this->remplacerClasseDansPivot('emploi_du_temps_element_classe', 'emploi_du_temps_element_id', $supprimee->id, $conservee->id);
            $this->remplacerClasseDansPivot('tronc_commun_groupe_classe', 'tronc_commun_groupe_id', $supprimee->id, $conservee->id);

            $supprimee->delete();

            return ['eleves' => $eleves, 'affectations' => $affectations, 'affectations_fusionnees' => $fusionnees, 'creneaux' => $creneaux];
        });
    }

    private function fusionnerAffectation(ClasseMatiere $source, ClasseMatiere $cible): void
    {
        foreach ($source->notes()->get() as $note) {
            $conflit = Note::where('classe_matiere_id', $cible->id)
                ->where('eleve_id', $note->eleve_id)->where('sequence_id', $note->sequence_id)->exists();
            if (! $conflit) $note->update(['classe_matiere_id' => $cible->id]);
        }

        foreach ([ProgressionColonne::class, ProgressionItem::class, Evaluation::class, EmploiDuTemps::class, Seance::class, Revendication::class] as $modele) {
            $modele::where('classe_matiere_id', $source->id)->update(['classe_matiere_id' => $cible->id]);
        }
        $source->delete();
    }

    private function deplacerReferencesSimples(int $sourceId, int $targetId): void
    {
        foreach (['eleves', 'sanctions', 'dossiers_scolarite', 'visites_infirmerie', 'preinscriptions', 'historiques_scolarite_eleves', 'archives_classe_annee', 'conseils_classe', 'frais_annexe_classe', 'bulletin_publications'] as $table) {
            if (DB::getSchemaBuilder()->hasTable($table)) {
                DB::table($table)->where('classe_id', $sourceId)->update(['classe_id' => $targetId]);
            }
        }
    }

    private function remplacerClasseDansPivot(string $table, string $foreignKey, int $sourceId, int $targetId): void
    {
        if (! DB::getSchemaBuilder()->hasTable($table)) return;
        $lignes = DB::table($table)->where('classe_id', $sourceId)->get();
        foreach ($lignes as $ligne) {
            $existe = DB::table($table)->where($foreignKey, $ligne->{$foreignKey})->where('classe_id', $targetId)->exists();
            if ($existe) DB::table($table)->where($foreignKey, $ligne->{$foreignKey})->where('classe_id', $sourceId)->delete();
            else DB::table($table)->where($foreignKey, $ligne->{$foreignKey})->where('classe_id', $sourceId)->update(['classe_id' => $targetId]);
        }
    }
}
