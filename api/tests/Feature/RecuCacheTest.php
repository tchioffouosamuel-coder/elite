<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\DossierScolarite;
use App\Models\Eleve;
use App\Models\School;
use App\Models\User;
use App\Models\Versement;
use App\Models\VersementLigne;
use App\Support\Pdf\CacheRendus;
use App\Support\Pdf\RecuVersementGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Mémorisation des reçus composés (cf. {@see CacheRendus}) : réimprimer un
 * ticket au guichet doit relire un fichier, pas refaire travailler mPDF —
 * mais jamais au prix d'un ticket périmé.
 */
class RecuCacheTest extends TestCase
{
    use RefreshDatabase;

    private School $ecole;

    private DossierScolarite $dossier;

    protected function setUp(): void
    {
        parent::setUp();

        File::deleteDirectory(CacheRendus::dossier());

        $this->ecole = School::create(['name' => 'Elites Tech', 'code' => 'EBT', 'type' => 'secondaire', 'is_active' => true]);
        $annee = AnneeScolaire::create([
            'school_id' => $this->ecole->id, 'libelle' => '2026-2027',
            'date_debut' => '2026-09-01', 'date_fin' => '2027-07-15', 'is_active' => true,
        ]);
        $classe = Classe::create(['school_id' => $this->ecole->id, 'nom' => 'ACCOUNTING 1-A']);
        $eleve = Eleve::create([
            'school_id' => $this->ecole->id, 'classe_id' => $classe->id, 'matricule' => '23PRIM2',
            'nom_complet' => 'FOMESSO LIMA MARK JOEL', 'sexe' => 'M', 'statut' => 'actif',
        ]);
        $this->dossier = DossierScolarite::create([
            'school_id' => $this->ecole->id, 'annee_scolaire_id' => $annee->id,
            'eleve_id' => $eleve->id, 'montant_scolarite' => 164500,
        ]);
    }

    protected function tearDown(): void
    {
        // Le cache vit dans le vrai `storage` : ne rien y laisser.
        File::deleteDirectory(CacheRendus::dossier());

        parent::tearDown();
    }

    private function versement(string $numero, string $date, int $montant): Versement
    {
        $versement = Versement::create([
            'school_id' => $this->ecole->id, 'dossier_scolarite_id' => $this->dossier->id,
            'numero_recu' => $numero, 'date_versement' => $date, 'montant' => $montant, 'mode' => 'especes',
        ]);
        VersementLigne::create(['versement_id' => $versement->id, 'affectation' => 'scolarite', 'libelle' => 'Scolarité', 'montant' => $montant]);

        return $versement;
    }

    public function test_un_recu_reimprime_est_servi_a_l_identique_depuis_le_cache(): void
    {
        $versement = $this->versement('RC-EBT-0001', '2026-09-26', 50000);

        $premier = (new RecuVersementGenerator)->build($versement);
        $second = (new RecuVersementGenerator)->build($versement->fresh());

        $this->assertStringStartsWith('%PDF-', $premier);
        $this->assertSame($premier, $second);
        $this->assertNotEmpty(File::allFiles(CacheRendus::dossier()));
    }

    /**
     * L'historique des versements et le reste à payer sont imprimés sur le
     * ticket : un encaissement de plus sur le dossier change le reçu des
     * précédents, qui ne doit donc plus sortir du cache tel quel.
     */
    public function test_un_nouveau_versement_sur_le_dossier_perime_le_recu_memorise(): void
    {
        $versement = $this->versement('RC-EBT-0001', '2026-09-26', 50000);
        $avant = (new RecuVersementGenerator)->build($versement);

        $this->versement('RC-EBT-0002', '2026-10-12', 30000);

        $this->assertNotSame($avant, (new RecuVersementGenerator)->build($versement->fresh()));
    }

    /** Un reçu annulé porte la mention d'annulation : il ne doit pas rester en cache sans elle. */
    public function test_l_annulation_perime_le_recu_memorise(): void
    {
        $versement = $this->versement('RC-EBT-0001', '2026-09-26', 50000);
        $avant = (new RecuVersementGenerator)->build($versement);

        $versement->update(['annule_le' => now(), 'motif_annulation' => 'Erreur de saisie']);

        $this->assertNotSame($avant, (new RecuVersementGenerator)->build($versement->fresh()));
    }

    /**
     * Rouvrir le même reçu ne doit pas retransférer le ticket : l'ETag
     * permet au navigateur de réutiliser sa copie. La revalidation reste
     * obligatoire — le poste du guichet est partagé, chaque ouverture repasse
     * par le contrôle de privilèges.
     */
    public function test_la_route_du_recu_repond_304_quand_le_navigateur_a_deja_le_ticket(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = User::create(['name' => 'Root', 'email' => 'root@test.local', 'password' => 'password', 'school_id' => null, 'is_active' => true]);
        $admin->assignRole('super_admin');

        $versement = $this->versement('RC-EBT-0001', '2026-09-26', 50000);
        $url = "/api/v1/versements/{$versement->id}/recu";

        $premier = $this->actingAs($admin, 'sanctum')->get($url)->assertOk();
        $etag = $premier->headers->get('ETag');

        $this->assertNotNull($etag);
        $this->assertStringContainsString('must-revalidate', (string) $premier->headers->get('Cache-Control'));

        $this->actingAs($admin, 'sanctum')
            ->get($url, ['If-None-Match' => $etag])
            ->assertStatus(304);
    }
}
