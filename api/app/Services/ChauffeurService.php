<?php

namespace App\Services;

use App\Models\BusAffectation;
use App\Models\BusRamassage;
use App\Models\BusRemplacement;
use App\Models\BusVehicule;
use App\Models\BusVersement;
use App\Models\Depense;
use App\Models\Personnel;
use App\Models\Remuneration;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;
use RuntimeException;

/**
 * Le métier du chauffeur, vu depuis son siège : le bus qu'il conduit, les
 * enfants qu'il transporte, ce que son véhicule rapporte face à ce qu'il
 * coûte, et sa tournée arrêt par arrêt.
 *
 * Tout y est borné à ses propres véhicules (`vehicules()`) : le chauffeur
 * porte `bus.view`, qui lui ouvrirait sinon la flotte entière, les trajets et
 * les souscriptions de tout le complexe. Aucune écriture sur une souscription,
 * un trajet ou un arrêt n'existe ici — un chauffeur pointe sa tournée et passe
 * la main quand il est empêché, rien de plus.
 */
class ChauffeurService extends BaseService
{
    /**
     * Les véhicules qu'un chauffeur conduit à une date donnée : le sien, sauf
     * s'il a rendu son itinéraire sur cette date, plus ceux qu'il a repris à
     * un collègue empêché.
     *
     * @return Collection<int, BusVehicule>
     */
    public function vehicules(Personnel $chauffeur, ?string $date = null): Collection
    {
        $jour = $date ?? Carbon::today()->toDateString();

        $cedes = BusRemplacement::enCours($jour)
            ->where('chauffeur_titulaire_id', $chauffeur->id)
            ->pluck('vehicule_id');

        $repris = BusRemplacement::enCours($jour)
            ->where('chauffeur_remplacant_id', $chauffeur->id)
            ->pluck('vehicule_id');

        return BusVehicule::query()
            ->where(fn ($q) => $q
                ->where(fn ($sien) => $sien->where('chauffeur_id', $chauffeur->id)->whereNotIn('id', $cedes))
                ->orWhereIn('id', $repris))
            ->with(['chauffeur:id,nom_complet,telephone', 'trajets.arrets'])
            ->orderBy('immatriculation')
            ->get();
    }

    /**
     * Ce qu'un chauffeur voit en ouvrant l'application : combien d'enfants il
     * transporte, ce que son bus rapporte ce mois-ci, et où en est sa tournée
     * du jour.
     *
     * @return array<string, mixed>
     */
    public function tableauDeBord(Personnel $chauffeur, ?string $mois = null, ?string $date = null): array
    {
        $jour = $date ?? Carbon::today()->toDateString();
        $debutMois = $mois ? Carbon::parse($mois)->startOfMonth() : Carbon::parse($jour)->startOfMonth();
        $vehicules = $this->vehicules($chauffeur, $jour);

        $bilans = $vehicules->map(fn (BusVehicule $v) => $this->rentabilite($v, $chauffeur, $debutMois))->values();

        return [
            'mois' => $debutMois->format('Y-m-d'),
            'date' => $jour,
            'effectif_transporte' => (int) $bilans->sum('effectif'),
            'capacite_totale' => (int) $vehicules->sum('capacite'),
            /*
             * Agrégé sur tous les véhicules conduits ce jour-là. Le salaire
             * n'est compté qu'une fois : c'est celui du chauffeur, pas une
             * charge du bus — cf. `rentabilite`, qui le porte sur le véhicule
             * dont il est titulaire et à zéro sur un bus simplement repris.
             */
            'rentabilite' => [
                'recettes' => (int) $bilans->sum('recettes'),
                'depenses' => (int) $bilans->sum('depenses'),
                'salaire' => (int) $bilans->sum('salaire'),
                'resultat' => (int) $bilans->sum('resultat'),
            ],
            'vehicules' => $bilans,
            'tournee_du_jour' => [
                'aller' => $this->avancementTournee($vehicules, $jour, 'aller'),
                'retour' => $this->avancementTournee($vehicules, $jour, 'retour'),
            ],
            // Itinéraires qu'un collègue empêché a rendus disponibles : le
            // chauffeur peut les reprendre depuis son accueil.
            'itineraires_disponibles' => $this->itinerairesDisponibles($chauffeur, $jour),
            'mes_empechements' => BusRemplacement::where('chauffeur_titulaire_id', $chauffeur->id)
                ->where('statut', '!=', 'annule')
                ->whereDate('au', '>=', $jour)
                ->with(['vehicule:id,immatriculation,marque,capacite', 'remplacant:id,nom_complet,telephone', 'titulaire:id,nom_complet,telephone'])
                ->orderBy('du')
                ->get()
                ->map(fn (BusRemplacement $r) => $this->presenterRemplacement($r))
                ->values(),
        ];
    }

