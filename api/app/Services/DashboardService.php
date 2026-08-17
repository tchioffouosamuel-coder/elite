<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\Eleve;
use App\Models\Personnel;
use App\Models\User;

class DashboardService extends BaseService
{
    /**
     * Un titulaire de primaire/maternelle ne gère qu'une classe : lui montrer
     * les effectifs de tout l'établissement ne l'intéresse pas et exposerait
     * des données hors de son périmètre. Les autres profils (secondaire,
     * administration) gardent le tableau de bord d'établissement.
     */
    public function stats(int $schoolId, User $user): array
    {
        if ($user->estEnseignant()) {
            $classe = Classe::forSchool($schoolId)->where('titulaire_id', $user->personnel?->id)->first();

            if ($classe) {
                return $this->statsClasse($schoolId, $classe);
            }
        }

        return $this->statsEcole($schoolId);
    }

    private function statsEcole(int $schoolId): array
    {
        $anneeActive = AnneeScolaire::where('school_id', $schoolId)->where('is_active', true)->first();
        $classesQuery = Classe::forSchool($schoolId)->when($anneeActive, fn ($q) => $q->where('annee_scolaire_id', $anneeActive->id));

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

    private function statsClasse(int $schoolId, Classe $classe): array
    {
        $eleves = Eleve::forSchool($schoolId)->where('classe_id', $classe->id)->where('statut', 'actif');

        $totalEleves = (clone $eleves)->count();
        $parGenre = (clone $eleves)->selectRaw('sexe, count(*) as total')->groupBy('sexe')->pluck('total', 'sexe');
        $filles = (int) ($parGenre['F'] ?? 0);
        $garcons = (int) ($parGenre['M'] ?? 0);

        $totalMatieres = ClasseMatiere::where('classe_id', $classe->id)->where('statut', 'actif')->count();

        $activiteRecente = Eleve::forSchool($schoolId)->where('classe_id', $classe->id)->latest()->limit(5)->get()
            ->map(fn ($e) => ['type' => 'eleve', 'libelle' => "Inscription de {$e->nom_complet}", 'date' => $e->created_at->toIso8601String()])
            ->values();

        return [
            'scope' => 'classe',
            'classe' => ['id' => $classe->id, 'nom' => $classe->nom],
            'annee_scolaire_active' => $classe->anneeScolaire?->libelle,
            'effectifs' => [
                'eleves' => $totalEleves,
                'matieres' => $totalMatieres,
            ],
            'repartition_genre' => ['garcons' => $garcons, 'filles' => $filles],
            'indicateurs' => [
                'taux_filles' => $totalEleves > 0 ? round($filles / $totalEleves * 100, 1) : 0,
            ],
            'activite_recente' => $activiteRecente,
        ];
    }
}
