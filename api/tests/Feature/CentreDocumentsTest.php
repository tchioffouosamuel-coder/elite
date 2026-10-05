<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\DossierScolarite;
use App\Models\Eleve;
use App\Models\Preinscription;
use App\Models\School;
use App\Models\User;
use App\Models\Versement;
use App\Support\Documents\CatalogueDocuments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use ZipArchive;

class CentreDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private School $ecoleA;

    private School $ecoleB;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->admin = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => null, 'is_active' => true,
        ]);
        $this->admin->assignRole('super_admin');

        $this->ecoleA = School::create(['name' => 'Elites Secondaire', 'code' => 'ES', 'type' => 'secondaire', 'is_active' => true]);
        $this->ecoleB = School::create(['name' => 'Elites Primaire', 'code' => 'EP', 'type' => 'primaire', 'is_active' => true]);

        foreach ([$this->ecoleA, $this->ecoleB] as $ecole) {
            AnneeScolaire::create([
                'school_id' => $ecole->id, 'libelle' => '2026-2027',
                'date_debut' => '2026-09-01', 'date_fin' => '2027-07-15', 'is_active' => true,
            ]);
        }
    }

    protected function tearDown(): void
    {
        // Les paquets sont écrits dans le vrai `storage` : ne rien y laisser.
        \Illuminate\Support\Facades\File::deleteDirectory(storage_path('app/private/paquets-documents'));

        parent::tearDown();
    }

    /**
     * Garde-fou de l'inventaire : chaque document déclaré doit viser une
     * route GET existante — un chemin renommé ailleurs dans l'API casserait
     * sinon le centre de documents sans que rien ne le signale.
     */
    public function test_chaque_document_du_catalogue_vise_une_route_existante(): void
    {
        $inconnues = [];

        foreach (CatalogueDocuments::documents() as $code => $definition) {
            foreach ($definition['formats'] as $format => $chemin) {
                $uri = '/api/v1/'.preg_replace('/\{\w+\}/', '1', explode('?', $chemin)[0]);

                try {
                    Route::getRoutes()->match(Request::create($uri, 'GET'));
                } catch (\Throwable) {
                    $inconnues[] = "{$code} ({$format}) → {$uri}";
                }
            }
        }

        $this->assertSame([], $inconnues);
    }

    public function test_le_catalogue_decrit_la_hierarchie_et_les_documents(): void
    {
        $classe = Classe::create(['school_id' => $this->ecoleA->id, 'nom' => '6e A']);

        $reponse = $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/documents/catalogue')->assertOk();

        $this->assertGreaterThan(80, count($reponse->json('data.documents')));
        $ecole = collect($reponse->json('data.ecoles'))->firstWhere('id', $this->ecoleA->id);
        $this->assertSame($classe->id, $ecole['classes'][0]['id']);
    }

    public function test_la_simulation_compte_un_fichier_par_classe_et_par_ecole(): void
    {
        Classe::create(['school_id' => $this->ecoleA->id, 'nom' => '6e A']);
        Classe::create(['school_id' => $this->ecoleA->id, 'nom' => '6e B']);
        Classe::create(['school_id' => $this->ecoleB->id, 'nom' => 'CM2']);

        $reponse = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/documents/paquets', [
            'simulation' => true,
            'documents' => [
                ['code' => 'liste_classe', 'formats' => ['pdf', 'excel']],
                ['code' => 'liste_classes', 'formats' => ['excel']],
                // Réservé au secondaire : l'école primaire n'en produit pas.
                ['code' => 'bulletins_classe', 'formats' => ['pdf']],
            ],
        ])->assertOk();

        $this->assertSame(3 * 2 + 2 + 2, $reponse->json('data.total'));
        $this->assertNull($reponse->json('data.token'));
    }

    public function test_un_document_decole_filtrable_descend_a_la_classe_quand_le_perimetre_le_demande(): void
    {
        $classe = Classe::create(['school_id' => $this->ecoleA->id, 'nom' => '6e A']);
        Classe::create(['school_id' => $this->ecoleA->id, 'nom' => '6e B']);

        $reponse = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/documents/paquets', [
            'simulation' => true,
            'perimetre' => ['school_id' => $this->ecoleA->id, 'classe_id' => $classe->id],
            'documents' => [['code' => 'insolvables', 'formats' => ['pdf']], ['code' => 'liste_classes', 'formats' => ['excel']]],
        ])->assertOk();

        // Insolvables : un fichier pour LA classe ; liste des classes : un pour l'école.
        $this->assertSame(2, $reponse->json('data.total'));
        $this->assertStringContainsString('6e A', $reponse->json('data.apercu.0'));
    }

    public function test_un_parametre_obligatoire_manquant_est_signale(): void
    {
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/documents/paquets', [
            'documents' => [['code' => 'bilan_depenses', 'formats' => ['pdf']]],
        ])->assertStatus(422);
    }

    public function test_un_paquet_est_produit_par_lots_puis_telecharge_en_zip(): void
    {
        $classe = Classe::create(['school_id' => $this->ecoleA->id, 'nom' => '6e A']);
        Eleve::create([
            'school_id' => $this->ecoleA->id, 'classe_id' => $classe->id, 'matricule' => 'E1',
            'nom_complet' => 'Ngono Paul', 'sexe' => 'M', 'statut' => 'actif',
        ]);

        $token = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/documents/paquets', [
            'perimetre' => ['school_id' => $this->ecoleA->id],
            'documents' => [
                ['code' => 'liste_classe', 'formats' => ['excel']],
                ['code' => 'liste_classes', 'formats' => ['excel']],
            ],
        ])->assertOk()->json('data.token');

        $etat = $this->actingAs($this->admin, 'sanctum')->postJson("/api/v1/documents/paquets/{$token}/traiter")->assertOk();
        $this->assertTrue($etat->json('data.termine'));
        $this->assertSame(2, $etat->json('data.fichiers'), (string) $etat->json('data.derniere_erreur'));

        $reponse = $this->actingAs($this->admin, 'sanctum')->get("/api/v1/documents/paquets/{$token}/telecharger")->assertOk();

        $zip = new ZipArchive;
        $zip->open($reponse->baseResponse->getFile()->getPathname());
        $noms = collect(range(0, $zip->numFiles - 1))->map(fn ($i) => $zip->getNameIndex($i));
        $zip->close();

        $this->assertContains('Elites Secondaire/6e A/Liste des élèves de la classe - 6e A.xlsx', $noms->all());
        $this->assertContains('Elites Secondaire/Liste des classes.xlsx', $noms->all());
    }

    public function test_un_paquet_dun_seul_fichier_est_rendu_sans_archive(): void
    {
        Classe::create(['school_id' => $this->ecoleA->id, 'nom' => '6e A']);

        $token = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/documents/paquets', [
            'perimetre' => ['school_id' => $this->ecoleA->id],
            'documents' => [['code' => 'liste_classes', 'formats' => ['excel']]],
        ])->json('data.token');

        $this->actingAs($this->admin, 'sanctum')->postJson("/api/v1/documents/paquets/{$token}/traiter")->assertOk();

        $reponse = $this->actingAs($this->admin, 'sanctum')->get("/api/v1/documents/paquets/{$token}/telecharger")->assertOk();
        $this->assertStringContainsString('Liste des classes.xlsx', rawurldecode((string) $reponse->headers->get('Content-Disposition')));
    }

    public function test_le_centre_de_documents_est_reserve_au_super_admin(): void
    {
        $agent = User::create([
            'name' => 'Agent', 'email' => 'agent@test.local', 'password' => 'password',
            'school_id' => $this->ecoleA->id, 'is_active' => true,
        ]);

        $this->actingAs($agent, 'sanctum')->getJson('/api/v1/documents/catalogue')->assertForbidden();
    }

    /**
     * Régression relevée par la revue du centre de documents : le
     * générateur appelait une méthode `francs()` inexistante et n'écrivait
     * jamais son HTML — aucun reçu de préinscription ne pouvait sortir.
     */
    public function test_le_recu_de_preinscription_produit_un_pdf(): void
    {
        $classe = Classe::create(['school_id' => $this->ecoleA->id, 'nom' => '6e A']);
        $eleve = Eleve::create([
            'school_id' => $this->ecoleA->id, 'classe_id' => $classe->id, 'matricule' => 'E1',
            'nom_complet' => 'Ngono Paul', 'sexe' => 'M', 'statut' => 'actif',
        ]);
        $annee = AnneeScolaire::where('school_id', $this->ecoleA->id)->first();
        $dossier = DossierScolarite::create(['school_id' => $this->ecoleA->id, 'annee_scolaire_id' => $annee->id, 'eleve_id' => $eleve->id]);
        $versement = Versement::create([
            'school_id' => $this->ecoleA->id, 'dossier_scolarite_id' => $dossier->id, 'numero_recu' => 'R-1',
            'date_versement' => '2026-10-01', 'montant' => 25000, 'mode' => 'especes',
        ]);
        $preinscription = Preinscription::create([
            'school_id' => $this->ecoleA->id, 'annee_scolaire_id' => $annee->id, 'eleve_id' => $eleve->id,
            'type' => 'existant', 'statut' => 'validee', 'donnees_eleve' => [], 'donnees_tuteurs' => [], 'versement_id' => $versement->id,
        ]);

        $reponse = $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-School-Id', (string) $this->ecoleA->id)
            ->get("/api/v1/preinscriptions/{$preinscription->id}/recu")
            ->assertOk();

        $this->assertStringStartsWith('%PDF', $reponse->getContent());
    }

    /**
     * Régression : sans aucune photo exploitable, l'archive vide n'était
     * jamais écrite et sa lecture levait une erreur 500 au lieu du refus
     * prévu par le contrôleur.
     */
    public function test_larchive_photos_sans_photo_rend_le_refus_metier(): void
    {
        $classe = Classe::create(['school_id' => $this->ecoleA->id, 'nom' => '3e A', 'code_examen' => 'BEPC-01']);
        Eleve::create([
            'school_id' => $this->ecoleA->id, 'classe_id' => $classe->id, 'matricule' => 'E1',
            'nom_complet' => 'Ngono Paul', 'sexe' => 'M', 'statut' => 'actif',
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-School-Id', (string) $this->ecoleA->id)
            ->getJson("/api/v1/photos-examen/classes/{$classe->id}/archive")
            ->assertStatus(422)
            ->assertJsonPath('message', "Aucune photo exploitable dans cette classe. Chargez les photos des candidats avant de générer l'archive.");
    }
}
