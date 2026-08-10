<?php

namespace App\Services;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Personnel;

class DashboardService extends BaseService
{
    public function stats(int $schoolId): array
    {
        $anneeActive = AnneeScolaire::where('school_id', $schoolId)->where('is_active', true)->first();
        $classesQuery = Classe::forSchool($schoolId)->when($anneeActive, fn ($q) => $q->where('annee_scolaire_id', $anneeActive->id));

        $totalEleves = Eleve::forSchool($schoolId)->where('statut', 'actif')->count();
        $totalClasses = (clone $classesQuery)->count();
        $totalPersonnel = Personnel::forSchool($schoolId)->where('statut', 'actif')->count();
        $totalEnseignants = Personnel::forSchool($schoolId)->where('statut', 'actif')->where('fonction', 'Enseignant')->count();

        $parGenre = Eleve::forSchool($schoolId)->where('statut', 'actif')
            ->selectRaw('sexe, count(*) as total')->groupBy('sexe')->pluck('total', 'sexe');
        $filles = (int) ($parGenre['F'] ?? 0);
        $garcons = (int) ($parGenre['M'] ?? 0);

        $topClasses = (clone $classesQuery)->withCount('eleves')
            ->orderByDesc('eleves_count')->limit(5)->get(['id', 'nom'])
            ->map(fn ($c) => ['classe' => $c->nom, 'effectif' => $c->eleves_count]);

        $activiteRecente = Eleve::forSchool($schoolId)->latest()->limit(3)->get()
            ->map(fn ($e) => ['type' => 'eleve', 'libelle' => "Inscription de {$e->nomComplet()}", 'date' => $e->created_at->toIso8601String()])
            ->concat(
                Personnel::forSchool($schoolId)->latest()->limit(3)->get()
                    ->map(fn ($p) => ['type' => 'personnel', 'libelle' => "Ajout de {$p->nomComplet()} ({$p->fonction})", 'date' => $p->created_at->toIso8601String()])
            )
            ->sortByDesc('date')->take(5)->values();

        return [
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
}
