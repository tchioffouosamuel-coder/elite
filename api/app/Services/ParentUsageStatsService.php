<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\JustificationAbsence;
use App\Models\ModificationEleve;
use App\Models\Observation;
use App\Models\Preinscription;
use App\Models\Tuteur;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Usage du portail parent : adoption des comptes, activité (connexions),
 * volumes déposés par fonctionnalité et délai de traitement — pour suivre
 * en continu si le portail remplace effectivement les démarches papier
 * plutôt que de simplement exister à côté d'elles.
 *
 * Justifications d'absence : la table `justifications_absences` n'existe
 * qu'au travers du dépôt parent (cf. `JustificationAbsenceService::soumettre`),
 * donc comptée sans filtre d'origine — contrairement aux observations, qui
 * peuvent aussi venir du personnel.
 */
class ParentUsageStatsService extends BaseService
{
    /** @param list<int> $schoolIds */
    public function resume(array $schoolIds, int $jours): array
    {
        $fin = Carbon::now()->endOfDay();
        $debut = Carbon::now()->subDays($jours - 1)->startOfDay();

        return [
            'periode' => ['jours' => $jours, 'debut' => $debut->toDateString(), 'fin' => $fin->toDateString()],
            'adoption' => $this->adoption($schoolIds, $debut, $fin),
            'activite' => $this->activite($schoolIds, $debut, $fin),
            'volumes' => [
                'preinscriptions' => $this->volumeAvecStatut(Preinscription::forSchool($schoolIds), $debut, $fin),
                'modifications' => $this->volumeAvecStatut(ModificationEleve::forSchool($schoolIds), $debut, $fin),
                'justifications' => $this->volume(JustificationAbsence::forSchool($schoolIds), $debut, $fin),
                'observations' => $this->volume(
                    Observation::forSchool($schoolIds)->whereHas('user', fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('name', 'parent'))),
                    $debut,
                    $fin,
                ),
            ],
            'efficience' => [
                'delai_moyen_preinscriptions_heures' => $this->delaiMoyenHeures(Preinscription::forSchool($schoolIds), $debut, $fin),
                'delai_moyen_modifications_heures' => $this->delaiMoyenHeures(ModificationEleve::forSchool($schoolIds), $debut, $fin),
            ],
        ];
    }

    private function adoption(array $schoolIds, Carbon $debut, Carbon $fin): array
    {
        $tuteurs = Tuteur::forSchool($schoolIds);
        $total = (clone $tuteurs)->count();
        $avecCompte = (clone $tuteurs)->whereNotNull('user_id')->count();

        // `->role('parent')` (le scope Spatie) lève une exception si aucune
        // ligne `roles` nommée « parent » n'existe — vrai sur un poste
        // desktop qui n'a jamais eu à créer ce rôle localement (mono-
        // utilisateur, jamais de compte parent provisionné). `whereHas`
        // sur la relation donne le même filtre sans jamais planter :
        // simplement aucune ligne à trouver dans ce cas.
        $comptesParent = User::whereIn('school_id', $schoolIds)
            ->whereHas('roles', fn (Builder $q) => $q->where('name', 'parent'));

        return [
            'tuteurs_total' => $total,
            'comptes_parent_total' => $avecCompte,
            'taux_adoption' => $total > 0 ? round($avecCompte / $total * 100, 1) : 0.0,
            'comptes_ouverts_serie' => $this->serieQuotidienne((clone $comptesParent), $debut, $fin),
        ];
    }

    private function activite(array $schoolIds, Carbon $debut, Carbon $fin): array
    {
        $connexions = ActivityLog::forSchool($schoolIds)
            ->where('action', 'connexion')
            ->whereHas('user', fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('name', 'parent')))
            ->whereBetween('created_at', [$debut, $fin]);

        return [
            'connexions_totales' => (clone $connexions)->count(),
            'parents_actifs_distincts' => (clone $connexions)->pluck('user_id')->unique()->count(),
            'parents_actifs_7j' => (clone $connexions)
                ->where('created_at', '>=', Carbon::now()->subDays(6)->startOfDay())
                ->pluck('user_id')->unique()->count(),
            'serie_quotidienne' => $this->serieQuotidienne((clone $connexions), $debut, $fin),
        ];
    }

    /** @return array{total: int, serie: list<array{date: string, total: int}>} */
    private function volume(Builder $query, Carbon $debut, Carbon $fin): array
    {
        $periode = (clone $query)->whereBetween('created_at', [$debut, $fin]);

        return [
            'total' => (clone $periode)->count(),
            'serie' => $this->serieQuotidienne($periode, $debut, $fin),
        ];
    }

    /** @return array{total: int, repartition: array{en_attente: int, validee: int, rejetee: int}, serie: list<array{date: string, total: int}>} */
    private function volumeAvecStatut(Builder $query, Carbon $debut, Carbon $fin): array
    {
        $base = $this->volume($query, $debut, $fin);
        $periode = (clone $query)->whereBetween('created_at', [$debut, $fin]);

        $parStatut = (clone $periode)->selectRaw('statut, count(*) as total')->groupBy('statut')->pluck('total', 'statut');

        return [
            ...$base,
            'repartition' => [
                'en_attente' => (int) ($parStatut['en_attente'] ?? 0),
                'validee' => (int) ($parStatut['validee'] ?? 0),
                'rejetee' => (int) ($parStatut['rejetee'] ?? 0),
            ],
        ];
    }

    /**
     * Délai moyen, en heures, entre le dépôt et le traitement (validation ou
     * rejet) — l'indicateur direct d'efficience : plus il descend, plus vite
     * une famille obtient une réponse.
     */
    private function delaiMoyenHeures(Builder $query, Carbon $debut, Carbon $fin): ?float
    {
        $traitees = (clone $query)
            ->whereNotNull('traite_le')
            ->whereBetween('traite_le', [$debut, $fin])
            ->get(['created_at', 'traite_le']);

        if ($traitees->isEmpty()) {
            return null;
        }

        $heures = $traitees->map(fn ($m) => abs($m->created_at->diffInMinutes($m->traite_le)) / 60);

        return round($heures->avg(), 1);
    }

    /**
     * Série quotidienne continue sur `[debut, fin]` — les jours sans donnée
     * comptent pour zéro plutôt que d'être absents, sans quoi un graphique de
     * tendance afficherait des segments qui sautent les jours creux.
     *
     * @return list<array{date: string, total: int}>
     */
    private function serieQuotidienne(Builder $query, Carbon $debut, Carbon $fin): array
    {
        $parJour = $query
            ->whereBetween('created_at', [$debut, $fin])
            ->selectRaw('DATE(created_at) as jour, count(*) as total')
            ->groupBy('jour')
            ->pluck('total', 'jour');

        $serie = collect();
        for ($jour = $debut->copy(); $jour->lte($fin); $jour->addDay()) {
            $cle = $jour->toDateString();
            $serie->push(['date' => $cle, 'total' => (int) ($parJour[$cle] ?? 0)]);
        }

        return $serie->all();
    }
}
