<?php

namespace App\Services;

use App\Exports\NotesEnseignantExport;
use App\Exports\NotesEnseignantSheet;
use App\Models\Appreciation;
use App\Models\ClasseCompetence;
use App\Models\ClasseMatiere;
use App\Models\Note;
use App\Models\Personnel;
use App\Models\Sequence;
use App\Models\Trimestre;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

/** Un seul classeur, relu intégralement avant toute écriture de notes. */
class NotesEnseignantService extends BaseService
{
    public function __construct(
        private readonly NoteService $secondaire,
        private readonly NotePrimaireService $primaire,
    ) {}

    public function affectations(Personnel $personnel, User $user): Collection
    {
        $secondaire = $personnel->school->type === 'secondaire';
        $model = $secondaire ? ClasseMatiere::class : ClasseCompetence::class;
        $affectations = $model::forSchool($personnel->school_id)->where('statut', 'actif')
            ->where(fn ($q) => $q->where('personnel_id', $personnel->id)
                ->orWhereHas('classe', fn ($c) => $c->where('titulaire_id', $personnel->id)))
            ->with(['classe.school', $secondaire ? 'matiere' : 'competence'])
            ->orderBy('classe_id')->orderBy('id')->get();
        abort_if($affectations->isEmpty(), 422, 'Aucune affectation trouvée pour cet enseignant.');
        foreach ($affectations as $affectation) {
            abort_unless(($secondaire ? $this->secondaire : $this->primaire)->peutSaisir($user, $affectation), 403,
                "Vous n'êtes pas autorisé à gérer les notes de cet enseignant.");
        }

        return $affectations;
    }

    public function exporter(Collection $affectations, Sequence|Trimestre $periode, bool $modele = false): NotesEnseignantExport
    {
        $feuilles = [];
        if ($periode instanceof Sequence) {
            foreach ($affectations as $cm) {
                $lignes = $this->secondaire->lignesExport($cm, $periode->id, $modele)
                    ->map(fn ($l) => [$l['matricule'], $l['nom_complet'], $l['note']])->all();
                $feuilles[] = new NotesEnseignantSheet(
                    "{$cm->id} {$cm->classe->nom} - {$cm->matiere->nom}",
                    [['Matricule', 'Nom complet', 'Note'], ...$lignes],
                    colorerColonnes: $modele,
                );
            }
        } else {
            foreach ($affectations->groupBy('classe_id') as $classeId => $competences) {
                $groupes = ['Matricule', 'Nom complet'];
                $colonnes = [null, null];
                $lignes = [];
                foreach ($competences as $cc) {
                    $cellules = $this->cellules($cc, $periode);
                    foreach ($cellules as $i => $colonne) {
                        $groupes[] = $i === 0 ? "{$cc->competence->label_fr} [{$cc->id}]" : null;
                        $colonnes[] = $colonne;
                    }
                    foreach ($this->primaire->lignesExport($cc, $periode, $modele) as $key => $eleve) {
                        $lignes[$key] ??= [$eleve['matricule'], $eleve['nom_complet']];
                        foreach ($cellules as $colonne) {
                            $lignes[$key][] = $eleve['cellules'][$colonne] ?? null;
                        }
                    }
                }
                $feuilles[] = new NotesEnseignantSheet(
                    "{$classeId} {$competences->first()->classe->nom}",
                    [$groupes, $colonnes, ...array_values($lignes)], 2,
                    $modele,
                );
            }
        }

        return new NotesEnseignantExport($feuilles);
    }

    /** @return list<string> */
    private function cellules(ClasseCompetence $cc, Trimestre $trimestre): array
    {
        $colonnes = [];
        foreach ($cc->voletsNotes() as $volet) {
            foreach ($trimestre->sequencesRetenues()->values() as $i => $sequence) {
                $colonnes[] = NotePrimaireService::colonne($volet, $i + 1);
            }
        }

        return $colonnes;
    }

    private function erreurFichier(string $message): never
    {
        throw ValidationException::withMessages(['file' => [$message]]);
    }

