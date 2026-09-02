<?php

namespace App\Services;

use App\Models\Seance;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Vue admin transverse de ce que fait déjà `MaJourneeService::heuresCouverture()`
 * pour un seul enseignant : prévu vs réalisé, mais pour tout le personnel et
 * ventilé par période — de quoi tracer l'activité et rapprocher la paie.
 */
class SuiviActiviteService
{
    /**
     * @return Collection<int, array{
     *     personnel_id: int, nom_complet: string, fonction: ?string,
     *     periodes: Collection<int, array<string, mixed>>,
     *     totaux: array<string, mixed>,
     * }>
     */
    public function parPersonnel(int $schoolId, CarbonImmutable $debut, CarbonImmutable $fin, string $granularite, ?int $personnelId = null): Collection
    {
        $seances = Seance::forSchool($schoolId)
            ->whereBetween('date_seance', [$debut, $fin])
            ->whereHas('classeMatiere', fn($q) => $q
                ->whereNotNull('personnel_id')
                ->when($personnelId, fn($q2) => $q2->where('personnel_id', $personnelId)))
            ->with('classeMatiere.enseignant')
            ->get(['id', 'classe_matiere_id', 'date_seance', 'heure_debut', 'heure_fin', 'statut']);

        return $seances
            ->groupBy(fn(Seance $s) => $s->classeMatiere->personnel_id)
            ->map(fn(Collection $seancesPersonnel) => $this->ligne($seancesPersonnel, $granularite))
            ->sortBy('nom_complet')
            ->values();
    }

    /** @param Collection<int, Seance> $seances */
    private function ligne(Collection $seances, string $granularite): array
    {
        $personnel = $seances->first()->classeMatiere->enseignant;

        $periodes = $seances
            ->groupBy(fn(Seance $s) => $this->cle($s->date_seance, $granularite))
            ->map(fn(Collection $groupe, string $cle) => $this->resume($groupe) + ['periode' => $cle])
            ->sortBy('periode')
            ->values();

        return [
            'personnel_id' => $personnel->id,
            'nom_complet' => $personnel->nom_complet,
            'fonction' => $personnel->fonction,
            'periodes' => $periodes,
            'totaux' => $this->resume($seances),
        ];
    }

    /** @param Collection<int, Seance> $groupe */
    private function resume(Collection $groupe): array
    {
        $prevues = (float) $groupe->sum(fn(Seance $s) => $s->dureeHeures());
        $realisees = (float) $groupe->where('statut', 'effectuee')->sum(fn(Seance $s) => $s->dureeHeures());

        return [
            'heures_prevues' => round($prevues, 1),
            'heures_realisees' => round($realisees, 1),
            'taux' => $prevues > 0 ? round($realisees / $prevues * 100, 1) : 0.0,
            'seances_prevues' => $groupe->count(),
            'seances_realisees' => $groupe->where('statut', 'effectuee')->count(),
            'seances_annulees' => $groupe->where('statut', 'annulee')->count(),
            'seances_en_retard' => $groupe->where('statut', 'prevue')
                ->filter(fn(Seance $s) => $s->date_seance->lt(now()->startOfDay()))
                ->count(),
        ];
    }

    private function cle(\Illuminate\Support\Carbon $date, string $granularite): string
    {
        return match ($granularite) {
            'semaine' => $date->format('o') . '-S' . $date->format('W'),
            'mois' => $date->format('Y-m'),
            'annee' => $date->format('Y'),
            default => $date->format('Y-m-d'),
        };
    }
}
