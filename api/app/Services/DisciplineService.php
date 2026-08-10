<?php

namespace App\Services;

use App\Models\AbsenceTrimestre;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Trimestre;
use App\Models\User;
use Illuminate\Support\Collection;

class DisciplineService extends BaseService
{
    /**
     * Grille de saisie des heures d'absence cumulées de la classe pour le
     * trimestre (comme _smapp : deux compteurs par élève, pas un journal).
     *
     * @return Collection<int, array{eleve_id:int, nom_complet:string, heures_justifiees:float, heures_non_justifiees:float}>
     */
    public function grille(Classe $classe, Trimestre $trimestre): Collection
    {
        $absences = AbsenceTrimestre::where('trimestre_id', $trimestre->id)
            ->whereIn('eleve_id', $classe->eleves()->pluck('id'))
            ->get()->keyBy('eleve_id');

        return $classe->eleves()->where('statut', 'actif')->orderBy('nom')->get()
            ->map(fn (Eleve $eleve) => [
                'eleve_id' => $eleve->id,
                'nom_complet' => $eleve->nomComplet(),
                'heures_justifiees' => (float) ($absences->get($eleve->id)?->heures_justifiees ?? 0),
                'heures_non_justifiees' => (float) ($absences->get($eleve->id)?->heures_non_justifiees ?? 0),
            ]);
    }

    /**
     * @param  array<int, array{eleve_id:int, heures_justifiees?: ?float, heures_non_justifiees?: ?float}>  $absences
     */
    public function sauvegarderEnLot(Classe $classe, Trimestre $trimestre, array $absences, ?User $user): int
    {
        $personnelId = $user?->personnel?->id;
        $eleveIdsValides = $classe->eleves()->pluck('id')->flip();

        return $this->transaction(function () use ($trimestre, $absences, $personnelId, $eleveIdsValides) {
            $count = 0;
            foreach ($absences as $row) {
                if (! $eleveIdsValides->has($row['eleve_id'])) {
                    continue;
                }

                AbsenceTrimestre::updateOrCreate(
                    ['eleve_id' => $row['eleve_id'], 'trimestre_id' => $trimestre->id],
                    [
                        'heures_justifiees' => $row['heures_justifiees'] ?? 0,
                        'heures_non_justifiees' => $row['heures_non_justifiees'] ?? 0,
                        'mis_a_jour_par' => $personnelId,
                    ]
                );
                $count++;
            }

            return $count;
        });
    }

    /**
     * Détail par élève (justifiées + non justifiées) pour l'export PDF —
     * bilanClasse() n'expose que les agrégats.
     *
     * @return Collection<int, array{eleve: Eleve, hj: float, hnj: float}>
     */
    public function lignesDetail(Classe $classe, Trimestre $trimestre): Collection
    {
        $eleves = $classe->eleves()->where('statut', 'actif')->orderBy('nom')->get();
        $absences = AbsenceTrimestre::where('trimestre_id', $trimestre->id)
            ->whereIn('eleve_id', $eleves->pluck('id'))
            ->get()->keyBy('eleve_id');

        return $eleves->map(fn (Eleve $e) => [
            'eleve' => $e,
            'hj' => (float) ($absences->get($e->id)?->heures_justifiees ?? 0),
            'hnj' => (float) ($absences->get($e->id)?->heures_non_justifiees ?? 0),
        ]);
    }

    /**
     * Bilan disciplinaire d'une classe : total/moyenne d'heures non
     * justifiées par genre, élève le plus absent — même logique que
     * bilan_disciplinaire.php (basée sur les heures, pas sur le nombre de
     * sanctions).
     */
    public function bilanClasse(Classe $classe, Trimestre $trimestre): array
    {
        $eleves = $classe->eleves()->where('statut', 'actif')->get();
        $absences = AbsenceTrimestre::where('trimestre_id', $trimestre->id)
            ->whereIn('eleve_id', $eleves->pluck('id'))
            ->get()->keyBy('eleve_id');

        $rows = $eleves->map(fn (Eleve $e) => [
            'eleve' => $e,
            'hnj' => (float) ($absences->get($e->id)?->heures_non_justifiees ?? 0),
        ]);

        $garcons = $rows->filter(fn ($r) => $r['eleve']->sexe === 'M');
        $filles = $rows->filter(fn ($r) => $r['eleve']->sexe === 'F');
        $plusAbsent = $rows->sortByDesc('hnj')->first();

        return [
            'effectif' => $eleves->count(),
            'total_hnj' => round((float) $rows->sum('hnj'), 1),
            'moyenne_hnj' => $eleves->count() > 0 ? round($rows->sum('hnj') / $eleves->count(), 1) : 0,
            'total_hnj_garcons' => round((float) $garcons->sum('hnj'), 1),
            'moyenne_hnj_garcons' => $garcons->count() > 0 ? round($garcons->sum('hnj') / $garcons->count(), 1) : 0,
            'total_hnj_filles' => round((float) $filles->sum('hnj'), 1),
            'moyenne_hnj_filles' => $filles->count() > 0 ? round($filles->sum('hnj') / $filles->count(), 1) : 0,
            'eleve_plus_absent' => $plusAbsent && $plusAbsent['hnj'] > 0 ? [
                'nom_complet' => $plusAbsent['eleve']->nomComplet(),
                'heures_non_justifiees' => $plusAbsent['hnj'],
            ] : null,
        ];
    }
}
