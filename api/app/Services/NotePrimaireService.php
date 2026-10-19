<?php

namespace App\Services;

use App\Imports\NotePrimaireImport;
use App\Models\AnneeScolaire;
use App\Models\Appreciation;
use App\Models\ClasseCompetence;
use App\Models\Eleve;
use App\Models\Note;
use App\Models\ObservationEvaluation;
use App\Models\Sequence;
use App\Models\Trimestre;
use App\Models\User;
use App\Services\Concerns\DetecteConflitsRemplacement;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Saisie des notes du primaire et de la maternelle.
 *
 * Là où le secondaire saisit une note par élève et par séquence, archange
 * (`fr/users/marks.php`) présente la grille complète du trimestre : une colonne
 * par séquence, un bloc de colonnes par volet d'évaluation (oral, écrit,
 * savoir-être, et pratique si la compétence s'y prête).
 *
 * La grille porte sur une COMPÉTENCE et non sur une matière : c'est elle que le
 * bulletin note, et l'enseignant renseigne un bloc plutôt qu'une dizaine de
 * matières qui aboutiraient à la même ligne.
 *
 * Deux modes de saisie cohabitent, selon le cycle :
 *
 * - **primaire** : une note chiffrée par volet, plafonnée à la part du barème
 *   allouée à ce volet ;
 * - **maternelle** : un niveau d'appréciation choisi dans le référentiel de
 *   l'école (`appreciations`). On n'y note pas sur vingt des enfants de trois
 *   ans : le bulletin porte un visage colorié, et `valeur` reste vide.
 */
class NotePrimaireService extends BaseService
{
    use DetecteConflitsRemplacement;

    /** Libellés affichés des volets, pour les en-têtes du fichier Excel (export/modèle/import). */
    public const LIBELLES_COMPOSANTES = [
        'oral' => 'Oral',
        'ecrit' => 'Écrit',
        'savoir_etre' => 'Savoir-être',
        'pratique' => 'Pratique',
    ];

    public function __construct(private readonly AppreciationService $appreciations) {}

    /**
     * Grille de saisie d'une compétence pour tout le trimestre.
     *
     * `mode` dit à l'interface ce qu'elle doit afficher : un champ numérique
     * (« note ») ou les visages du référentiel (« appreciation »).
     *
     * @return array{
     *     mode: string,
     *     composantes: list<string>,
     *     sequences: list<array{id:int, libelle:string}>,
     *     bareme: int,
     *     repartition: array<string, float>,
     *     appreciations: list<array{id:int, label_fr:string, label_en: ?string, emoji: ?string, couleur:string, ordre:int}>,
     *     lignes: Collection<int, array{eleve_id:int, nom_complet:string, notes: array<string, array<int, ?float>>, appreciations: array<string, array<int, ?int>>}>
     * }
     */
    public function grille(ClasseCompetence $classeCompetence, Trimestre $trimestre): array
    {
        $composantes = $classeCompetence->voletsNotes();
        $sequences = $trimestre->sequencesRetenues();
        $maternelle = $this->parAppreciation($classeCompetence);

        $notes = Note::where('classe_competence_id', $classeCompetence->id)
            ->whereIn('sequence_id', $sequences->pluck('id'))
            ->get();
        $observations = ObservationEvaluation::where('classe_competence_id', $classeCompetence->id)
            ->where('trimestre_id', $trimestre->id)
            ->pluck('texte', 'eleve_id');

        $lignes = $classeCompetence->classe->eleves()->where('statut', 'actif')->inscritAnneeActive()->orderBy('nom_complet')->get()
            ->map(function ($eleve) use ($notes, $composantes, $sequences, $observations) {
                $parEleve = $notes->where('eleve_id', $eleve->id);

                $valeurs = [];
                $appreciations = [];

                foreach ($composantes as $composante) {
                    foreach ($sequences as $sequence) {
                        $note = $parEleve->first(
                            fn (Note $n) => $n->composante === $composante && $n->sequence_id === $sequence->id
                        );
                        $valeurs[$composante][$sequence->id] = $note?->valeur !== null ? (float) $note->valeur : null;
                        $appreciations[$composante][$sequence->id] = $note?->appreciation_id;
                    }
                }

                return [
                    'eleve_id' => $eleve->id,
                    'nom_complet' => $eleve->nom_complet,
                    'notes' => $valeurs,
                    'appreciations' => $appreciations,
                    'observation' => $observations->get($eleve->id),
                ];
            });

        return [
            'mode' => $maternelle ? 'appreciation' : 'note',
            'composantes' => $composantes,
            'sequences' => $sequences->map(fn ($s) => [
                'id' => $s->id, 'libelle' => $s->libelle, 'saisie_ouverte' => (bool) $s->saisie_ouverte,
            ])->values()->all(),
            'bareme' => $classeCompetence->bareme(),
            'repartition' => $classeCompetence->repartitionVolets(),
            'appreciations' => $maternelle ? $this->referentiel($classeCompetence) : [],
            'lignes' => $lignes,
        ];
    }

