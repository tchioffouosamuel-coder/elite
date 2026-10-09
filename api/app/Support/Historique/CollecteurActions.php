<?php

namespace App\Support\Historique;

use App\Models\ActionAnnulable;
use App\Models\Eleve;
use App\Models\Note;
use App\Models\ObservationEvaluation;
use App\Models\Personnel;
use Illuminate\Database\Eloquent\Model;

class CollecteurActions
{
    private array $pile = [];

    public function ouvrir(?array $modelesImport = null): void
    {
        $this->pile[] = ['lignes' => [], 'incompatible' => false, 'modelesImport' => $modelesImport];
    }

    public function verrouiller(Model $modele): void
    {
        if ($this->pile === [] || ! in_array($modele::class, $this->pile[array_key_last($this->pile)]['modelesImport'] ?? [], true)) {
            return;
        }
        $actuel = $modele::whereKey($modele->getKey())->lockForUpdate()->first();
        abort_unless($actuel && self::valeurs($actuel) == self::valeurs($modele, $modele->getRawOriginal()), 409,
            'Ces donnees ont change pendant l\'import. Relancez-le.');
    }

    public static function valeurs(Model $modele, ?array $attributs = null): array
    {
        return array_diff_key($attributs ?? $modele->getAttributes(), array_flip(['id', 'created_at', 'updated_at']));
    }

    public function enregistrer(string $evenement, Model $modele): void
    {
        if ($this->pile === [] || in_array($modele::class, config('audit.modeles_exclus', []), true)
            || $modele instanceof ActionAnnulable) {
            return;
        }

        $niveau = array_key_last($this->pile);
        $import = $this->pile[$niveau]['modelesImport'];
        if (! in_array($modele::class, $import ?? [Eleve::class, Personnel::class, Note::class, ObservationEvaluation::class], true)) {
            $this->pile[$niveau]['incompatible'] = true;

            return;
        }

        if ($import === null && $evenement !== 'updated' && ! ($evenement === 'created' && ($modele instanceof Note || $modele instanceof ObservationEvaluation))) {
            $this->pile[$niveau]['incompatible'] = true;

            return;
        }

        $cle = $modele::class.':'.$modele->getKey();
        if (! array_key_exists($cle, $this->pile[$niveau]['lignes'])) {
            $this->pile[$niveau]['lignes'][$cle] = [
                'modele' => $modele::class,
                'id' => $modele->getKey(),
                // Les notes creees hors ligne peuvent recevoir un autre id sur le serveur.
                'cle' => ($modele instanceof Note || $modele instanceof ObservationEvaluation)
                    ? array_intersect_key($modele->getAttributes(), array_flip($modele instanceof Note
                        ? ['eleve_id', 'classe_matiere_id', 'classe_competence_id', 'sequence_id', 'composante']
                        : ['eleve_id', 'classe_matiere_id', 'classe_competence_id', 'trimestre_id']))
                    : ['id' => $modele->getKey()],
                'avant' => $evenement === 'created' ? null : self::valeurs($modele, $modele->getRawOriginal()),
            ];
        }
    }

    public function fermer(): array
    {
        $capture = array_pop($this->pile);
        $changements = [];
        foreach ($capture['lignes'] as $ligne) {
            $modele = $ligne['modele']::find($ligne['id']);
            if ($modele && ($modele instanceof Note || $modele instanceof ObservationEvaluation)) {
                $ligne['cle'] = array_intersect_key($modele->getAttributes(), array_flip($modele instanceof Note
                    ? ['eleve_id', 'classe_matiere_id', 'classe_competence_id', 'sequence_id', 'composante']
                    : ['eleve_id', 'classe_matiere_id', 'classe_competence_id', 'trimestre_id']));
            }
            $ligne['apres'] = $modele ? self::valeurs($modele) : null;
            if ($ligne['avant'] != $ligne['apres']) {
                $changements[] = $ligne;
            }
        }

        return ['changements' => $changements, 'incompatible' => $capture['incompatible']];
    }
}