    /**
     * Rentabilité d'un bus sur un mois : ce que les familles ont effectivement
     * réglé pour ce mois-là, moins les dépenses du mois imputées au véhicule
     * et le salaire de son chauffeur.
     *
     * Les recettes se comptent sur le mois *couvert* par la mensualité
     * (`bus_versements.mois`) et non sur la date d'encaissement — « les
     * souscriptions payées pour le mois en cours » : un parent qui règle en
     * novembre trois mois de retard ne gonfle pas la rentabilité de novembre.
     *
     * @return array<string, mixed>
     */
    public function rentabilite(BusVehicule $vehicule, Personnel $chauffeur, Carbon $debutMois): array
    {
        $affectations = BusAffectation::whereHas('trajet', fn ($q) => $q->where('vehicule_id', $vehicule->id))
            ->actives()
            ->pluck('id');

        $recettes = $affectations->isEmpty() ? 0 : (int) BusVersement::whereIn('bus_affectation_id', $affectations)
            ->valides()
            ->whereYear('mois', $debutMois->year)
            ->whereMonth('mois', $debutMois->month)
            ->sum('montant');

        $depenses = (int) Depense::where('vehicule_id', $vehicule->id)
            ->valides()
            ->whereYear('date_depense', $debutMois->year)
            ->whereMonth('date_depense', $debutMois->month)
            ->sum('montant');

        /*
         * Le salaire ne pèse que sur le bus dont l'intéressé est titulaire :
         * sur un véhicule repris en remplacement, le compter une seconde fois
         * doublerait la même charge dans le total du tableau de bord.
         */
        $salaire = $vehicule->chauffeur_id === $chauffeur->id ? $this->salaireMensuel($chauffeur) : 0;

        return [
            'vehicule_id' => $vehicule->id,
            'immatriculation' => $vehicule->immatriculation,
            'marque' => $vehicule->marque,
            'capacite' => $vehicule->capacite,
            'statut' => $vehicule->statut,
            'effectif' => $affectations->count(),
            'recettes' => $recettes,
            'depenses' => $depenses,
            'salaire' => $salaire,
            'resultat' => $recettes - ($depenses + $salaire),
            // Un bus repris à un collègue : le dire évite de laisser croire au
            // chauffeur que c'est son affectation habituelle.
            'repris_en_remplacement' => $vehicule->chauffeur_id !== $chauffeur->id,
        ];
    }

    /**
     * Itinéraire de la tournée : chaque trajet du bus, ses arrêts dans
     * l'ordre, et à chaque arrêt les enfants qui y montent — avec le contact
     * de leurs tuteurs, pour prévenir une famille depuis le bord de la route,
     * et l'état du pointage du jour.
     *
     * @return array<string, mixed>
     */
    public function itineraire(Personnel $chauffeur, string $date, string $sens): array
    {
        $vehicules = $this->vehicules($chauffeur, $date);
        $vehiculeIds = $vehicules->pluck('id');

        $affectations = BusAffectation::whereHas('trajet', fn ($q) => $q->whereIn('vehicule_id', $vehiculeIds))
            ->actives()
            ->with([
                'eleve:id,school_id,classe_id,matricule,nom_complet,sexe',
                'eleve.classe:id,nom',
                'eleve.tuteurs:id,nom_complet,telephone,email',
                'arret:id,trajet_id,nom,lieu_dit,ordre,heure_passage',
                'trajet:id,vehicule_id,nom',
            ])
            ->get();

        $pointes = BusRamassage::whereIn('bus_affectation_id', $affectations->pluck('id'))
            ->tournee($date, $sens)
            ->get()
            ->keyBy('bus_affectation_id');

        $parTrajet = $affectations->groupBy('trajet_id');

        $trajets = $vehicules->flatMap(fn (BusVehicule $v) => $v->trajets->map(function ($trajet) use ($v, $parTrajet, $pointes) {
            $duTrajet = $parTrajet->get($trajet->id, collect());

            $arrets = $trajet->arrets->map(fn ($arret) => [
                'id' => $arret->id,
                'nom' => $arret->nom,
                'lieu_dit' => $arret->lieu_dit,
                'ordre' => $arret->ordre,
                'heure_passage' => $arret->heure_passage,
                'eleves' => $this->presenterEleves($duTrajet->where('arret_id', $arret->id), $pointes),
            ])->values();

            /*
             * Une souscription sans arrêt (import, ou arrêt supprimé depuis)
             * resterait invisible dans une liste rangée par arrêt : elle tombe
             * dans un groupe « sans arrêt » plutôt que d'être oubliée au bord
             * de la route.
             */
            $sansArret = $this->presenterEleves($duTrajet->whereNull('arret_id'), $pointes);

            return [
                'id' => $trajet->id,
                'nom' => $trajet->nom,
                'vehicule_id' => $v->id,
                'immatriculation' => $v->immatriculation,
                'effectif' => $duTrajet->count(),
                'pris' => $duTrajet->filter(fn (BusAffectation $a) => $pointes->has($a->id))->count(),
                'arrets' => $arrets,
                'eleves_sans_arret' => $sansArret,
            ];
        }))->values();

        return [
            'date' => $date,
            'sens' => $sens,
            'effectif' => (int) $trajets->sum('effectif'),
            'pris' => (int) $trajets->sum('pris'),
            'trajets' => $trajets,
        ];
    }

