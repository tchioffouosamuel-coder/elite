<?php

namespace App\Services;

use App\Models\BusAffectation;
use App\Models\BusArret;
use App\Models\BusTrajet;
use App\Models\BusVehicule;
use App\Models\Eleve;
use App\Services\Sms\SmsService;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

/**
 * Transport scolaire : flotte, trajets/arrêts, et affectation des élèves.
 *
 * Un trajet et ses arrêts n'ont pas d'existence propre hors de l'école qui les
 * a créés ; on les atteint donc toujours via un trajet déjà chargé et scopé
 * (`trouverTrajet`), plutôt que de dupliquer un scope d'école sur des tables
 * qui ne portent pas `school_id` elles-mêmes.
 */
class BusService extends BaseService
{
    public function __construct(private readonly SmsService $sms) {}

    // ---- Véhicules --------------------------------------------------

    public function listerVehicules(int $schoolId): Collection
    {
        return BusVehicule::forSchool($schoolId)->with('chauffeur')->orderBy('immatriculation')->get();
    }

    public function trouverVehicule(int $schoolId, int $id): BusVehicule
    {
        return BusVehicule::forSchool($schoolId)->with('chauffeur')->findOrFail($id);
    }

    /** @param array<string, mixed> $donnees */
    public function creerVehicule(int $schoolId, array $donnees): BusVehicule
    {
        return BusVehicule::create([...$donnees, 'school_id' => $schoolId]);
    }

    /** @param array<string, mixed> $donnees */
    public function modifierVehicule(BusVehicule $vehicule, array $donnees): BusVehicule
    {
        $vehicule->update($donnees);

        return $vehicule->fresh('chauffeur');
    }

    public function supprimerVehicule(BusVehicule $vehicule): void
    {
        $vehicule->delete();
    }

    // ---- Trajets et arrêts -------------------------------------------

    public function listerTrajets(int $schoolId): Collection
    {
        return BusTrajet::forSchool($schoolId)
            ->with(['vehicule', 'arrets'])
            ->withCount(['affectations' => fn ($q) => $q->actives()])
            ->orderBy('nom')
            ->get();
    }

    public function trouverTrajet(int $schoolId, int $id): BusTrajet
    {
        return BusTrajet::forSchool($schoolId)
            ->with(['vehicule', 'arrets', 'affectations.eleve', 'affectations.arret'])
            ->findOrFail($id);
    }

    /** @param array<string, mixed> $donnees */
    public function creerTrajet(int $schoolId, array $donnees): BusTrajet
    {
        return BusTrajet::create([
            'school_id' => $schoolId,
            'vehicule_id' => $donnees['vehicule_id'] ?? null,
            'nom' => $donnees['nom'],
            'description' => $donnees['description'] ?? null,
        ]);
    }

    /** @param array<string, mixed> $donnees */
    public function modifierTrajet(BusTrajet $trajet, array $donnees): BusTrajet
    {
        $trajet->update($donnees);

        return $trajet->fresh(['vehicule', 'arrets']);
    }

    public function supprimerTrajet(BusTrajet $trajet): void
    {
        // Les arrêts et affectations du trajet disparaissent avec lui
        // (cascade en base) : plus rien ne les rattacherait à une école.
        $trajet->delete();
    }

    /** @param array<string, mixed> $donnees */
    public function ajouterArret(BusTrajet $trajet, array $donnees): BusArret
    {
        return BusArret::create([
            'trajet_id' => $trajet->id,
            'nom' => $donnees['nom'],
            'ordre' => $donnees['ordre'] ?? ($trajet->arrets()->max('ordre') + 1),
            'heure_passage' => $donnees['heure_passage'] ?? null,
        ]);
    }

    /** @param array<string, mixed> $donnees */
    public function modifierArret(BusArret $arret, array $donnees): BusArret
    {
        $arret->update($donnees);

        return $arret->fresh();
    }

