<?php

namespace Tests\Feature;

use App\Exports\BusSouscriptionExport;
use App\Imports\BusSouscriptionImport;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\BusTrajet;
use App\Models\School;
use App\Models\User;
use App\Services\BusPaiementService;
use App\Services\BusService;
use Database\Seeders\PlanComptableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BusSouscriptionImportTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private AnneeScolaire $annee;

    private BusTrajet $trajet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanComptableSeeder::class);

        $this->school = School::create(['name' => 'Elites Tech', 'code' => 'EBT', 'type' => 'secondaire', 'is_active' => true]);

        $this->annee = AnneeScolaire::create([
            'school_id' => $this->school->id,
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-07-31',
            'is_active' => true,
        ]);

        $this->trajet = BusTrajet::create([
            'school_id' => $this->school->id,
            'nom' => 'Ligne Nord',
            'tarif_aller_retour' => 15000,
        ]);
    }

    private function eleve(string $matricule, string $nom): Eleve
    {
        $classe = Classe::firstOrCreate(['school_id' => $this->school->id, 'nom' => 'ACCOUNTING 1-A']);

        return Eleve::create([
            'school_id' => $this->school->id,
            'classe_id' => $classe->id,
            'matricule' => $matricule,
            'nom_complet' => $nom,
            'sexe' => 'M',
            'statut' => 'actif',
        ]);
    }

    private function importer(Collection $lignes): BusSouscriptionImport
    {
        $admin = User::factory()->create();

        $import = new BusSouscriptionImport(
            $this->school->id,
            app(BusService::class),
            app(BusPaiementService::class),
            $admin->id,
        );

        $import->collection($lignes);

        return $import;
    }

    private function ligne(array $donnees): Collection
    {
        return collect($donnees);
    }

    public function test_importer_une_ligne_cree_une_souscription_et_un_versement(): void
    {
        $eleve = $this->eleve('23PRIM2', 'FOMESSO LIMA MARK JOEL');

        $import = $this->importer(collect([
            $this->ligne([
                'matricule' => $eleve->matricule,
                'nom' => $eleve->nom_complet,
                'bus' => 'Ligne Nord',
                'tarif' => 15000,
                'mois' => 'Sept',
                'mode' => 'cash',
            ]),
        ]));

        $this->assertSame(1, $import->versementsCrees);
        $this->assertSame(0, $import->versementsIgnores);
        $this->assertSame([], $import->erreurs);

        $this->assertDatabaseHas('bus_affectations', ['eleve_id' => $eleve->id, 'trajet_id' => $this->trajet->id, 'statut' => 'actif']);
        $this->assertDatabaseHas('bus_versements', ['montant' => 15000, 'mode' => 'especes']);
    }

    public function test_plusieurs_mois_du_meme_eleve_partagent_l_affectation_et_creent_des_versements_distincts(): void
    {
        $eleve = $this->eleve('23PRIM2', 'FOMESSO LIMA MARK JOEL');

        $import = $this->importer(collect([
            $this->ligne(['matricule' => $eleve->matricule, 'nom' => $eleve->nom_complet, 'bus' => 'Ligne Nord', 'tarif' => 15000, 'mois' => 'Sept']),
            $this->ligne(['matricule' => $eleve->matricule, 'nom' => $eleve->nom_complet, 'bus' => 'Ligne Nord', 'tarif' => 15000, 'mois' => 'Oct']),
        ]));

        $this->assertSame(2, $import->versementsCrees);
        $this->assertDatabaseCount('bus_affectations', 1);
        $this->assertDatabaseCount('bus_versements', 2);
    }

    public function test_l_affectation_est_retrodatee_au_premier_mois_du_groupe(): void
    {
        $eleve = $this->eleve('23PRIM2', 'FOMESSO LIMA MARK JOEL');

        $this->importer(collect([
            $this->ligne(['matricule' => $eleve->matricule, 'nom' => $eleve->nom_complet, 'bus' => 'Ligne Nord', 'tarif' => 15000, 'mois' => 'Oct']),
            $this->ligne(['matricule' => $eleve->matricule, 'nom' => $eleve->nom_complet, 'bus' => 'Ligne Nord', 'tarif' => 15000, 'mois' => 'Sept']),
        ]));

        $affectation = $eleve->fresh()->busAffectations()->first();
        $this->assertSame('2026-09-01', $affectation->created_at->toDateString());
    }

    public function test_reimporter_un_mois_deja_present_est_ignore_sans_erreur(): void
    {
        $eleve = $this->eleve('23PRIM2', 'FOMESSO LIMA MARK JOEL');

        $this->importer(collect([
            $this->ligne(['matricule' => $eleve->matricule, 'nom' => $eleve->nom_complet, 'bus' => 'Ligne Nord', 'tarif' => 15000, 'mois' => 'Sept']),
        ]));

        $import = $this->importer(collect([
            $this->ligne(['matricule' => $eleve->matricule, 'nom' => $eleve->nom_complet, 'bus' => 'Ligne Nord', 'tarif' => 15000, 'mois' => 'Sept']),
        ]));

        $this->assertSame(0, $import->versementsCrees);
        $this->assertSame(1, $import->versementsIgnores);
        $this->assertDatabaseCount('bus_versements', 1);
    }

    public function test_un_eleve_deja_affecte_a_un_autre_trajet_produit_une_erreur_sans_bloquer_le_reste(): void
    {
        $autreTrajet = BusTrajet::create(['school_id' => $this->school->id, 'nom' => 'Ligne Sud', 'tarif_aller_retour' => 12000]);
        $eleve1 = $this->eleve('23PRIM2', 'FOMESSO LIMA MARK JOEL');
        app(BusService::class)->affecterEleve($this->school->id, [
            'eleve_id' => $eleve1->id,
            'trajet_id' => $autreTrajet->id,
            'option_trajet' => 'aller_retour',
        ]);

        $eleve2 = $this->eleve('23PRIM3', 'AWA NDONGO CHRISTELLE');

        $import = $this->importer(collect([
            $this->ligne(['matricule' => $eleve1->matricule, 'nom' => $eleve1->nom_complet, 'bus' => 'Ligne Nord', 'tarif' => 15000, 'mois' => 'Sept']),
            $this->ligne(['matricule' => $eleve2->matricule, 'nom' => $eleve2->nom_complet, 'bus' => 'Ligne Nord', 'tarif' => 15000, 'mois' => 'Sept']),
        ]));

        $this->assertSame(1, $import->versementsCrees);
        $this->assertCount(1, $import->erreurs);
        $this->assertStringContainsString('déjà affecté', $import->erreurs[0]['message']);
    }

    public function test_un_trajet_inconnu_produit_une_erreur_pour_toutes_les_lignes_du_groupe(): void
    {
        $eleve = $this->eleve('23PRIM2', 'FOMESSO LIMA MARK JOEL');

        $import = $this->importer(collect([
            $this->ligne(['matricule' => $eleve->matricule, 'nom' => $eleve->nom_complet, 'bus' => 'Ligne Inconnue', 'tarif' => 15000, 'mois' => 'Sept']),
            $this->ligne(['matricule' => $eleve->matricule, 'nom' => $eleve->nom_complet, 'bus' => 'Ligne Inconnue', 'tarif' => 15000, 'mois' => 'Oct']),
        ]));

        $this->assertSame(0, $import->versementsCrees);
        $this->assertCount(2, $import->erreurs);
    }

    private function admin(): User
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $admin = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $admin->assignRole('super_admin');

        return $admin;
    }

    public function test_l_endpoint_export_retourne_un_fichier(): void
    {
        $response = $this->actingAs($this->admin(), 'sanctum')->get('/api/v1/bus/affectations/export');

        $response->assertOk();
    }

    public function test_l_endpoint_modele_retourne_un_fichier(): void
    {
        $response = $this->actingAs($this->admin(), 'sanctum')->get('/api/v1/bus/affectations/modele');

        $response->assertOk();
    }
}