    /**
     * Liste à plat des enfants transportés, avec le contact de leurs tuteurs —
     * l'annuaire de bord, quand l'itinéraire arrêt par arrêt n'est pas ce que
     * le chauffeur cherche.
     *
     * @return SupportCollection<int, array<string, mixed>>
     */
    public function elevesTransportes(Personnel $chauffeur, ?string $date = null): SupportCollection
    {
        $jour = $date ?? Carbon::today()->toDateString();
        $vehiculeIds = $this->vehicules($chauffeur, $jour)->pluck('id');

        $affectations = BusAffectation::whereHas('trajet', fn ($q) => $q->whereIn('vehicule_id', $vehiculeIds))
            ->actives()
            ->with([
                'eleve:id,school_id,classe_id,matricule,nom_complet,sexe',
                'eleve.classe:id,nom',
                'eleve.tuteurs:id,nom_complet,telephone,email',
                'arret:id,trajet_id,nom,lieu_dit,ordre',
                'trajet:id,vehicule_id,nom',
            ])
            ->get();

        return $this->presenterEleves($affectations, collect())
            ->map(function (array $ligne) use ($affectations) {
                $affectation = $affectations->firstWhere('id', $ligne['affectation_id']);

                return [
                    ...$ligne,
                    'trajet' => $affectation?->trajet?->nom,
                    'arret' => $affectation?->arret?->nom,
                ];
            })
            ->values();
    }

    /**
     * Coche (ou décoche) un enfant comme pris en charge sur la tournée du
     * jour. Idempotent : un double tap, ou une requête rejouée par un réseau
     * capricieux, ne crée pas deux pointages (unicité en base).
     */
    public function pointer(Personnel $chauffeur, int $affectationId, string $date, string $sens, bool $pris, ?int $userId = null): ?BusRamassage
    {
        $affectation = $this->affectationDeMaTournee($chauffeur, $affectationId, $date);

        if (! $pris) {
            BusRamassage::where('bus_affectation_id', $affectation->id)->tournee($date, $sens)->delete();

            return null;
        }

        return BusRamassage::updateOrCreate(
            ['bus_affectation_id' => $affectation->id, 'date_ramassage' => $date, 'sens' => $sens],
            ['arret_id' => $affectation->arret_id, 'pris_le' => now(), 'pointe_par' => $userId],
        );
    }

