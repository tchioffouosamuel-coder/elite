<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\ClasseCompetence;
use App\Models\ClasseMatiere;
use App\Models\Eleve;
use App\Models\Personnel;
use App\Models\Sequence;
use App\Models\User;
use App\Support\Perimetre;
use Illuminate\Support\Collection;

class DashboardService extends BaseService
{
    public function __construct(
        private readonly NoteService $notes,
        private readonly NotePrimaireService $notesPrimaire,
        private readonly ProgressionService $progression,
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

        return $this->statsEcole($schoolId);
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

        $totalEleves = Eleve::forSchool($schoolId)->where('statut', 'actif')->count();
        $totalClasses = (clone $classesQuery)->count();
        $totalPersonnel = Personnel::forSchool($schoolId)->where('statut', 'actif')->count();
        $totalEnseignants = Personnel::forSchool($schoolId)->where('statut', 'actif')
            ->whereHas('fonctionReference', fn ($q) => $q
                ->whereRaw('LOWER(label_fr) = ?', ['enseignant'])
                ->orWhereRaw('LOWER(label_en) = ?', ['teacher']))
            ->count();

        $parGenre = Eleve::forSchool($schoolId)->where('statut', 'actif')
            ->selectRaw('sexe, count(*) as total')->groupBy('sexe')->pluck('total', 'sexe');
        $filles = (int) ($parGenre['F'] ?? 0);
        $garcons = (int) ($parGenre['M'] ?? 0);

        $topClasses = (clone $classesQuery)->withCount('eleves')
            ->orderByDesc('eleves_count')->limit(5)->get(['id', 'nom'])
            ->map(fn ($c) => ['classe' => $c->nom, 'effectif' => $c->eleves_count]);

        // Journal réel des connexions et actions marquantes (qui a fait quoi),
        // pas une reconstruction a posteriori à partir des dates de création —
        // cf. cahier des charges §5.5.
        $activiteRecente = ActivityLog::forSchool($schoolId)
            ->latest('created_at')->limit(6)->get()
            ->map(function (ActivityLog $log) {
                $qui = $log->causer_role ? "{$log->causer_nom} — {$log->causer_role}" : $log->causer_nom;

                return ['type' => $log->action, 'libelle' => "{$qui} : {$log->description}", 'date' => $log->created_at->toIso8601String()];
            });

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
            'indicateurs' => [
                'taux_filles' => $totalEleves > 0 ? round($filles / $totalEleves * 100, 1) : 0,
                'eleves_par_classe_moyenne' => $totalClasses > 0 ? round($totalEleves / $totalClasses, 1) : 0,
            ],
            'activite_recente' => $activiteRecente,
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
            ->map(fn ($e) => ['type' => 'eleve', 'libelle' => "Inscription de {$e->nom_complet}", 'date' => $e->created_at->toIso8601String()])
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

        $mesAffectations = ClasseMatiere::whereIn('classe_id', $classeIds)
            ->where('statut', 'actif')
            ->where(fn ($q) => $q
                ->where('personnel_id', $personnelId)
                ->orWhereHas('classe', fn ($c) => $c->where('titulaire_id', $personnelId)))
            ->get();

        if ($mesAffectations->isEmpty()) {
            return [null, null];
        }

        $tauxProgression = (int) round($mesAffectations->avg(fn (ClasseMatiere $cm) => $this->progression->tauxAffectation($cm)['taux']));

        $sequenceActive = Sequence::whereHas(
            'trimestre',
            fn ($q) => $q->where('is_active', true)->whereHas('anneeScolaire', fn ($aq) => $aq->whereIn('school_id', (array) $schoolId))
        )->first();

        if ($sequenceActive === null) {
            return [null, $tauxProgression];
        }

        // Le primaire et la maternelle notent la compétence, pas la matière
        // que `ClasseMatiere` installe sous elle : le remplissage s'y lit sur
        // `ClasseCompetence`, où vivent réellement les notes de ce cycle.
        $primaireOuMaternelle = ! (Classe::find($classeIds[0])?->school?->estSecondaire() ?? true);

        if ($primaireOuMaternelle) {
            $mesCompetences = ClasseCompetence::whereIn('classe_id', $classeIds)
                ->where('statut', 'actif')
                ->where(fn ($q) => $q
                    ->where('personnel_id', $personnelId)
                    ->orWhereHas('classe', fn ($c) => $c->where('titulaire_id', $personnelId)))
                ->get();

            $tauxRemplissageNotes = $mesCompetences->isEmpty()
                ? null
                : (int) round($mesCompetences->avg(fn (ClasseCompetence $cc) => $this->notesPrimaire->tauxRemplissage($cc, $sequenceActive)));
        } else {
            $tauxRemplissageNotes = (int) round($mesAffectations->avg(fn (ClasseMatiere $cm) => $this->notes->tauxRemplissage($cm, $sequenceActive->id)));
        }

        return [$tauxRemplissageNotes, $tauxProgression];
    }
}