    public function supprimerArret(BusArret $arret): void
    {
        $arret->delete();
    }

    // ---- Affectation des élèves ---------------------------------------

    /**
     * Élèves affectés au transport, ceux d'un trajet précis si `$trajetId`
     * est fourni.
     */
    public function listerAffectations(int $schoolId, ?int $trajetId = null): Collection
    {
        return BusAffectation::whereHas('trajet', fn ($q) => $q->forSchool($schoolId))
            ->when($trajetId, fn ($q, $id) => $q->where('trajet_id', $id))
            ->with(['eleve.classe', 'trajet', 'arret'])
            ->get();
    }

    /**
     * @param array<string, mixed> $donnees
     *
     * @throws RuntimeException si l'élève a déjà une affectation active.
     */
    public function affecterEleve(int $schoolId, array $donnees): BusAffectation
    {
        $eleve = Eleve::forSchool($schoolId)->findOrFail($donnees['eleve_id']);
        $trajet = BusTrajet::forSchool($schoolId)->findOrFail($donnees['trajet_id']);

        $dejaAffecte = BusAffectation::where('eleve_id', $eleve->id)->actives()->exists();
        if ($dejaAffecte) {
            throw new RuntimeException("{$eleve->nom_complet} est déjà affecté(e) à un trajet. Retirez d'abord l'affectation en cours.");
        }

        return $this->transaction(function () use ($eleve, $trajet, $donnees) {
            $affectation = BusAffectation::create([
                'eleve_id' => $eleve->id,
                'trajet_id' => $trajet->id,
                'arret_id' => $donnees['arret_id'] ?? null,
                'annee_scolaire_id' => $donnees['annee_scolaire_id'] ?? null,
                'tarif_mensuel' => $donnees['tarif_mensuel'] ?? null,
                'statut' => 'actif',
            ]);

            return $affectation->fresh(['eleve.classe', 'trajet', 'arret']);
        });
    }

    /** @param array<string, mixed> $donnees */
    public function modifierAffectation(BusAffectation $affectation, array $donnees): BusAffectation
    {
        $affectation->update($donnees);

        return $affectation->fresh(['eleve.classe', 'trajet', 'arret']);
    }

    public function retirerAffectation(BusAffectation $affectation): void
    {
        $affectation->delete();
    }

    // ---- Notifications ------------------------------------------------

    public const TYPES_NOTIFICATION = ['retard', 'incident', 'changement_itineraire', 'autre'];

    private const PREFIXES_NOTIFICATION = [
        'retard' => 'Retard bus',
        'incident' => 'Incident bus',
        'changement_itineraire' => "Changement d'itinéraire",
        'autre' => 'Info bus',
    ];

    /**
     * Alerte SMS à tous les parents des élèves actifs sur un trajet — retard,
     * incident, changement d'itinéraire. Les envois échoués n'interrompent
     * pas les suivants : un numéro invalide ne doit pas priver le reste des
     * familles de l'alerte.
     *
     * @return int nombre de SMS effectivement envoyés
     */
    public function notifierParents(BusTrajet $trajet, string $type, string $detail): int
    {
        $prefixe = self::PREFIXES_NOTIFICATION[$type] ?? self::PREFIXES_NOTIFICATION['autre'];
        $message = "{$prefixe} — {$trajet->nom} : {$detail}";

        $eleves = BusAffectation::where('trajet_id', $trajet->id)
            ->actives()
            ->with('eleve.tuteurs')
            ->get()
            ->pluck('eleve');

        $envoyes = 0;

        foreach ($eleves as $eleve) {
            $tuteur = $eleve->tuteurs->firstWhere('pivot.is_principal', true) ?? $eleve->tuteurs->first();

            if ($tuteur?->telephone && $this->sms->envoyer($tuteur->telephone, $message)) {
                $envoyes++;
            }
        }

        return $envoyes;
    }
}
