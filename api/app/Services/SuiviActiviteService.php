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
     * @param array{personnel_id?: ?int, sous_systeme_id?: ?int, departement_id?: ?int} $filtres
     * @return Collection<int, array{
     *     personnel_id: int, nom_complet: string, fonction: ?string,
     *     periodes: Collection<int, array<string, mixed>>,
     *     totaux: array<string, mixed>,
     * }>
     */
    public function parPersonnel(int $schoolId, CarbonImmutable $debut, CarbonImmutable $fin, string $granularite, array $filtres = []): Collection
    {
        $personnelId = $filtres['personnel_id'] ?? null;
        $sousSystemeId = $filtres['sous_systeme_id'] ?? null;
        $departementId = $filtres['departement_id'] ?? null;

        $seances = Seance::forSchool($schoolId)
            ->whereBetween('date_seance', [$debut, $fin])
            ->whereHas('classeMatiere', function ($q) use ($personnelId, $sousSystemeId, $departementId) {
                // Un enseignant du secondaire est nommé sur l'affectation ; au
                // primaire/maternelle, c'est le titulaire de la classe qui
                // couvre toutes les matières sans y être nommé lui-même.
                $q->where(fn($q2) => $q2
                    ->whereNotNull('personnel_id')
                    ->orWhereHas('classe', fn($c) => $c->whereNotNull('titulaire_id')));

                if ($personnelId) {
                    $q->where(fn($q2) => $q2
                        ->where('personnel_id', $personnelId)
                        ->orWhereHas('classe', fn($c) => $c->where('titulaire_id', $personnelId)));
                }

                if ($sousSystemeId) {
                    $q->whereHas('classe', fn($c) => $c->where('sous_systeme_id', $sousSystemeId));
                }

                if ($departementId) {
                    $q->where(fn($q2) => $q2
                        ->whereHas('enseignant', fn($p) => $p->where('departement_id', $departementId))
                        ->orWhereHas('classe.titulaire', fn($p) => $p->where('departement_id', $departementId)));
                }
            })
            ->with(['classeMatiere.enseignant', 'classeMatiere.classe.titulaire'])
            ->get(['id', 'classe_matiere_id', 'date_seance', 'heure_debut', 'heure_fin', 'statut']);

        return $seances
            ->groupBy(fn(Seance $s) => $s->classeMatiere->personnel_id ?? $s->classeMatiere->classe->titulaire_id)
            ->map(fn(Collection $seancesPersonnel) => $this->ligne($seancesPersonnel, $granularite))
            ->sortBy('nom_complet')
            ->values();
    }

    /** @param Collection<int, Seance> $seances */
    private function ligne(Collection $seances, string $granularite): array
    {
        $classeMatiere = $seances->first()->classeMatiere;
        $personnel = $classeMatiere->enseignant ?? $classeMatiere->classe->titulaire;

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