    /** La maternelle coche un visage ; le primaire saisit un chiffre. */
    /**
     * Une ligne par élève, une colonne « {Volet} S{ordre} » par volet × séquence
     * retenue du trimestre — colonnes alignées avec {@see NotePrimaireImport}
     * pour un aller-retour sans adaptation. En maternelle, la cellule porte le
     * libellé de l'appréciation (ex. « Acquis ») plutôt qu'un chiffre.
     *
     * @return Collection<int, array{matricule: ?string, nom_complet: string, cellules: array<string, mixed>}>
     */
    public function lignesExport(ClasseCompetence $classeCompetence, Trimestre $trimestre, bool $pourModele = false): Collection
    {
        $composantes = $classeCompetence->voletsNotes();
        $sequences = $trimestre->sequencesRetenues()->values();
        $maternelle = $this->parAppreciation($classeCompetence);

        $notes = $pourModele ? collect() : Note::where('classe_competence_id', $classeCompetence->id)
            ->whereIn('sequence_id', $sequences->pluck('id'))->get();

        $appreciationsParId = $maternelle
            ? Appreciation::forSchool((int) $classeCompetence->classe->school_id)->get()->keyBy('id')
            : collect();

        return $classeCompetence->classe->eleves()->where('statut', 'actif')->inscritAnneeActive()->orderBy('nom_complet')->get()
            ->map(function (Eleve $eleve) use ($notes, $composantes, $sequences, $maternelle, $appreciationsParId) {
                $parEleve = $notes->where('eleve_id', $eleve->id);
                $cellules = [];

                foreach ($composantes as $composante) {
                    foreach ($sequences as $i => $sequence) {
                        $note = $parEleve->first(fn (Note $n) => $n->composante === $composante && $n->sequence_id === $sequence->id);
                        $cle = self::colonne($composante, $i + 1);

                        $cellules[$cle] = $maternelle
                            ? ($note?->appreciation_id !== null ? $appreciationsParId->get($note->appreciation_id)?->label_fr : null)
                            : ($note?->valeur !== null ? (float) $note->valeur : null);
                    }
                }

                return ['matricule' => $eleve->matricule, 'nom_complet' => $eleve->nom_complet, 'cellules' => $cellules];
            });
    }

    /** Nom de colonne Excel d'un volet pour une séquence donnée (1-indexée) — partagé entre export et import. */
    public static function colonne(string $composante, int $ordreSequence): string
    {
        return (self::LIBELLES_COMPOSANTES[$composante] ?? ucfirst($composante))." S{$ordreSequence}";
    }

    /** Parseur commun à l'import d'une compétence et au classeur par enseignant. */
    public function preparerImport(ClasseCompetence $classeCompetence, Trimestre $trimestre): NotePrimaireImport
    {
        $eleves = $classeCompetence->classe->eleves()->whereNotNull('matricule')->pluck('id', 'matricule')
            ->mapWithKeys(fn ($id, $matricule) => [self::normaliser((string) $matricule) => $id]);
        $maternelle = $this->parAppreciation($classeCompetence);
        $appreciations = $maternelle
            ? Appreciation::forSchool((int) $classeCompetence->classe->school_id)->get()
                ->mapWithKeys(fn (Appreciation $a) => [self::normaliser($a->label_fr) => $a->id])
            : collect();

        return new NotePrimaireImport(
            $eleves, $classeCompetence->voletsNotes(), $trimestre->sequencesRetenues()->values(),
            $maternelle, $appreciations, $classeCompetence->repartitionVolets(),
        );
    }

