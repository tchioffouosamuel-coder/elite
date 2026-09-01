<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Eleve;
use App\Models\Note;
use App\Models\Trimestre;
use App\Services\MoyennePrimaireService;
use App\Services\MoyenneService;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Vue JSON des notes d'un élève, groupées par séquence et par matière (ou
 * compétence au primaire), avec la moyenne et le rang de chaque ligne ainsi
 * que la moyenne générale et le rang général — le même calcul que le
 * bulletin ({@see BulletinService}, {@see BulletinPrimaireService}), mais
 * allégé pour un affichage écran plutôt qu'un document PDF.
 */
class NoteEleveController extends Controller
{
    public function __construct(
        private readonly MoyenneService $moyennes,
        private readonly MoyennePrimaireService $moyennesPrimaire,
    ) {}

    public function index(Request $request, int $eleveId): JsonResponse
    {
        $eleve = Eleve::forSchool(Tenant::schoolIds())->with('classe.school')->findOrFail($eleveId);

        if (! $eleve->classe) {
            return ApiResponse::error("Cet élève n'est affecté à aucune classe.", 422);
        }

        $school = $eleve->classe->school;

        $trimestre = $this->trimestre($request, $school->id);
        $sequences = $trimestre->sequencesRetenues();

        $donnees = $school->estSecondaire()
            ? $this->parMatiere($eleve, $trimestre, $sequences)
            : $this->parCompetence($eleve, $trimestre, $sequences);

        return ApiResponse::success([
            'eleve' => ['id' => $eleve->id, 'nom_complet' => $eleve->nom_complet],
            'trimestre' => ['id' => $trimestre->id, 'libelle' => $trimestre->libelle],
            'sequences' => $sequences->map(fn ($s) => ['id' => $s->id, 'libelle' => $s->libelle])->values(),
            ...$donnees,
        ]);
    }

    /** @return array{matieres: Collection, moyenne_generale: ?float, rang_general: ?int} */
    private function parMatiere(Eleve $eleve, Trimestre $trimestre, Collection $sequences): array
    {
        $affectations = $eleve->classe->classeMatieres()->where('statut', 'actif')->with('matiere')->get();

        $notes = Note::where('eleve_id', $eleve->id)
            ->whereIn('classe_matiere_id', $affectations->pluck('id'))
            ->whereIn('sequence_id', $sequences->pluck('id'))
            ->get()
            ->groupBy('classe_matiere_id');

        $classementsMatiere = $affectations->mapWithKeys(
            fn ($cm) => [$cm->id => $this->moyennes->classementMatiere($cm, $trimestre)]
        );

        $matieres = $affectations->map(function ($cm) use ($eleve, $trimestre, $sequences, $notes, $classementsMatiere) {
            $notesMatiere = ($notes->get($cm->id) ?? collect())->keyBy('sequence_id');
            $rang = $classementsMatiere->get($cm->id)?->firstWhere('eleve_id', $eleve->id)['rang'] ?? null;

            return [
                'matiere_id' => $cm->matiere_id,
                'matiere' => $cm->matiere->nom,
                'abreviation' => $cm->matiere->abbreviation,
                'coefficient' => (float) $cm->coefficient,
                'notes' => $sequences->map(fn ($s) => [
                    'sequence_id' => $s->id,
                    'libelle' => $s->libelle,
                    'valeur' => $notesMatiere->get($s->id)?->valeur !== null
                        ? (float) $notesMatiere->get($s->id)->valeur
                        : null,
                ])->values(),
                'moyenne' => $this->moyennes->moyenneMatiereEleve($eleve, $cm, $trimestre),
                'rang' => $rang,
            ];
        })->values();

        $general = $this->moyennes->moyenneGeneraleEleve($eleve, $trimestre);
        $rangGeneral = $this->moyennes->classementGeneral($eleve->classe, $trimestre)
            ->first(fn ($r) => $r['eleve']->id === $eleve->id)['rang'] ?? null;

        return [
            'matieres' => $matieres,
            'moyenne_generale' => $general['moyenne'],
            'rang_general' => $rangGeneral,
        ];
    }