    /**
     * Un chauffeur empêché rend l'itinéraire de son bus disponible sur une
     * période : un collègue pourra le reprendre (`reprendre()`), ou la
     * direction le lui attribuer directement en nommant le remplaçant ici.
     *
     * `$titulaire` n'est fourni que lorsque c'est le chauffeur lui-même qui
     * se déclare empêché — la direction, elle, ouvre le relais sur n'importe
     * quel bus de la flotte.
     *
     * @param  array{vehicule_id: int, du: string, au: string, motif?: ?string, chauffeur_remplacant_id?: ?int}  $donnees
     */
    public function rendreDisponible(array $donnees, ?Personnel $titulaire = null, ?int $userId = null): BusRemplacement
    {
        $vehicule = BusVehicule::findOrFail($donnees['vehicule_id']);

        if ($titulaire && $vehicule->chauffeur_id !== $titulaire->id) {
            throw new RuntimeException("Ce véhicule n'est pas le vôtre : seul son chauffeur titulaire ou la direction peut confier son itinéraire.");
        }

        if (Carbon::parse($donnees['au'])->lessThan(Carbon::parse($donnees['du']))) {
            throw new RuntimeException('La date de fin ne peut pas précéder la date de début.');
        }

        /*
         * Deux relais qui se chevauchent sur le même bus laisseraient deux
         * chauffeurs se croire aux commandes le même jour : un seul relais
         * vivant à la fois par véhicule.
         */
        $chevauche = BusRemplacement::where('vehicule_id', $vehicule->id)
            ->where('statut', '!=', 'annule')
            ->whereDate('du', '<=', $donnees['au'])
            ->whereDate('au', '>=', $donnees['du'])
            ->exists();

        if ($chevauche) {
            throw new RuntimeException('Un relais est déjà enregistré sur ce bus pour cette période.');
        }

        $remplacant = $donnees['chauffeur_remplacant_id'] ?? null;

        if ($remplacant && $remplacant === $vehicule->chauffeur_id) {
            throw new RuntimeException('Le remplaçant ne peut pas être le chauffeur titulaire du bus.');
        }

        return BusRemplacement::create([
            'vehicule_id' => $vehicule->id,
            'chauffeur_titulaire_id' => $titulaire?->id ?? $vehicule->chauffeur_id,
            'chauffeur_remplacant_id' => $remplacant,
            'du' => $donnees['du'],
            'au' => $donnees['au'],
            'motif' => $donnees['motif'] ?? null,
            // La direction peut désigner le remplaçant dans le même geste :
            // l'itinéraire est alors pourvu sans passer par l'étape « offert ».
            'statut' => $remplacant ? 'pourvu' : 'disponible',
            'pourvu_le' => $remplacant ? now() : null,
            'ouvert_par' => $userId,
        ])->load(['vehicule:id,immatriculation,marque,capacite', 'titulaire:id,nom_complet,telephone', 'remplacant:id,nom_complet,telephone']);
    }

    /** Un chauffeur (ou la direction pour lui) reprend un itinéraire offert. */
    public function reprendre(BusRemplacement $remplacement, Personnel $remplacant): BusRemplacement
    {
        if ($remplacement->statut !== 'disponible' || $remplacement->chauffeur_remplacant_id !== null) {
            throw new RuntimeException("Cet itinéraire n'est plus disponible.");
        }

        if ($remplacement->chauffeur_titulaire_id === $remplacant->id) {
            throw new RuntimeException('Cet itinéraire est déjà le vôtre : annulez votre empêchement pour le reprendre.');
        }

        $remplacement->update([
            'chauffeur_remplacant_id' => $remplacant->id,
            'statut' => 'pourvu',
            'pourvu_le' => now(),
        ]);

        return $remplacement->fresh(['vehicule:id,immatriculation,marque,capacite', 'titulaire:id,nom_complet,telephone', 'remplacant:id,nom_complet,telephone']);
    }

    /** Empêchement levé : le titulaire reprend son bus, le relais est clos. */
    public function annulerRemplacement(BusRemplacement $remplacement): BusRemplacement
    {
        if ($remplacement->statut === 'annule') {
            throw new RuntimeException('Ce relais est déjà annulé.');
        }

        $remplacement->update(['statut' => 'annule', 'annule_le' => now()]);

        return $remplacement->fresh(['vehicule:id,immatriculation,marque,capacite', 'titulaire:id,nom_complet,telephone', 'remplacant:id,nom_complet,telephone']);
    }

    /**
     * Itinéraires offerts par des collègues, les siens exclus.
     *
     * @return SupportCollection<int, array<string, mixed>>
     */
    public function itinerairesDisponibles(Personnel $chauffeur, ?string $date = null): SupportCollection
    {
        $jour = $date ?? Carbon::today()->toDateString();

        return BusRemplacement::disponibles()
            ->whereDate('au', '>=', $jour)
            ->where(fn ($q) => $q->whereNull('chauffeur_titulaire_id')->orWhere('chauffeur_titulaire_id', '!=', $chauffeur->id))
            ->with(['vehicule:id,immatriculation,marque,capacite', 'titulaire:id,nom_complet,telephone', 'remplacant:id,nom_complet,telephone'])
            ->orderBy('du')
            ->get()
            ->map(fn (BusRemplacement $r) => $this->presenterRemplacement($r))
            ->values();
    }

    /**
     * Tous les relais de la flotte, pour la direction : qui est empêché, qui
     * le remplace, et ce qui reste sans preneur.
     *
     * @return SupportCollection<int, array<string, mixed>>
     */
    public function relaisDeLaFlotte(?string $statut = null): SupportCollection
    {
        return BusRemplacement::query()
            ->when($statut, fn ($q, $s) => $q->where('statut', $s))
            ->with(['vehicule:id,immatriculation,marque,capacite', 'titulaire:id,nom_complet,telephone', 'remplacant:id,nom_complet,telephone'])
            ->orderByDesc('du')
            ->get()
            ->map(fn (BusRemplacement $r) => $this->presenterRemplacement($r))
            ->values();
    }