    /**
     * Importe un fichier rempli depuis {@see lignesExport()} — même garde-fou
     * qu'au secondaire (cf. {@see NoteService::importFromExcel()}) : rien
     * n'est écrit si au moins une ligne valide remplacerait une note déjà
     * saisie et différente, tant que le client n'a pas confirmé.
     *
     * @return array{imported: int, failed: int, errors: list<string>, confirmation_requise: bool, conflits: array}
     */
    public function importerDepuisExcel(ClasseCompetence $classeCompetence, Trimestre $trimestre, ?User $user, UploadedFile $file, bool $confirmerRemplacement = false): array
    {
        $sequences = $trimestre->sequencesRetenues()->values();
        $maternelle = $this->parAppreciation($classeCompetence);
        $import = $this->preparerImport($classeCompetence, $trimestre);

        return $this->transaction(function () use ($import, $file, $user, $classeCompetence, $sequences, $confirmerRemplacement, $maternelle) {
            if ($user) {
                foreach ($sequences as $sequence) {
                    Sequence::with('trimestre.anneeScolaire')->lockForUpdate()->findOrFail($sequence->id)
                        ->verifierSaisieNotes($user, $classeCompetence->classe->school_id);
                }
            }

            Excel::import($import, $file);

            $existantes = Note::where('classe_competence_id', $classeCompetence->id)
                ->whereIn('sequence_id', $sequences->pluck('id'))
                ->get()->keyBy(fn (Note $n) => "{$n->eleve_id}:{$n->sequence_id}:{$n->composante}");
            $noms = Eleve::whereIn('id', collect($import->lignes)->pluck('eleve_id')->unique())->pluck('nom_complet', 'id');

            $conflits = $this->detecterConflits(
                $import->lignes,
                $existantes,
                fn (array $l) => "{$l['eleve_id']}:{$l['sequence_id']}:{$l['composante']}",
                fn (array $l, Note $existante) => $maternelle
                    ? ($existante->appreciation_id !== null && $existante->appreciation_id !== ($l['appreciation_id'] ?? null))
                    : ($existante->valeur !== null && (float) $existante->valeur !== ($l['valeur'] ?? null)),
                fn (array $l, Note $existante) => [
                    'eleve_id' => $l['eleve_id'],
                    'nom_complet' => $noms->get($l['eleve_id']),
                    'composante' => self::LIBELLES_COMPOSANTES[$l['composante']] ?? $l['composante'],
                    'valeur_actuelle' => $maternelle ? $existante->appreciation_id : (float) $existante->valeur,
                ],
            );

            if ($conflits !== [] && ! $confirmerRemplacement) {
                return [
                    'imported' => 0, 'failed' => count($import->erreurs), 'errors' => $import->erreurs,
                    'confirmation_requise' => true, 'conflits' => $conflits,
                ];
            }

            $personnelId = $user?->personnel?->id;
            $imported = 0;

            foreach ($import->lignes as $ligne) {
                $attributs = $maternelle
                    ? ['appreciation_id' => $ligne['appreciation_id'], 'valeur' => null, 'saisi_par' => $personnelId]
                    : ['valeur' => $ligne['valeur'], 'saisi_par' => $personnelId];

                Note::updateOrCreate([
                    'eleve_id' => $ligne['eleve_id'], 'classe_competence_id' => $classeCompetence->id,
                    'sequence_id' => $ligne['sequence_id'], 'composante' => $ligne['composante'],
                ], $attributs);
                $imported++;
            }

            return ['imported' => $imported, 'failed' => count($import->erreurs), 'errors' => $import->erreurs, 'confirmation_requise' => false, 'conflits' => []];
        });
    }

    /** Minuscule + accents retirés, pour un rapprochement insensible à la casse et aux accents (matricule, libellé d'appréciation). */
    public static function normaliser(string $valeur): string
    {
        return mb_strtolower(trim(Str::ascii($valeur)));
    }

    public function parAppreciation(ClasseCompetence $classeCompetence): bool
    {
        return (bool) $classeCompetence->classe?->school?->estMaternelle();
    }

    /** @return list<array<string, mixed>> */
    private function referentiel(ClasseCompetence $classeCompetence): array
    {
        $schoolId = $classeCompetence->classe?->school_id;

        if ($schoolId === null) {
            return [];
        }

        return $this->appreciations->referentiel($schoolId)
            ->map(fn (Appreciation $a) => [
                'id' => $a->id,
                'label_fr' => $a->label_fr,
                'label_en' => $a->label_en,
                'emoji' => $a->emoji,
                'couleur' => $a->couleur,
                'ordre' => $a->ordre,
            ])->values()->all();
    }

