<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\ClasseCompetence;
use App\Models\ClasseMatiere;
use App\Models\Eleve;
use App\Models\Personnel;
use App\Models\Preinscription;
use App\Models\School;
use App\Models\Sequence;
use App\Models\User;
use App\Support\Perimetre;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class DashboardService extends BaseService
{
    public function __construct(
        private readonly NoteService $notes,
        private readonly NotePrimaireService $notesPrimaire,
        private readonly ProgressionService $progression,
        private readonly PreinscriptionService $preinscriptions,
    ) {}

    /**
     * Un enseignant ne gère que les classes où il intervient (titulariat ou
     * affectation matière) : lui montrer les effectifs de tout l'établissement,
     * ou le journal d'activité de l'école, exposerait des données hors de son
     * périmètre. Les autres profils (administration) gardent le tableau de
     * bord d'établissement.
     */
    /** @param int|array<int> $schoolId */
    public function stats(int|array $schoolId, User $user): array
    {
        if ($user->estEnseignant()) {
            $classeIds = (new Perimetre($user))->classesEnseignees();
            $classes = Classe::forSchool($schoolId)->whereIn('id', $classeIds)->get();

            if ($classes->isNotEmpty()) {
                return $this->statsClasse($schoolId, $classes, $user);
            }
        }

        // Mode agrégé sur plusieurs écoles (super admin, "Toutes les écoles") :
        // une ligne par école plutôt qu'un total qui mélangerait des
        // établissements de tailles et de cycles différents.
        if (is_array($schoolId) && count($schoolId) > 1) {
            return $this->statsComplexe($schoolId);
        }

        return $this->statsEcole($schoolId);
    }

    /**
     * Une ligne par école du périmètre agrégé, chacune calculée par
     * `statsEcole()` sur son seul id — l'activité récente reste, elle,
     * commune à tout le complexe (cf. `statsEcole()` en mode mono-école).
     *
     * @param  list<int>  $schoolIds
     */
    private function statsComplexe(array $schoolIds): array
    {
        $schools = School::whereIn('id', $schoolIds)->orderBy('name')->get(['id', 'name', 'type']);

        $ecoles = $schools->map(function (School $school) {
            $stats = $this->statsEcole($school->id);
            unset($stats['scope']);

            return ['id' => $school->id, 'nom' => $school->name, 'type' => $school->type] + $stats;
        })->values();

        $activiteRecente = ActivityLog::forSchool($schoolIds)
            ->latest('created_at')->limit(6)->get()
            ->map(fn(ActivityLog $log) => $this->formaterLogActivite($log));

        return [
            'scope' => 'complexe',
            'ecoles' => $ecoles,
            'activite_recente' => $activiteRecente,
        ];
    }

    /**
     * En mode agrégé (super admin, plusieurs écoles), les effectifs se
     * somment sur tout le périmètre accessible ; l'année scolaire active
     * affichée reste celle de la première école trouvée — chaque école du
     * complexe gère la sienne, il n'y en a pas "une" à l'échelle agrégée.
     *
     * @param  int|array<int>  $schoolId
     */
    private function statsEcole(int|array $schoolId): array
    {
        // Chaque école a sa propre année active ; en mode agrégé (plusieurs
        // écoles), le libellé affiché reste celui de la première trouvée.
        $anneeActive = AnneeScolaire::whereIn('school_id', (array) $schoolId)->where('is_active', true)->first();
        $classesQuery = Classe::forSchool($schoolId);

        // Les indicateurs d'effectifs portent uniquement sur les élèves ayant
        // confirmé leur inscription pour l'année active.
        $anciensTotal = $this->preinscriptions->anciensEleves($schoolId)->count();
        $ancienesNonReinscrits = $this->preinscriptions->listeAnciensNonReinscrits($schoolId)->count();
        $ancienesReinscrits = $anciensTotal - $ancienesNonReinscrits;
        $nouveauxElevesIds = Preinscription::forSchool($schoolId)
            ->where('type', 'nouveau')->where('statut', 'validee')
            ->whereHas('anneeScolaire', fn($q) => $q->where('is_active', true))
            ->pluck('eleve_id');
        $elevesInscritsIds = $this->preinscriptions->listeAnciensReinscrits($schoolId)
            ->pluck('id')
            ->merge($nouveauxElevesIds)
            ->filter()
            ->unique()
            ->values();
        $elevesInscrits = Eleve::forSchool($schoolId)
            ->where('statut', 'actif')
            ->whereIn('id', $elevesInscritsIds);

        $totalEleves = (clone $elevesInscrits)->count();
        $totalClasses = (clone $classesQuery)->count();
        $totalPersonnel = Personnel::forSchool($schoolId)->where('statut', 'actif')->count();
        $totalEnseignants = Personnel::forSchool($schoolId)->where('statut', 'actif')
            ->whereHas('fonctionReference', fn($q) => $q
                ->whereRaw('LOWER(label_fr) = ?', ['enseignant'])
                ->orWhereRaw('LOWER(label_en) = ?', ['teacher']))
            ->count();

        $parGenre = (clone $elevesInscrits)
            ->selectRaw('sexe, count(*) as total')->groupBy('sexe')->pluck('total', 'sexe');
        $filles = (int) ($parGenre['F'] ?? 0);
        $garcons = (int) ($parGenre['M'] ?? 0);

        // Par classe ET par genre, pour détailler garçons/filles/total sous
        // chaque classe du classement (widget « Classes les plus nombreuses »).
        $genrePartClasse = (clone $elevesInscrits)
            ->selectRaw('classe_id, sexe, count(*) as total')
            ->groupBy('classe_id', 'sexe')
            ->get()
            ->groupBy('classe_id');
        $effectifsParClasse = $genrePartClasse->map(fn($lignes) => (int) $lignes->sum('total'));
        $classementClasses = (clone $classesQuery)->whereIn('id', $effectifsParClasse->keys())
            ->get(['id', 'nom'])
            ->map(function ($c) use ($genrePartClasse, $effectifsParClasse) {
                $lignes = $genrePartClasse->get($c->id, collect());

                return [
                    'classe' => $c->nom,
                    'effectif' => (int) $effectifsParClasse[$c->id],
                    'garcons' => (int) $lignes->firstWhere('sexe', 'M')?->total,
                    'filles' => (int) $lignes->firstWhere('sexe', 'F')?->total,
                ];
            })
            ->sortByDesc('effectif')->values();
        $topClasses = $classementClasses->take(5)->values();

        // Journal réel des connexions et actions marquantes (qui a fait quoi),
        // pas une reconstruction a posteriori à partir des dates de création —
        // cf. cahier des charges §5.5.
        $activiteRecente = ActivityLog::forSchool($schoolId)
            ->latest('created_at')->limit(6)->get()
            ->map(fn(ActivityLog $log) => $this->formaterLogActivite($log));

        return [
            'scope' => 'ecole',
            'annee_scolaire_active' => $anneeActive?->libelle,
            'effectifs' => [
                'eleves' => $totalEleves,
                'personnel' => $totalPersonnel,
                'enseignants' => $totalEnseignants,
                'classes' => $totalClasses,
            ],
            'repartition_genre' => ['garcons' => $garcons, 'filles' => $filles],
            'top_classes' => $topClasses,
            // Classement complet, derrière le « Voir plus » du widget.
            'classement_classes' => $classementClasses,
            'indicateurs' => [
                'taux_filles' => $totalEleves > 0 ? round($filles / $totalEleves * 100, 1) : 0,
                'eleves_par_classe_moyenne' => $totalClasses > 0 ? round($totalEleves / $totalClasses, 1) : 0,
            ],
            'activite_recente' => $activiteRecente,
            'reinscription' => [
                'anciens_total' => $anciensTotal,
                'anciens_reinscrits' => $ancienesReinscrits,
                'taux_reinscription' => $anciensTotal > 0 ? round($ancienesReinscrits / $anciensTotal * 100, 1) : 0,
                'nouveaux_eleves' => $nouveauxElevesIds->filter()->unique()->count(),
            ],
        ];
    }

    /**
     * @param  int|array<int>  $schoolId
     * @param  Collection<int, Classe>  $classes  Les classes où l'enseignant intervient (une ou plusieurs).
     */
    private function statsClasse(int|array $schoolId, Collection $classes, User $user): array
    {
        $classeIds = $classes->pluck('id')->all();
        $premiere = $classes->first();

        $eleves = Eleve::forSchool($schoolId)->whereIn('classe_id', $classeIds)->where('statut', 'actif');

        $totalEleves = (clone $eleves)->count();
        $parGenre = (clone $eleves)->selectRaw('sexe, count(*) as total')->groupBy('sexe')->pluck('total', 'sexe');
        $filles = (int) ($parGenre['F'] ?? 0);
        $garcons = (int) ($parGenre['M'] ?? 0);

        $totalMatieres = ClasseMatiere::whereIn('classe_id', $classeIds)->where('statut', 'actif')->count();

        $activiteRecente = Eleve::forSchool($schoolId)->whereIn('classe_id', $classeIds)->latest()->limit(5)->get()
            ->map(fn($e) => ['type' => 'eleve', 'libelle' => "Inscription de {$e->nom_complet}", 'date' => $e->created_at->toIso8601String()])
            ->values();

        [$tauxRemplissageNotes, $tauxProgression] = $this->indicateursPedagogiques($schoolId, $classeIds, $user);

        return [
            'scope' => 'classe',
            'classe' => [
                'id' => $premiere->id,
                'nom' => $classes->count() === 1 ? $premiere->nom : $classes->pluck('nom')->implode(', '),
            ],
            'annee_scolaire_active' => AnneeScolaire::where('school_id', $premiere->school_id)->where('is_active', true)->value('libelle'),
            'effectifs' => [
                'eleves' => $totalEleves,
                'matieres' => $totalMatieres,
                'classes' => $classes->count(),
            ],
            'repartition_genre' => ['garcons' => $garcons, 'filles' => $filles],
            'indicateurs' => [
                'taux_filles' => $totalEleves > 0 ? round($filles / $totalEleves * 100, 1) : 0,
                'taux_remplissage_notes' => $tauxRemplissageNotes,
                'taux_progression' => $tauxProgression,
            ],
            'activite_recente' => $activiteRecente,
        ];
    }

    /**
     * Moyenne du remplissage des notes (séquence active) et de l'avancement
     * du programme, sur les seules affectations de l'agent connecté — pas sur
     * toute la classe, dont d'autres enseignants peuvent avoir la charge.
     *
     * @param  int|array<int>  $schoolId
     * @param  list<int>  $classeIds
     * @return array{0: ?int, 1: ?int}
     */
    private function indicateursPedagogiques(int|array $schoolId, array $classeIds, User $user): array
    {
        $personnelId = $user->personnel?->id;

        if ($personnelId === null || $classeIds === []) {
            return [null, null];
        }

        $mesAffectations = $this->mesAffectations($classeIds, $personnelId);

        if ($mesAffectations->isEmpty()) {
            return [null, null];
        }

        $tauxProgression = (int) round($mesAffectations->avg(fn(ClasseMatiere $cm) => $this->progression->tauxAffectation($cm)['taux']));

        $sequenceActive = $this->sequenceActive($schoolId);

        if ($sequenceActive === null) {
            return [null, $tauxProgression];
        }

        // Le primaire et la maternelle notent la compétence, pas la matière
        // que `ClasseMatiere` installe sous elle : le remplissage s'y lit sur
        // `ClasseCompetence`, où vivent réellement les notes de ce cycle.
        $primaireOuMaternelle = ! (Classe::find($classeIds[0])?->school?->estSecondaire() ?? true);

        if ($primaireOuMaternelle) {
            $mesCompetences = $this->mesCompetences($classeIds, $personnelId);

            $tauxRemplissageNotes = $mesCompetences->isEmpty()
                ? null
                : (int) round($mesCompetences->avg(fn(ClasseCompetence $cc) => $this->notesPrimaire->tauxRemplissage($cc, $sequenceActive)));
        } else {
            $tauxRemplissageNotes = (int) round($mesAffectations->avg(fn(ClasseMatiere $cm) => $this->notes->tauxRemplissage($cm, $sequenceActive->id)));
        }

        return [$tauxRemplissageNotes, $tauxProgression];
    }

    /**
     * Détail, matière par matière (et compétence par compétence au
     * primaire/maternelle), des deux taux que résume la carte « Indicateurs
     * pédagogiques » du tableau de bord enseignant — mêmes affectations et
     * même séquence active que {@see indicateursPedagogiques()}, pour que la
     * moyenne affichée sur la carte se retrouve dans ce détail.
     *
     * @param  int|array<int>  $schoolId
     * @return array{sequence: ?string, matieres: list<array<string, mixed>>, competences: list<array<string, mixed>>}
     */
    public function detailIndicateursPedagogiques(int|array $schoolId, User $user): array
    {
        $personnelId = $user->personnel?->id;
        $classeIds = $user->estEnseignant() ? (new Perimetre($user))->classesEnseignees() : [];

        if ($personnelId === null || $classeIds === []) {
            return ['sequence' => null, 'matieres' => [], 'competences' => []];
        }

        $sequenceActive = $this->sequenceActive($schoolId);

        $matieres = $this->mesAffectations($classeIds, $personnelId)
            ->load(['classe.school', 'matiere'])
            ->map(function (ClasseMatiere $cm) use ($sequenceActive) {
                $progression = $this->progression->tauxAffectation($cm);
                $secondaire = $cm->classe?->school?->estSecondaire() ?? true;

                return [
                    'classe_matiere_id' => $cm->id,
                    'classe' => $cm->classe?->nom,
                    'matiere' => $cm->matiere?->nom,
                    'lecons' => $progression['lecons'],
                    'lecons_traitees' => $progression['traitees'],
                    'taux_progression' => (int) round($progression['taux']),
                    // Au primaire/maternelle, les notes vivent sur la
                    // compétence : le remplissage figure dans `competences`.
                    'taux_remplissage_notes' => $sequenceActive !== null && $secondaire
                        ? $this->notes->tauxRemplissage($cm, $sequenceActive->id)
                        : null,
                ];
            })
            ->sortBy([['classe', 'asc'], ['matiere', 'asc']])
            ->values()
            ->all();

        $competences = $sequenceActive === null ? [] : $this->mesCompetences($classeIds, $personnelId)
            ->load(['classe', 'competence'])
            ->map(fn(ClasseCompetence $cc) => [
                'classe_competence_id' => $cc->id,
                'classe' => $cc->classe?->nom,
                'competence' => app()->getLocale() === 'en' && $cc->competence?->label_en
                    ? $cc->competence->label_en
                    : $cc->competence?->label_fr,
                'taux_remplissage_notes' => $this->notesPrimaire->tauxRemplissage($cc, $sequenceActive),
            ])
            ->sortBy([['classe', 'asc'], ['competence', 'asc']])
            ->values()
            ->all();

        return [
            'sequence' => $sequenceActive?->libelle,
            'matieres' => $matieres,
            'competences' => $competences,
        ];
    }

    /**
     * Affectations de l'agent : ses matières, plus toutes celles des classes
     * dont il est titulaire.
     *
     * @param  list<int>  $classeIds
     * @return Collection<int, ClasseMatiere>
     */
    private function mesAffectations(array $classeIds, int $personnelId): Collection
    {
        return ClasseMatiere::whereIn('classe_id', $classeIds)
            ->where('statut', 'actif')
            ->where(fn($q) => $q
                ->where('personnel_id', $personnelId)
                ->orWhereHas('classe', fn($c) => $c->where('titulaire_id', $personnelId)))
            ->get();
    }

    /**
     * Compétences notées par l'agent : seul le titulaire saisit les notes de
     * compétence (plus de `personnel_id` propre à `classe_competences`).
     *
     * @param  list<int>  $classeIds
     * @return Collection<int, ClasseCompetence>
     */
    private function mesCompetences(array $classeIds, int $personnelId): Collection
    {
        return ClasseCompetence::whereIn('classe_id', $classeIds)
            ->where('statut', 'actif')
            ->whereHas('classe', fn($c) => $c->where('titulaire_id', $personnelId))
            ->get();
    }

    /** @param int|array<int> $schoolId */
    private function sequenceActive(int|array $schoolId): ?Sequence
    {
        return Sequence::whereHas(
            'trimestre',
            fn($q) => $q->where('is_active', true)->whereHas('anneeScolaire', fn($aq) => $aq->whereIn('school_id', (array) $schoolId))
        )->first();
    }

    /** @return array{type: string, libelle: string, date: string} */
    private function formaterLogActivite(ActivityLog $log): array
    {
        $qui = $log->causer_role ? "{$log->causer_nom} — {$log->causer_role}" : $log->causer_nom;

        return ['type' => $log->action, 'libelle' => "{$qui} : {$log->description}", 'date' => $log->created_at->toIso8601String()];
    }

    /**
     * Liste les anciens élèves qui ont confirmé leur présence pour l'année active.
     *
     * @param  int|array<int>  $schoolId
     * @return Collection<int, Eleve>
     */
    public function listeAnciensReinscrits(int|array $schoolId): Collection
    {
        return $this->preinscriptions->listeAnciensReinscrits($schoolId);
    }

    /**
     * Journal complet, paginé — derrière le « Voir plus » de la carte
     * Activité récente qui n'en affiche qu'un aperçu, et derrière l'onglet
     * Activité d'une fiche personnel quand `$personnelId` est fourni.
     *
     * @param  int|array<int>  $schoolId
     */
    public function activiteRecentePaginee(int|array $schoolId, int $perPage = 25, ?int $personnelId = null): LengthAwarePaginator
    {
        $requete = ActivityLog::forSchool($schoolId)->latest('created_at');

        if ($personnelId !== null) {
            // Un agent sans compte de connexion n'a jamais pu produire de ligne
            // de journal : userId reste `null` et la pagination retombe sur une
            // page vide plutôt que de planter ou de montrer tout le monde.
            $userId = Personnel::whereIn('school_id', (array) $schoolId)->whereKey($personnelId)->value('user_id');
            $requete->where('user_id', $userId);
        }

        return $requete->paginate(max(1, min($perPage, 100)))
            ->through(fn(ActivityLog $log) => $this->formaterLogActivite($log));
    }
}
