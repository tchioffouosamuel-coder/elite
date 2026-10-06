<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\BusAffectation;
use App\Models\BusArret;
use App\Models\BusTrajet;
use App\Models\BusVehicule;
use App\Models\BusVersement;
use App\Models\Classe;
use App\Models\Depense;
use App\Models\Eleve;
use App\Models\FonctionReferentiel;
use App\Models\Personnel;
use App\Models\Remuneration;
use App\Models\School;
use App\Models\Tuteur;
use App\Models\User;
use App\Support\CataloguePermissions;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * L'espace chauffeur : sa tournée, et rien d'autre.
 *
 * Ce qui se joue ici — le chauffeur porte `bus.view`, qui ouvrirait sinon la
 * flotte entière : chaque réponse doit rester bornée aux enfants de SON bus,
 * et les gestes de gestion (souscrire un élève, retoucher un trajet) lui
 * rester fermés. Plus le relais d'itinéraire quand il est empêché, qui est la
 * seule écriture structurelle qu'il peut déclencher.
 */
class ChauffeurEspaceTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private AnneeScolaire $annee;

    private Classe $classe;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }

        $this->school = School::create(['name' => 'Elites Tech', 'code' => 'EBT', 'type' => 'secondaire', 'is_active' => true]);

        $this->annee = AnneeScolaire::create([
            'school_id' => $this->school->id,
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-07-31',
            'is_active' => true,
        ]);

        $this->classe = Classe::create(['school_id' => $this->school->id, 'nom' => '6e M']);
    }

    public function test_le_tableau_de_bord_compte_son_effectif_et_la_rentabilite_du_mois(): void
    {
        [$user, $chauffeur] = $this->chauffeur('awono@test.local', 'Jean Awono');
        Remuneration::create([
            'school_id' => $this->school->id,
            'personnel_id' => $chauffeur->id,
            'date_effet' => '2026-09-01',
            'mode' => 'mensuel',
            'salaire_base' => 90_000,
        ]);

        $bus = $this->vehicule($chauffeur, 'LT-100-AA');
        $trajet = $this->trajet($bus, 'Ligne Nord');
        $arret = $this->arret($trajet, 'Carrefour Obili', 1);

        $a1 = $this->souscription($this->eleve('E1', 'Ngo Bell Marie'), $trajet, $arret);
        $this->souscription($this->eleve('E2', 'Abena Paul'), $trajet, $arret);

        // Réglé POUR le mois en cours : c'est ce que la rentabilité du mois
        // retient, et non la date d'encaissement.
        $this->versement($a1, montant: 15_000, mois: now()->startOfMonth()->toDateString());
        // Réglé ce mois-ci mais POUR le mois prochain : hors du bilan du mois.
        $this->versement($a1, montant: 15_000, mois: now()->addMonthNoOverflow()->startOfMonth()->toDateString());

        $this->depense($bus, 4_000, now()->toDateString());
        $this->depense($bus, 7_000, now()->subMonthNoOverflow()->toDateString());

        $reponse = $this->actingAs($user)->getJson('/api/v1/chauffeur/tableau-de-bord')->assertOk();

        $reponse->assertJsonPath('data.effectif_transporte', 2);
        $reponse->assertJsonPath('data.nombre_trajets', 1);
        $reponse->assertJsonPath('data.rentabilite.recettes', 15_000);
        $reponse->assertJsonPath('data.rentabilite.depenses', 4_000);
        $reponse->assertJsonPath('data.rentabilite.salaire', 90_000);
        $reponse->assertJsonPath('data.rentabilite.resultat', 15_000 - (4_000 + 90_000));
        $reponse->assertJsonPath('data.vehicules.0.immatriculation', 'LT-100-AA');
    }

    public function test_les_depenses_et_profils_restent_limites_a_ses_bus(): void
    {
        [$user, $chauffeur] = $this->chauffeur('bord@test.local', 'Jean Bord');
        [, $autre] = $this->chauffeur('autre@test.local', 'Autre Chauffeur');
        $bus = $this->vehicule($chauffeur, 'LT-110-AA');
        $autreBus = $this->vehicule($autre, 'LT-111-AA');
        $trajet = $this->trajet($bus, 'Ligne du chauffeur');
        $eleve = $this->eleve('PHOTO1', 'Marie Photo');
        $eleve->update(['photo_path' => 'eleves/marie.jpg']);
        $this->souscription($eleve, $trajet, $this->arret($trajet, 'Mon arret', 1));
        $horsTournee = $this->eleve('PHOTO2', 'Autre Eleve');

        $this->depense($bus, 4000, now()->toDateString());
        $this->depense($bus, 7000, now()->subMonthNoOverflow()->toDateString());
        $this->depense($autreBus, 9000, now()->toDateString());
        $this->depense($bus, 5000, now()->toDateString())->update(['statut' => 'annulee']);

        $this->actingAs($user)->getJson('/api/v1/chauffeur/depenses')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.montant', 4000);
        $this->getJson('/api/v1/chauffeur/eleves')
            ->assertOk()->assertJsonPath('data.0.photo_url', asset('storage/eleves/marie.jpg'));
        $this->getJson('/api/v1/chauffeur/eleves/' . $eleve->id)
            ->assertOk()->assertJsonPath('data.id', $eleve->id)->assertJsonPath('data.classe.nom', '6e M');
        $this->getJson('/api/v1/chauffeur/eleves/' . $horsTournee->id)->assertNotFound();
    }

    public function test_l_itineraire_liste_les_enfants_par_arret_avec_le_contact_des_parents(): void
    {
        [$user, $chauffeur] = $this->chauffeur('mballa@test.local', 'Paul Mballa');
        $bus = $this->vehicule($chauffeur, 'LT-200-BB');
        $trajet = $this->trajet($bus, 'Ligne Est');
        $premier = $this->arret($trajet, 'Mvog-Ada', 1);
        $second = $this->arret($trajet, 'Nkolndongo', 2);

        $eleve = $this->eleve('E10', 'Tchoupo Alice');
        $this->souscription($eleve, $trajet, $premier);
        $this->souscription($this->eleve('E11', 'Biya Serge'), $trajet, $second);

        $tuteur = Tuteur::create([
            'school_id' => $this->school->id,
            'nom_complet' => 'Mme Tchoupo',
            'telephone' => '699000111',
        ]);
        $eleve->tuteurs()->attach($tuteur->id, ['lien_parente' => 'mere', 'is_principal' => true]);

        $reponse = $this->actingAs($user)->getJson('/api/v1/chauffeur/itineraire?sens=aller')->assertOk();

        $reponse->assertJsonPath('data.effectif', 2);
        $reponse->assertJsonPath('data.pris', 0);
        $reponse->assertJsonPath('data.trajets.0.arrets.0.nom', 'Mvog-Ada');
        $reponse->assertJsonPath('data.trajets.0.arrets.0.eleves.0.nom_complet', 'Tchoupo Alice');
        $reponse->assertJsonPath('data.trajets.0.arrets.0.eleves.0.tuteurs.0.telephone', '699000111');
        $reponse->assertJsonPath('data.trajets.0.arrets.1.eleves.0.nom_complet', 'Biya Serge');
    }

    public function test_il_coche_un_enfant_pris_en_charge_puis_le_decoche(): void
    {
        [$user, $chauffeur] = $this->chauffeur('nana@test.local', 'Luc Nana');
        $bus = $this->vehicule($chauffeur, 'LT-300-CC');
        $trajet = $this->trajet($bus, 'Ligne Sud');
        $arret = $this->arret($trajet, 'Mendong', 1);
        $affectation = $this->souscription($this->eleve('E20', 'Essomba Rita'), $trajet, $arret);

        $this->actingAs($user)
            ->postJson('/api/v1/chauffeur/ramassages', ['affectation_id' => $affectation->id, 'pris' => true])
            ->assertOk()
            ->assertJsonPath('data.pris', true);

        $this->assertDatabaseCount('bus_ramassages', 1);

        // Rejouer le même pointage (double tap, réseau qui rejoue) ne doit pas
        // créer un second ramassage.
        $this->actingAs($user)
            ->postJson('/api/v1/chauffeur/ramassages', ['affectation_id' => $affectation->id, 'pris' => true])
            ->assertOk();

        $this->assertDatabaseCount('bus_ramassages', 1);

        $this->actingAs($user)->getJson('/api/v1/chauffeur/itineraire')
            ->assertOk()
            ->assertJsonPath('data.pris', 1)
            ->assertJsonPath('data.trajets.0.arrets.0.eleves.0.pris', true);

        $this->actingAs($user)
            ->postJson('/api/v1/chauffeur/ramassages', ['affectation_id' => $affectation->id, 'pris' => false])
            ->assertOk()
            ->assertJsonPath('data.pris', false);

        $this->assertDatabaseCount('bus_ramassages', 0);
    }

    public function test_il_ne_peut_pas_pointer_un_enfant_d_un_autre_bus(): void
    {
        [$user, $chauffeur] = $this->chauffeur('ekoto@test.local', 'Yves Ekoto');
        $this->vehicule($chauffeur, 'LT-400-DD');

        [, $autreChauffeur] = $this->chauffeur('zoa@test.local', 'Marc Zoa');
        $autreBus = $this->vehicule($autreChauffeur, 'LT-500-EE');
        $autreTrajet = $this->trajet($autreBus, 'Ligne Ouest');
        $autreArret = $this->arret($autreTrajet, 'Biyem-Assi', 1);
        $affectation = $this->souscription($this->eleve('E30', 'Kamga Ines'), $autreTrajet, $autreArret);

        $this->actingAs($user)
            ->postJson('/api/v1/chauffeur/ramassages', ['affectation_id' => $affectation->id, 'pris' => true])
            ->assertNotFound();

        $this->assertDatabaseCount('bus_ramassages', 0);
    }

    public function test_il_ne_peut_ni_souscrire_un_eleve_ni_modifier_un_trajet_ou_un_arret(): void
    {
        [$user, $chauffeur] = $this->chauffeur('fouda@test.local', 'Eric Fouda');
        $bus = $this->vehicule($chauffeur, 'LT-600-FF');
        $trajet = $this->trajet($bus, 'Ligne Centre');
        $arret = $this->arret($trajet, 'Etoa-Meki', 1);
        $eleve = $this->eleve('E40', 'Manga Yves');

        $this->actingAs($user)->postJson('/api/v1/bus/affectations', [
            'eleve_id' => $eleve->id,
            'trajet_id' => $trajet->id,
            'arret_id' => $arret->id,
            'option_trajet' => 'aller_retour',
        ])->assertForbidden();

        $this->actingAs($user)->putJson("/api/v1/bus/trajets/{$trajet->id}", ['nom' => 'Renommé'])->assertForbidden();

        $this->actingAs($user)->putJson("/api/v1/bus/trajets/{$trajet->id}/arrets/{$arret->id}", [
            'nom' => 'Ailleurs',
        ])->assertForbidden();
    }

    public function test_un_chauffeur_empeche_rend_son_itineraire_disponible_et_un_collegue_le_reprend(): void
    {
        [$titulaireUser, $titulaire] = $this->chauffeur('mbida@test.local', 'Alain Mbida');
        $bus = $this->vehicule($titulaire, 'LT-700-GG');
        $trajet = $this->trajet($bus, 'Ligne Soa');
        $arret = $this->arret($trajet, 'Soa Village', 1);
        $this->souscription($this->eleve('E50', 'Ayissi Carine'), $trajet, $arret);

        [$remplacantUser, ] = $this->chauffeur('tsala@test.local', 'Hervé Tsala');

        $empechement = $this->actingAs($titulaireUser)->postJson('/api/v1/chauffeur/empechements', [
            'vehicule_id' => $bus->id,
            'du' => now()->toDateString(),
            'au' => now()->addDays(2)->toDateString(),
            'motif' => 'Hospitalisation',
        ])->assertCreated()->json('data');

        $this->assertSame('disponible', $empechement['statut']);

        // Le bus sort de la tournée du titulaire dès que l'itinéraire est confié.
        $this->actingAs($titulaireUser)->getJson('/api/v1/chauffeur/tableau-de-bord')
            ->assertOk()
            ->assertJsonPath('data.effectif_transporte', 0);

        // Et s'offre au collègue, qui le voit sur son accueil.
        $this->actingAs($remplacantUser)->getJson('/api/v1/chauffeur/tableau-de-bord')
            ->assertOk()
            ->assertJsonPath('data.itineraires_disponibles.0.id', $empechement['id']);

        $this->actingAs($remplacantUser)
            ->postJson("/api/v1/chauffeur/empechements/{$empechement['id']}/reprendre")
            ->assertOk()
            ->assertJsonPath('data.statut', 'pourvu');

        // Repris : les enfants du circuit apparaissent dans sa tournée.
        $this->actingAs($remplacantUser)->getJson('/api/v1/chauffeur/itineraire')
            ->assertOk()
            ->assertJsonPath('data.effectif', 1)
            ->assertJsonPath('data.trajets.0.arrets.0.eleves.0.nom_complet', 'Ayissi Carine');

        // Plus disponible : un troisième chauffeur ne peut plus le prendre.
        [$troisiemeUser, ] = $this->chauffeur('onana@test.local', 'Serge Onana');
        $this->actingAs($troisiemeUser)
            ->postJson("/api/v1/chauffeur/empechements/{$empechement['id']}/reprendre")
            ->assertStatus(422);

        // Empêchement levé : le titulaire retrouve son bus.
        $this->actingAs($titulaireUser)
            ->postJson("/api/v1/chauffeur/empechements/{$empechement['id']}/annuler")
            ->assertOk()
            ->assertJsonPath('data.statut', 'annule');

        $this->actingAs($titulaireUser)->getJson('/api/v1/chauffeur/tableau-de-bord')
            ->assertOk()
            ->assertJsonPath('data.effectif_transporte', 1);
    }

    public function test_il_ne_peut_pas_confier_l_itineraire_d_un_bus_qui_n_est_pas_le_sien(): void
    {
        [$user, $chauffeur] = $this->chauffeur('belinga@test.local', 'Rémy Belinga');
        $this->vehicule($chauffeur, 'LT-800-HH');

        [, $autre] = $this->chauffeur('ngono@test.local', 'Paule Ngono');
        $autreBus = $this->vehicule($autre, 'LT-900-II');

        $this->actingAs($user)->postJson('/api/v1/chauffeur/empechements', [
            'vehicule_id' => $autreBus->id,
            'du' => now()->toDateString(),
            'au' => now()->addDay()->toDateString(),
        ])->assertStatus(422);
    }

    public function test_la_direction_confie_l_itineraire_d_un_chauffeur_injoignable(): void
    {
        [, $titulaire] = $this->chauffeur('atangana@test.local', 'Luc Atangana');
        $bus = $this->vehicule($titulaire, 'LT-910-JJ');
        $trajet = $this->trajet($bus, 'Ligne Nkoabang');
        $arret = $this->arret($trajet, 'Nkoabang', 1);
        $this->souscription($this->eleve('E60', 'Owona Lea'), $trajet, $arret);

        [$remplacantUser, $remplacant] = $this->chauffeur('bikoi@test.local', 'Jean Bikoi');
        $direction = $this->direction();

        $relais = $this->actingAs($direction)->postJson('/api/v1/bus/remplacements', [
            'vehicule_id' => $bus->id,
            'du' => now()->toDateString(),
            'au' => now()->addDays(5)->toDateString(),
            'motif' => 'Chauffeur injoignable',
            'chauffeur_remplacant_id' => $remplacant->id,
        ])->assertCreated()->json('data');

        $this->assertSame('pourvu', $relais['statut']);

        $this->actingAs($remplacantUser)->getJson('/api/v1/chauffeur/itineraire')
            ->assertOk()
            ->assertJsonPath('data.effectif', 1);

        $this->actingAs($direction)->getJson('/api/v1/bus/remplacements')
            ->assertOk()
            ->assertJsonPath('data.0.id', $relais['id']);
    }

    // ---- Décors ------------------------------------------------------

    /** @return array{0: User, 1: Personnel} */
    private function chauffeur(string $email, string $nom): array
    {
        $fonction = FonctionReferentiel::firstOrCreate([
            'school_id' => $this->school->id,
            'label_fr' => 'Chauffeur',
        ]);
        $fonction->synchroniserPermissions(RolePermissionSeeder::permissionsDuRole('chauffeur'));

        $user = User::create([
            'name' => $nom, 'email' => $email, 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);

        $personnel = Personnel::create([
            'school_id' => $this->school->id,
            'user_id' => $user->id,
            'fonction_id' => $fonction->id,
            'nom_complet' => $nom,
            'sexe' => 'M',
            'statut' => 'actif',
        ]);

        return [$user->fresh(), $personnel];
    }

    private function direction(): User
    {
        $role = Role::firstOrCreate(['name' => 'admin_college', 'guard_name' => 'web']);
        $role->syncPermissions(RolePermissionSeeder::permissionsDuRole('admin_college'));

        $user = User::create([
            'name' => 'Principal', 'email' => 'principal@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $user->assignRole($role);

        return $user->fresh();
    }

    private function vehicule(Personnel $chauffeur, string $immatriculation): BusVehicule
    {
        return BusVehicule::create([
            'school_id' => null,
            'immatriculation' => $immatriculation,
            'marque' => 'Toyota Coaster',
            'capacite' => 30,
            'chauffeur_id' => $chauffeur->id,
            'statut' => 'actif',
        ]);
    }

    private function trajet(BusVehicule $bus, string $nom): BusTrajet
    {
        return BusTrajet::create([
            'school_id' => null,
            'vehicule_id' => $bus->id,
            'nom' => $nom,
            'tarif_aller_retour' => 15_000,
        ]);
    }

    private function arret(BusTrajet $trajet, string $nom, int $ordre): BusArret
    {
        return BusArret::create([
            'trajet_id' => $trajet->id,
            'nom' => $nom,
            'ordre' => $ordre,
            'tarif_aller_retour' => 15_000,
        ]);
    }

    private function eleve(string $matricule, string $nomComplet): Eleve
    {
        return Eleve::create([
            'school_id' => $this->school->id,
            'classe_id' => $this->classe->id,
            'matricule' => $matricule,
            'nom_complet' => $nomComplet,
            'sexe' => 'F',
            'statut' => 'actif',
        ]);
    }

    private function souscription(Eleve $eleve, BusTrajet $trajet, BusArret $arret): BusAffectation
    {
        return BusAffectation::create([
            'eleve_id' => $eleve->id,
            'trajet_id' => $trajet->id,
            'arret_id' => $arret->id,
            'annee_scolaire_id' => $this->annee->id,
            'tarif_mensuel' => 15_000,
            'option_trajet' => 'aller_retour',
            'statut' => 'actif',
        ]);
    }

    private function versement(BusAffectation $affectation, int $montant, string $mois): BusVersement
    {
        return BusVersement::create([
            'school_id' => $this->school->id,
            'bus_affectation_id' => $affectation->id,
            'mois' => $mois,
            'numero_recu' => 'BUS-' . uniqid(),
            'date_versement' => now()->toDateString(),
            'montant' => $montant,
            'mode' => 'especes',
        ]);
    }

    private function depense(BusVehicule $bus, int $montant, string $date): Depense
    {
        return Depense::create([
            'school_id' => $this->school->id,
            'annee_scolaire_id' => $this->annee->id,
            'vehicule_id' => $bus->id,
            'date_depense' => $date,
            'libelle' => 'Carburant',
            'montant' => $montant,
            'statut' => 'payee',
        ]);
    }
}