    /**
     * Enregistre la grille complète : chaque entrée cible un élève, une
     * séquence et un volet.
     *
     * @param  array<int, array{eleve_id:int, sequence_id:int, composante:string, valeur?: ?float, appreciation_id?: ?int}>  $notes
     */
    public function sauvegarderEnLot(ClasseCompetence $classeCompetence, array $notes, ?User $user): int
    {
        $personnelId = $user?->personnel?->id;
        $maternelle = $this->parAppreciation($classeCompetence);

        // Un identifiant d'appréciation venu d'une autre école n'a rien à faire
        // ici : on borne au référentiel de l'établissement de la classe.
        $appreciationsValides = $maternelle
            ? Appreciation::forSchool((int) $classeCompetence->classe->school_id)->pluck('id')->flip()
            : collect();
        $eleveIdsValides = $classeCompetence->classe->eleves()->pluck('id')->flip();
        $composantesValides = array_flip($classeCompetence->voletsNotes());
        $anneeActive = AnneeScolaire::where('school_id', $classeCompetence->classe->school_id)->where('is_active', true)->first();
        $sequenceIdsValides = $anneeActive
            ? Sequence::whereHas(
                'trimestre',
                fn ($q) => $q->where('annee_scolaire_id', $anneeActive->id)
            )->pluck('id')->flip()
            : collect();
        $sequenceTrimestreIds = Sequence::whereIn('id', collect($notes)->pluck('sequence_id')->unique())
            ->pluck('trimestre_id', 'id');

        return $this->transaction(function () use ($classeCompetence, $notes, $user, $personnelId, $eleveIdsValides, $composantesValides, $sequenceIdsValides, $sequenceTrimestreIds, $maternelle, $appreciationsValides) {
            if ($user) {
                $ids = collect($notes)->pluck('sequence_id')->unique();
                $sequences = Sequence::with('trimestre.anneeScolaire')->whereIn('id', $ids)
                    ->orderBy('id')->lockForUpdate()->get();
                abort_unless($sequences->count() === $ids->count(), 403, 'Séquence introuvable.');
                foreach ($sequences as $sequence) {
                    $sequence->verifierSaisieNotes($user, $classeCompetence->classe->school_id);
                }
            }
            $count = 0;
            $observationsEnregistrees = [];

            foreach ($notes as $row) {
                // Défense en profondeur : un élève d'une autre classe, une séquence
                // d'une autre année ou un volet que la compétence n'évalue pas sont
                // ignorés plutôt que d'être écrits en base.
                if (! $eleveIdsValides->has($row['eleve_id'])
                    || ! isset($composantesValides[$row['composante']])
                    || ! $sequenceIdsValides->has($row['sequence_id'])) {
                    continue;
                }

                if ($maternelle) {
                    $appreciationId = $row['appreciation_id'] ?? null;

                    if ($appreciationId !== null && ! $appreciationsValides->has($appreciationId)) {
                        continue;
                    }

                    // `valeur` est laissée vide : en maternelle, la case du
                    // bulletin porte une couleur, pas un nombre.
                    $attributs = [
                        'appreciation_id' => $appreciationId,
                        'valeur' => null,
                        'saisi_par' => $personnelId,
                    ];
                } else {
                    $attributs = ['valeur' => $row['valeur'] ?? null, 'saisi_par' => $personnelId];
                }

                Note::updateOrCreate(
                    [
                        'eleve_id' => $row['eleve_id'],
                        'classe_competence_id' => $classeCompetence->id,
                        'sequence_id' => $row['sequence_id'],
                        'composante' => $row['composante'],
                    ],
                    $attributs,
                );

                $trimestreId = $sequenceTrimestreIds->get($row['sequence_id']);
                $cleObservation = "{$row['eleve_id']}:{$trimestreId}";

                if (array_key_exists('observation', $row) && ! isset($observationsEnregistrees[$cleObservation])) {
                    ObservationEvaluation::updateOrCreate(
                        [
                            'eleve_id' => $row['eleve_id'],
                            'trimestre_id' => $trimestreId,
                            'classe_matiere_id' => null,
                            'classe_competence_id' => $classeCompetence->id,
                        ],
                        ['texte' => $row['observation'] === '' ? null : $row['observation']],
                    );
                    $observationsEnregistrees[$cleObservation] = true;
                }

                $count++;
            }

            return $count;
        });
    }

    /** Part des cellules (élève × volet) déjà renseignées pour cette compétence, sur une séquence. */
    public function tauxRemplissage(ClasseCompetence $classeCompetence, Sequence $sequence): int
    {
        $composantes = $classeCompetence->voletsNotes();
        $effectif = $classeCompetence->classe->eleves()->where('statut', 'actif')->inscritAnneeActive()->count();
        $total = $effectif * count($composantes);

        if ($total === 0) {
            return 0;
        }

        $maternelle = $this->parAppreciation($classeCompetence);

        $remplies = Note::where('classe_competence_id', $classeCompetence->id)
            ->where('sequence_id', $sequence->id)
            ->get()
            ->filter(fn (Note $n) => $maternelle ? $n->appreciation_id !== null : $n->valeur !== null)
            ->count();

        return (int) round(min($remplies, $total) / $total * 100);
    }

    /**
     * Au primaire, c'est le titulaire de la classe qui saisit toutes les
     * compétences — contrairement au secondaire où chaque enseignant ne saisit
     * que sa matière.
     */
    public function peutSaisir(User $user, ClasseCompetence $classeCompetence): bool
    {
        if ($user->hasAnyRole(['super_admin', 'admin_ecole', 'admin_college', 'censeur_sg'])) {
            return true;
        }

        $personnelId = $user->personnel?->id;

        if ($personnelId === null) {
            return false;
        }

        // Le titulaire tient toute sa classe ; l'enseignant nommé sur la
        // compétence tient celle-là, dans une classe qui n'est pas la sienne.
        return $classeCompetence->classe->titulaire_id === $personnelId
            || $classeCompetence->personnel_id === $personnelId;
    }
}
