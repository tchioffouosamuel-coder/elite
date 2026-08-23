<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\BusAffectation;
use App\Models\BusTrajet;
use App\Models\Classe;
use App\Models\EcritureComptable;
use App\Models\Eleve;
use App\Models\FraisAnnexe;
use App\Models\GrilleFrais;
use App\Models\School;
use App\Models\User;
use App\Models\Versement;
use App\Support\Pdf\RecuVersementGenerator;
use App\Services\ScolariteService;
use Database\Seeders\PlanComptableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ScolariteTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private AnneeScolaire $annee;

    private Classe $classe;

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

        $this->classe = Classe::create([
            'school_id' => $this->school->id,
            'nom' => 'ACCOUNTING 1-A',
        ]);

        GrilleFrais::create([
            'school_id' => $this->school->id,
            'annee_scolaire_id' => $this->annee->id,
            'classe_id' => $this->classe->id,
            'montant' => 359000,
        ]);
    }

    private function eleve(array $attributs = []): Eleve
    {
        return Eleve::create([
            'school_id' => $this->school->id,
            'classe_id' => $this->classe->id,
            'matricule' => '23PRIM2',
            'nom_complet' => 'FOMESSO LIMA MARK JOEL',
            'sexe' => 'M',
            'statut' => 'actif',
            ...$attributs,
        ]);
    }

    private function service(): ScolariteService
    {
        return app(ScolariteService::class);
    }

    public function test_le_dossier_reprend_le_tarif_de_la_classe(): void
    {
        $dossier = $this->service()->dossier($this->eleve(), $this->annee);

        $this->assertSame(359000, $dossier->montant_scolarite);
        $this->assertSame(359000, $dossier->total_du);
        $this->assertSame('impaye', $dossier->statut_paiement);
    }

    public function test_le_tarif_par_defaut_de_l_ecole_sert_de_repli(): void
    {
        GrilleFrais::create([
            'school_id' => $this->school->id,
            'annee_scolaire_id' => $this->annee->id,
            'classe_id' => null,
            'montant' => 200000,
        ]);
        $autre = Classe::create([
            'school_id' => $this->school->id,
            'nom' => 'CLOTHING 1-A',
        ]);

        $dossier = $this->service()->dossier($this->eleve(['classe_id' => $autre->id]), $this->annee);

        $this->assertSame(200000, $dossier->montant_scolarite);
    }

    public function test_les_frais_annexes_obligatoires_sont_rattaches(): void
    {
        FraisAnnexe::create([
            'school_id' => $this->school->id,
            'annee_scolaire_id' => $this->annee->id,
            'libelle' => 'Tenue scolaire',
            'montant' => 15000,
            'obligatoire' => true,
        ]);
        FraisAnnexe::create([
            'school_id' => $this->school->id,
            'annee_scolaire_id' => $this->annee->id,
            'libelle' => 'Transport',
            'montant' => 50000,
            'obligatoire' => false,
        ]);

        $dossier = $this->service()->dossier($this->eleve(), $this->annee);

        $this->assertCount(1, $dossier->fraisAnnexes);
        $this->assertSame(15000, $dossier->total_frais_annexes);
        $this->assertSame(374000, $dossier->total_du);
    }

    /** Le cas du reçu fourni : 359 000 dus, 20 000 versés, 339 000 restants. */
    public function test_un_encaissement_reduit_le_reste_a_payer(): void
    {
        $dossier = $this->service()->dossier($this->eleve(), $this->annee);

        $versement = $this->service()->encaisser($dossier, ['montant' => 20000, 'mode' => 'especes']);

        $this->assertSame('RC-EBT-0001', $versement->numero_recu);

        $dossier = $dossier->fresh()->load(['fraisAnnexes', 'versements']);
        $this->assertSame(20000, $dossier->total_paye);
        $this->assertSame(339000, $dossier->reste_a_payer);
        $this->assertSame('partiel', $dossier->statut_paiement);
    }

    public function test_le_reliquat_precedent_s_eteint_en_premier(): void
    {
        $eleve = $this->eleve();
        $dossier = $this->service()->dossier($eleve, $this->annee);
        $dossier->update(['report_dette' => 50000]);

        $versement = $this->service()->encaisser($dossier->fresh(), ['montant' => 60000]);

        $lignes = $versement->lignes->keyBy('affectation');
        $this->assertSame(50000, $lignes['report_dette']->montant);
        $this->assertSame(10000, $lignes['scolarite']->montant);
    }

    public function test_le_trop_percu_ressort_en_avance(): void
    {
        $dossier = $this->service()->dossier($this->eleve(), $this->annee);
        $this->service()->encaisser($dossier, ['montant' => 400000]);

        $dossier = $dossier->fresh()->load(['fraisAnnexes', 'versements']);
        $this->assertSame(0, $dossier->reste_a_payer);
        $this->assertSame(41000, $dossier->avance);
        $this->assertSame('avance', $dossier->statut_paiement);
    }

    public function test_une_ventilation_incoherente_est_refusee(): void
    {
        $dossier = $this->service()->dossier($this->eleve(), $this->annee);

        $this->expectExceptionMessage('ne correspond pas au montant encaissé');

        $this->service()->encaisser($dossier, [
            'montant' => 20000,
            'lignes' => [['affectation' => 'scolarite', 'montant' => 15000]],
        ]);
    }

    public function test_un_recu_bus_seul_ne_presente_que_le_transport(): void
    {
        $eleve = $this->eleve();
        $dossier = $this->service()->dossier($eleve, $this->annee);
        $trajet = BusTrajet::create([
            'school_id' => $this->school->id,
            'nom' => 'Ligne Nord',
            'tarif_aller_retour' => 50000,
        ]);
        BusAffectation::create([
            'eleve_id' => $eleve->id,
            'trajet_id' => $trajet->id,
            'annee_scolaire_id' => $this->annee->id,
            'tarif_mensuel' => 50000,
            'option_trajet' => 'aller_retour',
            'statut' => 'actif',
        ]);
        $versement = Versement::create([
            'school_id' => $this->school->id,
            'dossier_scolarite_id' => $dossier->id,
            'numero_recu' => 'RC-EBT-BUS',
            'date_versement' => '2026-09-01',
            'montant' => 30000,
            'mode' => 'especes',
        ]);
        $versement->lignes()->create([
            'affectation' => 'bus',
            'montant' => 30000,
            'libelle' => 'Transport scolaire',
        ]);

        $mentions = new \ReflectionMethod(RecuVersementGenerator::class, 'mentions');
        $mentions->setAccessible(true);
        $html = $mentions->invoke(new RecuVersementGenerator, $versement->fresh('lignes'), $dossier->fresh());

        $this->assertStringContainsString('Frais de bus', $html);
        $this->assertStringNotContainsString('Frais de scolarité', $html);
        $this->assertStringContainsString('30 000 F', $html);
        $this->assertStringContainsString('20 000 F', $html);
    }

    public function test_l_encaissement_mouvemente_le_journal(): void
    {
        $dossier = $this->service()->dossier($this->eleve(), $this->annee);
        $versement = $this->service()->encaisser($dossier, ['montant' => 20000, 'mode' => 'mobile_money']);

        $ecritures = EcritureComptable::where('origine_id', $versement->id)->with('compte')->get();

        $debit = $ecritures->firstWhere('sens', 'debit');
        $this->assertSame('578', $debit->compte->code);      // Mobile Money
        $this->assertSame(20000, $debit->montant);

        $credit = $ecritures->firstWhere('sens', 'credit');
        $this->assertSame('701', $credit->compte->code);     // Frais de scolarité
        $this->assertSame(20000, $credit->montant);
    }

    /** Un reçu remis ne disparaît pas : il s'annule, et le journal se contrepasse. */
    public function test_l_annulation_neutralise_sans_supprimer(): void
    {
        $dossier = $this->service()->dossier($this->eleve(), $this->annee);
        $versement = $this->service()->encaisser($dossier, ['montant' => 20000]);

        $this->service()->annuler($versement, 'Erreur de saisie');

        $this->assertDatabaseHas('versements', ['id' => $versement->id, 'motif_annulation' => 'Erreur de saisie']);
        $this->assertSame(0, $dossier->fresh()->load(['fraisAnnexes', 'versements'])->total_paye);

        // Deux écritures d'origine, deux contrepassations : le journal se
        // corrige par l'inverse, il ne se réécrit pas.
        $ecritures = EcritureComptable::where('origine_id', $versement->id)->get();
        $this->assertCount(4, $ecritures);
        $this->assertSame(
            $ecritures->where('sens', 'debit')->sum('montant'),
            $ecritures->where('sens', 'credit')->sum('montant'),
        );
    }

    public function test_un_recu_ne_s_annule_pas_deux_fois(): void
    {
        $dossier = $this->service()->dossier($this->eleve(), $this->annee);
        $versement = $this->service()->encaisser($dossier, ['montant' => 20000]);
        $this->service()->annuler($versement, 'Erreur');

        $this->expectExceptionMessage('déjà annulé');
        $this->service()->annuler($versement->fresh(), 'Encore');
    }

    public function test_une_revision_de_tarif_se_repercute_sur_les_dossiers_deja_ouverts(): void
    {
        $dossier = $this->service()->dossier($this->eleve(), $this->annee);
        $this->assertSame(359000, $dossier->montant_scolarite);

        GrilleFrais::where('school_id', $this->school->id)
            ->where('classe_id', $this->classe->id)
            ->update(['montant' => 400000]);

        $misAJour = $this->service()->synchroniserTarifs($this->school->id, $this->annee);

        $this->assertSame(1, $misAJour);
        $this->assertSame(400000, $dossier->fresh()->montant_scolarite);
        $this->assertSame(400000, $dossier->fresh()->load(['fraisAnnexes', 'versements'])->total_du);
    }

    public function test_un_frais_annexe_circonscrit_a_une_classe_ne_s_applique_pas_ailleurs(): void
    {
        $autre = Classe::create([
            'school_id' => $this->school->id,
            'nom' => 'CLOTHING 1-A',
        ]);

        $frais = FraisAnnexe::create([
            'school_id' => $this->school->id,
            'annee_scolaire_id' => $this->annee->id,
            'libelle' => 'Tenue technique',
            'montant' => 15000,
            'obligatoire' => true,
        ]);
        $frais->synchroniserClasses([$this->classe->id]);

        $dossierClasseVisee = $this->service()->dossier($this->eleve(), $this->annee);
        $this->assertCount(1, $dossierClasseVisee->fraisAnnexes);

        $dossierAutreClasse = $this->service()->dossier(
            $this->eleve(['classe_id' => $autre->id, 'matricule' => '23PRIM3']),
            $this->annee,
        );
        $this->assertCount(0, $dossierAutreClasse->fraisAnnexes);
    }

    public function test_le_reliquat_est_repris_sur_l_annee_suivante(): void
    {
        $eleve = $this->eleve();
        $dossier = $this->service()->dossier($eleve, $this->annee);
        $this->service()->encaisser($dossier, ['montant' => 100000]);

        $suivante = AnneeScolaire::create([
            'school_id' => $this->school->id,
            'libelle' => '2027-2028',
            'date_debut' => '2027-09-01',
            'date_fin' => '2028-07-31',
            'is_active' => false,
        ]);

        // 359 000 − 100 000 = 259 000 reportés.
        $this->assertSame(259000, $this->service()->dossier($eleve->fresh(), $suivante)->report_dette);
    }

    /**
     * Régression : un super admin en mode agrégé (sans X-School-Id) doit
     * pouvoir ouvrir le dossier d'un élève de n'importe quelle école de son
     * complexe, pas seulement de la première renvoyée par `ecolesAccessibles`
     * — sinon la route 404 pour tout élève qui ne s'y trouve pas.
     */
    public function test_un_super_admin_en_mode_agrege_ouvre_le_dossier_d_une_autre_ecole(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $autreEcole = School::create(['name' => 'Elites Prim', 'code' => 'EBP', 'type' => 'primaire', 'is_active' => true]);
        $autreAnnee = AnneeScolaire::create([
            'school_id' => $autreEcole->id,
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-07-31',
            'is_active' => true,
        ]);
        $autreClasse = Classe::create(['school_id' => $autreEcole->id, 'nom' => 'CE1 A']);
        GrilleFrais::create([
            'school_id' => $autreEcole->id,
            'annee_scolaire_id' => $autreAnnee->id,
            'classe_id' => $autreClasse->id,
            'montant' => 90000,
        ]);
        $eleveAutreEcole = Eleve::create([
            'school_id' => $autreEcole->id,
            'classe_id' => $autreClasse->id,
            'matricule' => '26PRIM1',
            'nom_complet' => 'NGONO PAUL',
            'sexe' => 'M',
            'statut' => 'actif',
        ]);

        // `school_id` place ce super admin en priorité sur la première école
        // (`$this->school`) : sans le correctif, c'est celle-ci — pas
        // `$autreEcole` — que le tenant retiendrait en mode agrégé.
        $user = User::create([
            'name' => 'Root',
            'email' => 'root@test.local',
            'password' => 'password',
            'school_id' => $this->school->id,
            'is_active' => true,
        ]);
        $user->assignRole('super_admin');

        $reponse = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/eleves/{$eleveAutreEcole->id}/scolarite");

        $reponse->assertOk()->assertJsonPath('data.montant_scolarite', 90000);
    }
}
