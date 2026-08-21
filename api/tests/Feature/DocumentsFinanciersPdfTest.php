<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\CompteComptable;
use App\Models\EcritureComptable;
use App\Models\Personnel;
use App\Models\Remuneration;
use App\Models\School;
use App\Services\Comptabilite\EtatSyntheseService;
use App\Services\Paie\BordereauVirementService;
use App\Services\PaieService;
use App\Support\Pdf\BordereauVirementGenerator;
use App\Support\Pdf\BulletinPaieGenerator;
use App\Support\Pdf\EtatSyntheseGenerator;
use App\Support\Pdf\SerieExercicesGenerator;
use Database\Seeders\PlanComptableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Les documents papier du circuit financier.
 *
 * Un générateur qui compile n'est pas un générateur qui produit : mPDF échoue
 * à l'exécution sur une police manquante, une balise mal fermée ou un tableau
 * vide. Ces tests vérifient qu'un PDF sort réellement, et qu'il porte ce qu'il
 * doit porter.
 */
class DocumentsFinanciersPdfTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private AnneeScolaire $annee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanComptableSeeder::class);

        $this->school = School::create([
            'name' => 'Les Elites', 'code' => 'ELT', 'type' => 'primaire', 'is_active' => true,
        ]);

        $this->annee = AnneeScolaire::create([
            'school_id' => $this->school->id, 'libelle' => '2025-2026',
            'date_debut' => '2025-09-01', 'date_fin' => '2026-07-31', 'is_active' => true,
        ]);
    }

    private function estUnPdf(string $contenu): void
    {
        $this->assertStringStartsWith('%PDF-', $contenu);
        // Un document vide sort tout de même à quelques centaines d'octets :
        // le seuil écarte l'en-tête seul, pas une page réellement composée.
        $this->assertGreaterThan(3000, strlen($contenu));
    }

    // -------------------------------------------------------- état de synthèse

    private function ecrire(string $code, int $montant, string $sens): void
    {
        EcritureComptable::create([
            'school_id' => $this->school->id,
            'annee_scolaire_id' => $this->annee->id,
            'date_ecriture' => '2026-01-15',
            'libelle' => 'Test '.$code,
            'montant' => $montant,
            'sens' => $sens,
            'compte_comptable_id' => CompteComptable::where('code', $code)->value('id'),
        ]);
    }

    public function test_l_etat_de_synthese_produit_un_pdf(): void
    {
        $this->ecrire('701', 60_000_000, 'credit');
        $this->ecrire('661', 16_000_000, 'debit');
        $this->ecrire('624', 15_400_000, 'debit');
        $this->ecrire('108', 3_956_259, 'credit');

        $etat = app(EtatSyntheseService::class)->etablir($this->school->id, $this->annee->id);

        $this->estUnPdf((new EtatSyntheseGenerator)->build($this->school, $etat));
    }

    public function test_un_exercice_sans_ecriture_produit_quand_meme_la_grille(): void
    {
        // La grille vide est le cas d'ouverture d'exercice : le document doit
        // sortir avec tous ses comptes à zéro, pas échouer.
        $etat = app(EtatSyntheseService::class)->etablir($this->school->id, $this->annee->id);

        $this->estUnPdf((new EtatSyntheseGenerator)->build($this->school, $etat));
    }

    public function test_l_etat_pdf_repond_sur_la_route(): void
    {
        $this->ecrire('701', 1_000_000, 'credit');

        $reponse = $this->actingAs($this->admin(), 'sanctum')->get(
            '/api/v1/etat-synthese/pdf?school_id='.$this->school->id.'&annee_scolaire_id='.$this->annee->id,
        );

        $reponse->assertOk();
        $reponse->assertHeader('Content-Type', 'application/pdf');
        $this->estUnPdf($reponse->getContent());
    }

    public function test_la_serie_des_exercices_produit_un_pdf(): void
    {
        $this->ecrire('701', 56_355_000, 'credit');
        $this->ecrire('624', 22_185_600, 'debit');

        AnneeScolaire::create([
            'school_id' => $this->school->id, 'libelle' => '2026-2027',
            'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31',
        ]);

        $serie = app(EtatSyntheseService::class)->serie($this->school->id);

        $this->assertCount(2, $serie);
        $this->estUnPdf((new SerieExercicesGenerator)->build($this->school, $serie));
    }

    public function test_un_etablissement_sans_exercice_ne_fait_pas_echouer_la_serie(): void
    {
        $vierge = School::create([
            'name' => 'Elites Tech', 'code' => 'ETC', 'type' => 'secondaire', 'is_active' => true,
        ]);

        $this->estUnPdf((new SerieExercicesGenerator)->build($vierge, []));
    }

    // ------------------------------------------------------ bulletin de paie

    /**
     * Le bulletin existait avant la vacation horaire : il affichait « jours
     * ouvrables / jours travaillés » à un agent payé à l'heure, sans jamais
     * montrer le calcul qui le concerne.
     */
    public function test_le_bulletin_d_un_vacataire_porte_ses_heures_et_son_taux(): void
    {
        $personnel = Personnel::create([
            'school_id' => $this->school->id, 'nom_complet' => 'SONG ERIC MUNYAM',
            'sexe' => 'M', 'statut' => 'actif',
        ]);

        Remuneration::create([
            'school_id' => $this->school->id, 'personnel_id' => $personnel->id,
            'date_effet' => '2025-09-01', 'mode' => 'horaire', 'taux_horaire' => 1100, 'salaire_base' => 0,
        ]);

        $bulletin = app(PaieService::class)->preparer($personnel, 2026, 1, ['heures' => 40]);

        $this->assertSame(44000, $bulletin->salaire_brut);
        $this->assertSame(40, $bulletin->heures);
        $this->assertSame(1100, $bulletin->taux_horaire);

        $this->estUnPdf((new BulletinPaieGenerator)->build($bulletin->fresh(['lignes', 'personnel'])));
    }

    public function test_le_bulletin_d_un_mensuel_reste_inchange(): void
    {
        $personnel = Personnel::create([
            'school_id' => $this->school->id, 'nom_complet' => 'BOIGA DJANABOU',
            'sexe' => 'F', 'statut' => 'actif',
        ]);

        Remuneration::create([
            'school_id' => $this->school->id, 'personnel_id' => $personnel->id,
            'date_effet' => '2025-09-01', 'salaire_base' => 60000,
        ]);

        $bulletin = app(PaieService::class)->preparer($personnel, 2026, 1);

        $this->assertNull($bulletin->taux_horaire);
        $this->estUnPdf((new BulletinPaieGenerator)->build($bulletin->fresh(['lignes', 'personnel'])));
    }

    // ------------------------------------------------------------ bordereau

    private function agentPaye(string $nom, ?string $banque, ?string $compte): void
    {
        $personnel = Personnel::create([
            'school_id' => $this->school->id, 'nom_complet' => $nom, 'sexe' => 'F', 'statut' => 'actif',
            'banque' => $banque, 'numero_compte' => $compte,
        ]);

        Remuneration::create([
            'school_id' => $this->school->id, 'personnel_id' => $personnel->id,
            'date_effet' => '2025-09-01', 'salaire_base' => 60000,
        ]);

        $service = app(PaieService::class);
        $service->arreter($service->preparer($personnel, 2026, 1));
    }

    public function test_le_bordereau_produit_un_pdf_par_banque(): void
    {
        $this->agentPaye('NCHANG SYLVIE WANKI', 'NTARINKON', '827508');
        $this->agentPaye('BOUBA EMMANUEL', 'SUNRISE', '319');

        $bordereau = app(BordereauVirementService::class)->etablir($this->school->id, 2026, 1);

        $this->assertCount(2, $bordereau['banques']);
        $this->estUnPdf((new BordereauVirementGenerator)->build($this->school, $bordereau));
    }

    public function test_le_bordereau_signale_les_agents_non_virables(): void
    {
        $this->agentPaye('NCHANG SYLVIE WANKI', 'NTARINKON', '827508');
        $this->agentPaye('EUPHRASIE', null, null);

        $bordereau = app(BordereauVirementService::class)->etablir($this->school->id, 2026, 1);

        $this->assertCount(1, $bordereau['sans_domiciliation']);
        $this->estUnPdf((new BordereauVirementGenerator)->build($this->school, $bordereau));
    }

    public function test_un_mois_sans_bulletin_arrete_produit_un_document_explicite(): void
    {
        $bordereau = app(BordereauVirementService::class)->etablir($this->school->id, 2026, 1);

        $this->assertSame([], $bordereau['banques']);
        // Pas d'exception sur une liste vide : le document dit qu'il n'y a rien.
        $this->estUnPdf((new BordereauVirementGenerator)->build($this->school, $bordereau));
    }

    public function test_le_bordereau_pdf_repond_sur_la_route(): void
    {
        $this->agentPaye('NCHANG SYLVIE WANKI', 'NTARINKON', '827508');

        $reponse = $this->actingAs($this->admin(), 'sanctum')->get(
            '/api/v1/paie/bordereau/pdf?school_id='.$this->school->id.'&annee=2026&mois=1',
        );

        $reponse->assertOk();
        $reponse->assertHeader('Content-Type', 'application/pdf');
        $this->estUnPdf($reponse->getContent());
    }

    /** Compte disposant de tous les privilèges : le test porte sur le document, pas sur la garde. */
    private function admin(): \App\Models\User
    {
        foreach (\App\Support\CataloguePermissions::codes() as $code) {
            \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }

        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $user = \App\Models\User::create([
            'school_id' => $this->school->id, 'name' => 'Économe', 'email' => 'econome@elites.test',
            'password' => \Illuminate\Support\Facades\Hash::make('secret'), 'is_active' => true,
        ]);

        $user->assignRole($role);

        return $user;
    }
}