    public function importer(Collection $affectations, Sequence|Trimestre $periode, User $user, UploadedFile $file, bool $confirmer): array
    {
        try {
            $classeur = IOFactory::load($file->getRealPath());
        } catch (Throwable) {
            $this->erreurFichier('Le fichier Excel est illisible. Téléchargez le modèle puis réessayez.');
        }
        $lots = [];
        $erreurs = [];
        $vues = [];
        try {
            foreach ($classeur->getWorksheetIterator() as $feuille) {
                $nom = $feuille->getTitle();
                if (! preg_match('/^(\d+)\s/u', $nom, $match)) {
                    $this->erreurFichier("Feuille non reconnue : {$nom}. Conservez les noms du modèle.");
                }
                $id = (int) $match[1];
                if (isset($vues[$id])) {
                    $this->erreurFichier("Feuille en double : {$nom}.");
                }
                $vues[$id] = true;
                // Valeurs brutes : une formule ne devient jamais une note à notre insu.
                $rows = $feuille->toArray(null, false, false, false);
                if ($periode instanceof Sequence) {
                    $cm = $affectations->firstWhere('id', $id);
                    if (! $cm) {
                        $this->erreurFichier("La feuille {$nom} ne correspond pas à cet enseignant.");
                    }
                    [$lignes, $errors] = $this->lireSecondaire($cm, $rows, $nom);
                    $lots[] = ['affectation' => $cm, 'lignes' => $lignes];
                    array_push($erreurs, ...$errors);
                } else {
                    $competences = $affectations->where('classe_id', $id)->keyBy('id');
                    if ($competences->isEmpty()) {
                        $this->erreurFichier("La classe de la feuille {$nom} n'est pas confiée à cet enseignant.");
                    }
                    array_push($lots, ...$this->lirePrimaire($competences, $periode, $rows, $nom, $erreurs));
                }
            }
        } finally {
            $classeur->disconnectWorksheets();
        }

        return $this->enregistrer($lots, $periode, $user, $confirmer, $erreurs);
    }

    private function lireSecondaire(ClasseMatiere $cm, array $rows, string $nom): array
    {
        $entetes = array_map(fn ($v) => Str::slug((string) $v, '_'), array_shift($rows) ?? []);
        foreach (['matricule', 'nom_complet', 'note'] as $cle) {
            if (count(array_keys($entetes, $cle, true)) !== 1) {
                $this->erreurFichier("{$nom} : colonne {$cle} absente ou en double.");
            }
        }
        $matriculeCol = array_search('matricule', $entetes, true);
        $noteCol = array_search('note', $entetes, true);
        $eleves = $cm->classe->eleves()->whereNotNull('matricule')->pluck('id', 'matricule');
        $lignes = [];
        $errors = [];
        foreach ($rows as $index => $row) {
            $valeur = $row[$noteCol] ?? null;
            if ($valeur === null || $valeur === '') {
                continue;
            }
            $matricule = trim((string) ($row[$matriculeCol] ?? ''));
            $eleveId = $eleves->get($matricule);
            if (! $eleveId || ! is_numeric($valeur) || (float) $valeur < 0 || (float) $valeur > 20) {
                $errors[] = $nom.' — ligne '.($index + 2)." ({$matricule}) : matricule inconnu dans cette classe ou note hors barème (0 à 20).";

                continue;
            }
            if (isset($lignes[$eleveId])) {
                $this->erreurFichier("{$nom} : matricule en double ({$matricule}).");
            }
            $lignes[$eleveId] = ['eleve_id' => $eleveId, 'valeur' => (float) $valeur];
        }

        return [array_values($lignes), $errors];
    }

    private function lirePrimaire(Collection $competences, Trimestre $trimestre, array $rows, string $nom, array &$erreurs): array
    {
        $groupes = array_shift($rows) ?? [];
        $colonnes = array_shift($rows) ?? [];
        if (Str::slug((string) ($groupes[0] ?? ''), '_') !== 'matricule'
            || Str::slug((string) ($groupes[1] ?? ''), '_') !== 'nom_complet') {
            $this->erreurFichier("{$nom} : les deux premières colonnes doivent être Matricule et Nom complet.");
        }
        $blocs = [];
        $ccId = null;
        for ($i = 2; $i < count($groupes); $i++) {
            if (($groupes[$i] ?? '') !== '' && $groupes[$i] !== null) {
                if (! preg_match('/\[(\d+)\]$/u', (string) $groupes[$i], $match)
                    || ! $competences->has((int) $match[1]) || isset($blocs[(int) $match[1]])) {
                    $this->erreurFichier("{$nom} : compétence inconnue, non attribuée ou en double. Conservez les en-têtes du modèle.");
                }
                $ccId = (int) $match[1];
                $blocs[$ccId] = [];
            }
            $cle = Str::slug((string) ($colonnes[$i] ?? ''), '_');
            if ($ccId === null || ! in_array($cle, array_map(fn ($c) => Str::slug($c, '_'), $this->cellules($competences[$ccId], $trimestre)), true)
                || isset($blocs[$ccId][$cle])) {
                $this->erreurFichier("{$nom} : volet ou séquence non reconnu dans les colonnes du fichier.");
            }
            $blocs[$ccId][$cle] = $i;
        }
        if ($blocs === []) {
            $this->erreurFichier("{$nom} : aucune colonne de compétence reconnue.");
        }
        $matricules = [];
        foreach ($rows as $index => $row) {
            $matricule = NotePrimaireService::normaliser((string) ($row[0] ?? ''));
            if ($matricule === '' && collect(array_slice($row, 2))->contains(fn ($v) => $v !== null && $v !== '')) {
                $erreurs[] = $nom.' — ligne '.($index + 3).' : matricule obligatoire.';
            }
            if ($matricule !== '' && isset($matricules[$matricule])) {
                $this->erreurFichier("{$nom} : matricule en double ({$matricule}).");
            }
            $matricules[$matricule] = true;
        }
        $lots = [];
        foreach ($blocs as $id => $mapping) {
            $import = $this->primaire->preparerImport($competences[$id], $trimestre);
            $import->collection(collect($rows)->filter(fn ($row) => collect($mapping)->contains(fn ($col) => ($row[$col] ?? null) !== null && $row[$col] !== ''))
                ->map(function ($row) use ($mapping) {
                    $ligne = ['matricule' => $row[0] ?? null];
                    foreach ($mapping as $cle => $col) {
                        $ligne[$cle] = $row[$col] ?? null;
                    }

                    return collect($ligne);
                }));
            $lots[] = ['affectation' => $competences[$id], 'lignes' => $import->lignes];
            foreach ($import->erreurs as $erreur) {
                $erreurs[] = "{$nom} — {$competences[$id]->competence->label_fr} : {$erreur}";
            }
        }

        return $lots;
    }

