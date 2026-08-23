<?php

namespace App\Services;

use App\Models\AnneeScolaire;
use App\Models\Appreciation;
use App\Models\ClasseCompetence;
use App\Models\Note;
use App\Models\Sequence;
use App\Models\Trimestre;
use App\Models\User;
use Illuminate\Support\Collection;

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
        $competence = $classeCompetence->competence;
        $composantes = $competence->volets();
        $sequences = $trimestre->sequencesRetenues();
        $maternelle = $this->parAppreciation($classeCompetence);

        $notes = Note::where('classe_competence_id', $classeCompetence->id)
            ->whereIn('sequence_id', $sequences->pluck('id'))
            ->get();

        $lignes = $classeCompetence->classe->eleves()->where('statut', 'actif')->orderBy('nom_complet')->get()
            ->map(function ($eleve) use ($notes, $composantes, $sequences) {
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
                ];
            });

        return [
            'mode' => $maternelle ? 'appreciation' : 'note',
            'composantes' => $composantes,
            'sequences' => $sequences->map(fn ($s) => ['id' => $s->id, 'libelle' => $s->libelle])->values()->all(),
            'bareme' => (int) ($competence->notation ?? 20),
            'repartition' => $competence->repartitionVolets(),
            'appreciations' => $maternelle ? $this->referentiel($classeCompetence) : [],
            'lignes' => $lignes,
        ];
    }

    /** La maternelle coche un visage ; le primaire saisit un chiffre. */
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
        $composantesValides = array_flip($classeCompetence->competence->volets());
        $anneeActive = AnneeScolaire::where('school_id', $classeCompetence->classe->school_id)->where('is_active', true)->first();
        $sequenceIdsValides = $anneeActive
            ? Sequence::whereHas(
                'trimestre',
                fn ($q) => $q->where('annee_scolaire_id', $anneeActive->id)
            )->pluck('id')->flip()
            : collect();

        return $this->transaction(function () use ($classeCompetence, $notes, $personnelId, $eleveIdsValides, $composantesValides, $sequenceIdsValides, $maternelle, $appreciationsValides) {
            $count = 0;

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
                $count++;
            }

            return $count;
        });
    }

    /**
     * Au primaire, c'est le titulaire de la classe qui saisit toutes les
     * compétences — contrairement au secondaire où chaque enseignant ne saisit
     * que sa matière.
     */
    public function peutSaisir(User $user, ClasseCompetence $classeCompetence): bool
    {
        if ($user->hasAnyRole(['super_admin', 'admin_etablissement', 'censeur_sg'])) {
            return true;
        }

        $personnelId = $user->personnel?->id;

        if ($personnelId === null) {
            return false;
        }

        return $classeCompetence->classe->titulaire_id === $personnelId
            || $classeCompetence->personnel_id === $personnelId;
    }
}