    /** @return array<string, mixed> */
    public function presenterRemplacement(BusRemplacement $r): array
    {
        return [
            'id' => $r->id,
            'vehicule_id' => $r->vehicule_id,
            'immatriculation' => $r->vehicule?->immatriculation,
            'marque' => $r->vehicule?->marque,
            'capacite' => $r->vehicule?->capacite,
            'du' => $r->du->format('Y-m-d'),
            'au' => $r->au->format('Y-m-d'),
            'motif' => $r->motif,
            'statut' => $r->statut,
            'titulaire' => $this->presenterChauffeur($r->titulaire),
            'remplacant' => $this->presenterChauffeur($r->remplacant),
        ];
    }

    /** @return array<string, mixed>|null */
    private function presenterChauffeur(?Personnel $personnel): ?array
    {
        return $personnel ? [
            'id' => $personnel->id,
            'nom_complet' => $personnel->nom_complet,
            'telephone' => $personnel->telephone,
        ] : null;
    }

    /**
     * Enfants d'un arrêt, avec les contacts de leurs tuteurs et l'état du
     * pointage.
     *
     * @param  SupportCollection<int, BusAffectation>|Collection<int, BusAffectation>  $affectations
     * @param  SupportCollection<int, BusRamassage>  $pointes
     * @return SupportCollection<int, array<string, mixed>>
     */
    private function presenterEleves($affectations, $pointes): SupportCollection
    {
        return $affectations
            ->sortBy(fn (BusAffectation $a) => mb_strtolower(trim($a->eleve?->nom_complet ?? '')))
            ->map(function (BusAffectation $a) use ($pointes) {
                $ramassage = $pointes->get($a->id);

                return [
                    'affectation_id' => $a->id,
                    'eleve_id' => $a->eleve_id,
                    'matricule' => $a->eleve?->matricule,
                    'nom_complet' => $a->eleve?->nom_complet,
                    'sexe' => $a->eleve?->sexe,
                    'classe' => $a->eleve?->classe?->nom,
                    'option_trajet' => $a->option_trajet,
                    'pris' => $ramassage !== null,
                    'pris_le' => $ramassage?->pris_le?->format('H:i'),
                    /*
                     * De quoi joindre la famille depuis le bord de la route :
                     * c'est tout ce que le chauffeur a à connaître du dossier
                     * — ni scolarité, ni notes, ni situation financière.
                     */
                    'tuteurs' => ($a->eleve?->tuteurs ?? collect())->map(fn ($t) => [
                        'nom_complet' => $t->nom_complet,
                        'telephone' => $t->telephone,
                        'lien_parente' => $t->pivot?->lien_parente,
                        'principal' => (bool) ($t->pivot?->is_principal ?? false),
                    ])->values(),
                ];
            })
            ->values();
    }

    /**
     * Combien d'enfants sont déjà montés sur une tournée — ce que le tableau
     * de bord affiche sous le bouton d'itinéraire.
     *
     * @param  Collection<int, BusVehicule>  $vehicules
     * @return array{effectif: int, pris: int}
     */
    private function avancementTournee(Collection $vehicules, string $date, string $sens): array
    {
        $affectations = BusAffectation::whereHas('trajet', fn ($q) => $q->whereIn('vehicule_id', $vehicules->pluck('id')))
            ->actives()
            ->pluck('id');

        $pris = $affectations->isEmpty() ? 0 : BusRamassage::whereIn('bus_affectation_id', $affectations)
            ->tournee($date, $sens)
            ->count();

        return ['effectif' => $affectations->count(), 'pris' => $pris];
    }

    /** Dernière rémunération contractuelle en vigueur, brut — 0 sans fiche de paie. */
    private function salaireMensuel(Personnel $chauffeur): int
    {
        $remuneration = Remuneration::where('personnel_id', $chauffeur->id)
            ->orderByDesc('date_effet')
            ->orderByDesc('id')
            ->first();

        return $remuneration ? (int) $remuneration->brut : 0;
    }

    /**
     * Une souscription que ce chauffeur transporte bien ce jour-là — sans ce
     * contrôle, `bus.view` laisserait pointer l'enfant d'un autre bus.
     */
    private function affectationDeMaTournee(Personnel $chauffeur, int $affectationId, string $date): BusAffectation
    {
        $vehiculeIds = $this->vehicules($chauffeur, $date)->pluck('id');

        return BusAffectation::whereHas('trajet', fn ($q) => $q->whereIn('vehicule_id', $vehiculeIds))
            ->actives()
            ->findOrFail($affectationId);
    }
}