    /** @return array{competences: Collection, moyenne_generale: ?float, rang_general: ?int} */
    private function parCompetence(Eleve $eleve, Trimestre $trimestre, Collection $sequences): array
    {
        $affectations = $eleve->classe->classeCompetences()->where('statut', 'actif')->with('competence')->get();

        $classementsCompetence = $affectations->mapWithKeys(
            fn ($cc) => [$cc->id => $this->moyennesPrimaire->classementCompetence($cc, $trimestre)]
        );

        $competences = $affectations->map(function ($cc) use ($eleve, $trimestre, $sequences, $classementsCompetence) {
            // `notation` nul signale une compétence de maternelle, évaluée
            // par appréciation (émoji/couleur/libellé) plutôt que par une
            // note chiffrée sur barème — cf. NotePrimaireService::
            // parAppreciation(), même distinction côté saisie. Le moteur de
            // moyennes (MoyennePrimaireService::noteCompetenceEleve) ne lit
            // que la colonne `valeur` : il renvoie `note: null` pour ces
            // compétences-là sans jamais planter, mais l'écran a besoin de
            // l'appréciation elle-même, pas juste de son absence de note.
            if ($cc->competence->notation === null) {
                return $this->competenceAppreciation($eleve, $cc, $sequences);
            }

            $resultat = $this->moyennesPrimaire->noteCompetenceEleve($eleve, $cc, $trimestre);
            $rang = $classementsCompetence->get($cc->id)?->firstWhere('eleve_id', $eleve->id)['rang'] ?? null;

            return [
                'competence_id' => $cc->competence_id,
                'competence' => $cc->competence->label_fr,
                'abreviation' => $cc->competence->abbreviation,
                'mode' => 'note',
                'bareme' => $resultat['bareme'],
                'notes' => $sequences->map(fn ($s) => [
                    'sequence_id' => $s->id,
                    'libelle' => $s->libelle,
                    'total' => $resultat['totaux_sequences'][$s->id] ?? null,
                ])->values(),
                'moyenne' => $resultat['note'],
                'rang' => $rang,
            ];
        })->values();

        $general = $this->moyennesPrimaire->moyenneGeneraleEleve($eleve, $trimestre);
        $rangGeneral = $this->moyennesPrimaire->classementGeneral($eleve->classe, $trimestre)
            ->first(fn ($r) => $r['eleve']->id === $eleve->id)['rang'] ?? null;

        return [
            'competences' => $competences,
            'moyenne_generale' => $general['moyenne'],
            'rang_general' => $rangGeneral,
        ];
    }

    /**
     * Ligne d'une compétence de maternelle : l'appréciation choisie par
     * séquence, pas de moyenne ni de rang (ces notions supposent un barème
     * numérique — cf. `MoyennePrimaireService::moyenneGeneraleEleve`, qui
     * exclut déjà ces compétences du calcul de la moyenne générale).
     *
     * @return array{competence_id: int, competence: string, abreviation: ?string, mode: string, bareme: null, notes: Collection, moyenne: null, rang: null}
     */
    private function competenceAppreciation(Eleve $eleve, $classeCompetence, Collection $sequences): array
    {
        $notesParSequence = Note::where('eleve_id', $eleve->id)
            ->where('classe_competence_id', $classeCompetence->id)
            ->whereIn('sequence_id', $sequences->pluck('id'))
            ->whereNotNull('appreciation_id')
            ->with('appreciation')
            ->get()
            ->keyBy('sequence_id');

        return [
            'competence_id' => $classeCompetence->competence_id,
            'competence' => $classeCompetence->competence->label_fr,
            'abreviation' => $classeCompetence->competence->abbreviation,
            'mode' => 'appreciation',
            'bareme' => null,
            'notes' => $sequences->map(function ($s) use ($notesParSequence) {
                $appreciation = $notesParSequence->get($s->id)?->appreciation;

                return [
                    'sequence_id' => $s->id,
                    'libelle' => $s->libelle,
                    'appreciation' => $appreciation ? [
                        'id' => $appreciation->id,
                        'label_fr' => $appreciation->label_fr,
                        'emoji' => $appreciation->emoji,
                        'couleur' => $appreciation->couleur,
                    ] : null,
                ];
            })->values(),
            'moyenne' => null,
            'rang' => null,
        ];
    }

    private function trimestre(Request $request, int $schoolId): Trimestre
    {
        $query = Trimestre::whereHas('anneeScolaire', fn ($q) => $q->where('school_id', $schoolId));

        return ($id = $request->integer('trimestre_id'))
            ? $query->findOrFail($id)
            : $query->where('is_active', true)->firstOrFail();
    }
}
