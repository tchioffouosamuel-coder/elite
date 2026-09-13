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
use Illuminate\Support\Collection;
use InvalidArgumentException;

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
            'comptes' => $this->comptes($schoolIds, $debut, $fin),
            'adoption' => $this->adoption($schoolIds, $debut, $fin),
            'activite' => $this->activite($schoolIds, $debut, $fin),
            'volumes' => [
                'preinscriptions' => $this->volumeAvecStatut(Preinscription::forSchool($schoolIds), $debut, $fin),
                'modifications' => $this->volumeAvecStatut(ModificationEleve::forSchool($schoolIds), $debut, $fin),
                'justifications' => $this->volume(JustificationAbsence::forSchool($schoolIds), $debut, $fin),
                'observations' => $this->volume(
                    Observation::forSchool($schoolIds)->whereHas('user', fn($q) => $q->whereHas('roles', fn($r) => $r->where('name', 'parent'))),
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

    /**
     * État des comptes du portail parent et des comptes du personnel : une
     * ouverture est la création du compte, une activité est une connexion
     * pendant la période sélectionnée, et un dormant est un compte actif sans
     * connexion dans cette période.
     */
    private function comptes(array $schoolIds, Carbon $debut, Carbon $fin): array
    {
        [$parents, $staff, $dernieresConnexions] = $this->chargerComptes($schoolIds);

        return [
            'parents' => $this->etatComptes($parents, $dernieresConnexions, $debut, $fin),
            'personnel' => $this->etatComptes($staff, $dernieresConnexions, $debut, $fin),
        ];
    }

    /**
     * Comptes parent et personnel, avec leur dernière connexion — chargés une
     * seule fois puis partagés entre le décompte ({@see etatComptes}) et le
     * détail nominatif ({@see listeComptes}) pour ne jamais laisser les deux
     * dériver l'un de l'autre.
     *
     * @param  list<int>  $schoolIds
     * @return array{0: Collection<int, User>, 1: Collection<int, User>, 2: Collection<int, string>}
     */
    private function chargerComptes(array $schoolIds): array
    {
        $tuteursAvecCompte = Tuteur::forSchool($schoolIds)->whereNotNull('user_id')->pluck('user_id');
        $colonnes = ['id', 'name', 'email', 'phone', 'is_active', 'created_at'];
        $parents = User::whereIn('id', $tuteursAvecCompte)->get($colonnes);
        $staff = User::where(
            fn($q) => $q->whereIn('school_id', $schoolIds)
                ->orWhereHas('schools', fn($s) => $s->whereIn('schools.id', $schoolIds))
                ->orWhereHas('roles', fn($r) => $r->where('name', 'super_admin')),
        )
            ->whereDoesntHave('roles', fn($q) => $q->where('name', 'parent'))
            ->get($colonnes);

        $ids = $parents->merge($staff)->pluck('id');
        $dernieresConnexions = ActivityLog::whereIn('user_id', $ids)
            ->where('action', 'connexion')
            ->selectRaw('user_id, MAX(created_at) as derniere')
            ->groupBy('user_id')
            ->pluck('derniere', 'user_id');

        return [$parents, $staff, $dernieresConnexions];
    }

    private function etatComptes(Collection $comptes, Collection $dernieresConnexions, Carbon $debut, Carbon $fin): array
    {
        $total = $comptes->count();
        $actifsDansPeriode = $this->filtrerParCategorie($comptes, $dernieresConnexions, $debut, $fin, 'actifs');
        $dormants = $this->filtrerParCategorie($comptes, $dernieresConnexions, $debut, $fin, 'dormants');

        return [
            'total' => $total,
            'ouverts_dans_periode' => $this->filtrerParCategorie($comptes, $dernieresConnexions, $debut, $fin, 'ouverts_dans_periode')->count(),
            'actifs' => $actifsDansPeriode->count(),
            'dormants' => $dormants->count(),
            'jamais_connectes' => $this->filtrerParCategorie($comptes, $dernieresConnexions, $debut, $fin, 'jamais_connectes')->count(),
            'desactives' => $this->filtrerParCategorie($comptes, $dernieresConnexions, $debut, $fin, 'desactives')->count(),
            'taux_actifs' => $total > 0 ? round($actifsDansPeriode->count() / $total * 100, 1) : 0.0,
            'taux_dormants' => $total > 0 ? round($dormants->count() / $total * 100, 1) : 0.0,
        ];
    }

    /**
     * Le sous-ensemble d'une catégorie de la carte tableau de bord (« Actifs »,
     * « Dormants »…) — seul juge de qui en fait partie, pour que le décompte
     * affiché et la liste ouverte derrière la carte ne puissent jamais diverger.
     *
     * @param  Collection<int, User>  $comptes
     * @param  Collection<int, string>  $dernieresConnexions
     */
    private function filtrerParCategorie(Collection $comptes, Collection $dernieresConnexions, Carbon $debut, Carbon $fin, string $categorie): Collection
    {
        return match ($categorie) {
            'total' => $comptes,
            'ouverts_dans_periode' => $comptes->filter(fn(User $u) => $u->created_at?->betweenIncluded($debut, $fin)),
            'actifs' => $comptes->where('is_active', true)->filter(fn(User $u) => ($derniere = $dernieresConnexions->get($u->id)) !== null
                && Carbon::parse($derniere)->betweenIncluded($debut, $fin)),
            'dormants' => $comptes->where('is_active', true)->reject(fn(User $u) => ($derniere = $dernieresConnexions->get($u->id)) !== null
                && Carbon::parse($derniere)->betweenIncluded($debut, $fin)),
            'jamais_connectes' => $comptes->where('is_active', true)->filter(fn(User $u) => $dernieresConnexions->get($u->id) === null),
            'desactives' => $comptes->where('is_active', false),
            default => throw new InvalidArgumentException("Catégorie de comptes inconnue : {$categorie}."),
        };
    }

    /**
     * Détail nominatif d'une catégorie de comptes — les individus derrière le
     * décompte d'une carte du tableau de bord, pour la modale ouverte au clic.
     *
     * @param  list<int>  $schoolIds
     * @param  'parents'|'personnel'  $segment
     */
    public function listeComptes(array $schoolIds, string $segment, string $categorie, int $jours): Collection
    {
        $fin = Carbon::now()->endOfDay();
        $debut = Carbon::now()->subDays($jours - 1)->startOfDay();

        [$parents, $staff, $dernieresConnexions] = $this->chargerComptes($schoolIds);
        $comptes = $segment === 'personnel' ? $staff : $parents;

        return $this->filtrerParCategorie($comptes, $dernieresConnexions, $debut, $fin, $categorie)
            ->map(fn(User $u) => [
                'id' => $u->id,
                'nom' => $u->name,
                'email' => $u->email,
                'telephone' => $u->phone,
                'actif' => $u->is_active,
                'cree_le' => $u->created_at?->toIso8601String(),
                'derniere_connexion' => ($derniere = $dernieresConnexions->get($u->id)) ? Carbon::parse($derniere)->toIso8601String() : null,
            ])
            ->sortBy('nom')
            ->values();
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
            ->whereHas('roles', fn(Builder $q) => $q->where('name', 'parent'));
        /** @var Builder $comptesParent */

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
            ->whereHas('user', fn($q) => $q->whereHas('roles', fn($r) => $r->where('name', 'parent')))
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

        $heures = $traitees->map(fn($m) => abs($m->created_at->diffInMinutes($m->traite_le)) / 60);

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
