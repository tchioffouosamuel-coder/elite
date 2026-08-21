<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\EcritureComptable;
use App\Models\Eleve;
use App\Models\FraisAnnexe;
use App\Models\GrilleFrais;
use App\Models\School;
use App\Services\ScolariteService;
use Database\Seeders\PlanComptableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'school_id' => $this->school->id, 'libelle' => '2026-2027',
            'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31', 'is_active' => true,
        ]);

        $this->classe = Classe::create([
            'school_id' => $this->school->id, 'annee_scolaire_id' => $this->annee->id, 'nom' => 'ACCOUNTING 1-A',
        ]);

        GrilleFrais::create([
            'school_id' => $this->school->id, 'annee_scolaire_id' => $this->annee->id,
            'classe_id' => $this->classe->id, 'montant' => 359000,
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
            'school_id' => $this->school->id, 'annee_scolaire_id' => $this->annee->id,
            'classe_id' => null, 'montant' => 200000,
        ]);
        $autre = Classe::create([
            'school_id' => $this->school->id, 'annee_scolaire_id' => $this->annee->id, 'nom' => 'CLOTHING 1-A',
        ]);

        $dossier = $this->service()->dossier($this->eleve(['classe_id' => $autre->id]), $this->annee);

        $this->assertSame(200000, $dossier->montant_scolarite);
    }

    public function test_les_frais_annexes_obligatoires_sont_rattaches(): void
    {
        FraisAnnexe::create([
            'school_id' => $this->school->id, 'annee_scolaire_id' => $this->annee->id,
            'libelle' => 'Tenue scolaire', 'montant' => 15000, 'obligatoire' => true,
        ]);
        FraisAnnexe::create([
            'school_id' => $this->school->id, 'annee_scolaire_id' => $this->annee->id,
            'libelle' => 'Transport', 'montant' => 50000, 'obligatoire' => false,
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
            'school_id' => $this->school->id, 'annee_scolaire_id' => $this->annee->id, 'nom' => 'CLOTHING 1-A',
        ]);

        $frais = FraisAnnexe::create([
            'school_id' => $this->school->id, 'annee_scolaire_id' => $this->annee->id,
            'libelle' => 'Tenue technique', 'montant' => 15000, 'obligatoire' => true,
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
            'school_id' => $this->school->id, 'libelle' => '2027-2028',
            'date_debut' => '2027-09-01', 'date_fin' => '2028-07-31', 'is_active' => false,
        ]);

        // 359 000 − 100 000 = 259 000 reportés.
        $this->assertSame(259000, $this->service()->dossier($eleve->fresh(), $suivante)->report_dette);
    }
}