    private function enregistrer(array $lots, Sequence|Trimestre $periode, User $user, bool $confirmer, array $erreurs): array
    {
        return $this->transaction(function () use ($lots, $periode, $user, $confirmer, $erreurs) {
            $schoolId = $lots[0]['affectation']->classe->school_id;
            $sequences = $periode instanceof Sequence ? collect([$periode]) : $periode->sequencesRetenues();
            abort_if($sequences->isEmpty(), 422, 'Aucune séquence configurée pour ce trimestre.');
            foreach ($sequences->sortBy('id') as $sequence) {
                Sequence::with('trimestre.anneeScolaire')->lockForUpdate()->findOrFail($sequence->id)
                    ->verifierSaisieNotes($user, $schoolId);
            }
            $conflits = [];
            $ecritures = [];
            $appreciations = Appreciation::forSchool($schoolId)->pluck('label_fr', 'id');
            foreach ($lots as $lot) {
                $affectation = $lot['affectation'];
                $secondaire = $affectation instanceof ClasseMatiere;
                $champ = $secondaire ? 'classe_matiere_id' : 'classe_competence_id';
                $maternelle = ! $secondaire && $this->primaire->parAppreciation($affectation);
                $existantes = Note::where($champ, $affectation->id)->whereIn('sequence_id', $sequences->pluck('id'))
                    ->lockForUpdate()->get()->keyBy(fn ($n) => "{$n->eleve_id}:{$n->sequence_id}:{$n->composante}");
                $noms = $affectation->classe->eleves()->pluck('nom_complet', 'id');
                foreach ($lot['lignes'] as $ligne) {
                    $sequenceId = $secondaire ? $periode->id : $ligne['sequence_id'];
                    $volet = $secondaire ? 'unique' : $ligne['composante'];
                    $existante = $existantes->get("{$ligne['eleve_id']}:{$sequenceId}:{$volet}");
                    $actuelle = $maternelle ? $existante?->appreciation_id : $existante?->valeur;
                    $nouvelle = $maternelle ? $ligne['appreciation_id'] : $ligne['valeur'];
                    if ($actuelle !== null && (float) $actuelle !== (float) $nouvelle) {
                        $conflits[] = [
                            'eleve_id' => $ligne['eleve_id'], 'nom_complet' => $noms->get($ligne['eleve_id']),
                            'classe' => $affectation->classe->nom,
                            'unite' => $secondaire ? $affectation->matiere->nom : $affectation->competence->label_fr,
                            'composante' => $volet,
                            'valeur_actuelle' => $maternelle ? $appreciations->get($actuelle) : (float) $actuelle,
                            'valeur_fichier' => $maternelle ? $appreciations->get($nouvelle) : $nouvelle,
                        ];
                    }
                    $ecritures[] = [
                        ['eleve_id' => $ligne['eleve_id'], $champ => $affectation->id, 'sequence_id' => $sequenceId, 'composante' => $volet],
                        ['valeur' => $maternelle ? null : $nouvelle, 'appreciation_id' => $maternelle ? $nouvelle : null,
                            'saisi_par' => $user->personnel?->id],
                    ];
                }
            }
            $resultat = ['imported' => 0, 'failed' => count($erreurs), 'errors' => $erreurs,
                'confirmation_requise' => $conflits !== [] && ! $confirmer, 'conflits' => $conflits];
            if ($resultat['confirmation_requise']) {
                return $resultat;
            }
            foreach ($ecritures as [$cle, $valeurs]) {
                Note::updateOrCreate($cle, $valeurs);
                $resultat['imported']++;
            }
            $resultat['conflits'] = [];

            return $resultat;
        });
    }
}
