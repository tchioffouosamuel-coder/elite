<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\CompteComptable;
use App\Models\Depense;
use App\Models\EcritureComptable;
use App\Models\Eleve;
use App\Models\GrilleFrais;
use App\Models\Personnel;
use App\Models\Remuneration;
use App\Models\School;
use App\Services\BilanFinancierService;
use App\Services\DepenseService;
use App\Services\PaieService;
use App\Services\ScolariteService;
use Database\Seeders\PlanComptableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepenseBilanTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private AnneeScolaire $annee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanComptableSeeder::class);

        $this->school = School::create(['name' => 'Elites Tech', 'code' => 'EBT', 'type' => 'secondaire', 'is_active' => true]);
        $this->annee = AnneeScolaire::create([
            'school_id' => $this->school->id, 'libelle' => '2026-2027',
            'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31', 'is_active' => true,
        ]);
    }

    private function depenses(): DepenseService
    {
        return app(DepenseService::class);
    }

    private function bilan(): BilanFinancierService
    {
        return app(BilanFinancierService::class);
    }

    private function enregistrer(array $donnees = []): Depense
    {
        return $this->depenses()->enregistrer($this->school->id, [
            'libelle' => 'Facture électricité',
            'montant' => 120000,
            'date_depense' => '2026-10-05',
            'annee_scolaire_id' => $this->annee->id,
            'compte_comptable_id' => CompteComptable::where('code', '632')->value('id'),
            ...$donnees,
        ]);
    }

    public function test_une_depense_payee_mouvemente_charge_et_tresorerie(): void
    {
        $depense = $this->enregistrer(['mode' => 'especes']);

        $ecritures = EcritureComptable::where('origine_id', $depense->id)->with('compte')->get();

        $this->assertSame('632', $ecritures->firstWhere('sens', 'debit')->compte->code);  // électricité
        $this->assertSame('571', $ecritures->firstWhere('sens', 'credit')->compte->code); // caisse
        $this->assertSame(120000, $ecritures->firstWhere('sens', 'debit')->montant);
    }

    /** Une dépense engagée grève un budget, elle ne sort pas encore de la caisse. */
    public function test_une_depense_engagee_ne_mouvemente_rien(): void
    {
        $depense = $this->enregistrer(['statut' => 'engagee']);

        $this->assertSame(0, EcritureComptable::where('origine_id', $depense->id)->count());

        $this->depenses()->payer($depense, 'virement');

        $ecritures = EcritureComptable::where('origine_id', $depense->id)->with('compte')->get();
        $this->assertCount(2, $ecritures);
        $this->assertSame('521', $ecritures->firstWhere('sens', 'credit')->compte->code); // banque
    }

    public function test_l_annulation_contrepasse_une_depense_payee(): void
    {
        $depense = $this->enregistrer();
        $this->depenses()->annuler($depense, 'Facture en double');

        $ecritures = EcritureComptable::where('origine_id', $depense->id)->get();

        $this->assertCount(4, $ecritures);
        $this->assertSame(
            (int) $ecritures->where('sens', 'debit')->sum('montant'),
            (int) $ecritures->where('sens', 'credit')->sum('montant'),
        );
        $this->assertSame('annulee', $depense->fresh()->statut);
    }

    public function test_annuler_une_depense_engagee_ne_cree_aucune_contrepassation(): void
    {
        $depense = $this->enregistrer(['statut' => 'engagee']);
        $this->depenses()->annuler($depense, 'Commande abandonnée');

        $this->assertSame(0, EcritureComptable::where('origine_id', $depense->id)->count());
    }

    public function test_le_bilan_ventile_par_compte_et_ignore_les_annulees(): void
    {
        $this->enregistrer(['libelle' => 'Électricité', 'montant' => 120000]);
        $this->enregistrer([
            'libelle' => 'Loyer', 'montant' => 300000,
            'compte_comptable_id' => CompteComptable::where('code', '611')->value('id'),
        ]);
        $annulee = $this->enregistrer(['libelle' => 'Erreur', 'montant' => 999000]);
        $this->depenses()->annuler($annulee, 'Doublon');

        $bilan = $this->depenses()->bilan($this->school->id);

        $this->assertSame(2, $bilan['totaux']['nombre']);
        $this->assertSame(420000, $bilan['totaux']['total']);
        $this->assertSame(999000, $bilan['totaux']['annule']);
        // Trié par montant décroissant : le loyer d'abord.
        $this->assertSame('611', $bilan['par_compte'][0]['code']);
    }

    /** Recettes et dépenses se comparent sur le journal, jamais sur les tables métier. */
    public function test_le_resultat_confronte_produits_et_charges(): void
    {
        $this->recette(500000);
        $this->enregistrer(['montant' => 120000]);

        $resultat = $this->bilan()->resultat($this->school->id);

        $this->assertSame(500000, $resultat['produits']['total']);
        $this->assertSame(120000, $resultat['charges']['total']);
        $this->assertSame(380000, $resultat['resultat']);
        $this->assertSame(24.0, $resultat['taux_charges']);
    }

    public function test_la_tresorerie_somme_les_entrees_moins_les_sorties(): void
    {
        $this->recette(500000);
        $this->enregistrer(['montant' => 120000, 'mode' => 'especes']);

        $this->assertSame(380000, $this->bilan()->tresorerie($this->school->id)['disponible']);
    }

    public function test_la_balance_s_equilibre(): void
    {
        $this->recette(500000);
        $this->enregistrer(['montant' => 120000]);
        $this->paie();

        $balance = $this->bilan()->balance($this->school->id);

        $this->assertTrue($balance['equilibre'], 'La partie double doit s\'équilibrer.');
        $this->assertSame($balance['total_debit'], $balance['total_credit']);
    }

    /**
     * Les charges de personnel doivent peser sur le résultat de l'année : sans
     * `annee_scolaire_id` sur le bulletin, leurs écritures disparaissaient du
     * compte de résultat dès qu'on le filtrait par année.
     */
    public function test_la_masse_salariale_pese_sur_le_resultat_de_l_annee(): void
    {
        $this->paie();

        $resultat = $this->bilan()->resultat($this->school->id, ['annee_scolaire_id' => $this->annee->id]);
        $comptes = collect($resultat['charges']['lignes'])->pluck('montant', 'code');

        $this->assertSame(48000, $comptes['661'] ?? 0, 'Le salaire brut doit figurer en charges.');
        $this->assertGreaterThan(0, $comptes['664'] ?? 0, 'Les charges patronales aussi.');
    }

    public function test_le_tableau_de_bord_reunit_les_trois_volets(): void
    {
        $this->recette(500000);
        $this->enregistrer(['montant' => 120000]);
        $this->paie();

        $bord = $this->bilan()->tableauDeBord($this->school->id, $this->annee->id);

        $this->assertSame(1, $bord['scolarite']['effectif']);
        $this->assertSame(500000, $bord['scolarite']['recouvre']);
        $this->assertSame(1, $bord['paie']['bulletins']);
        $this->assertSame(48000, $bord['paie']['masse_brute']);
        $this->assertGreaterThan(0, $bord['resultat']['produits']);
    }

    /** Encaisse une scolarité, pour alimenter le journal côté produits. */
    private function recette(int $montant): void
    {
        $classe = Classe::create([
            'school_id' => $this->school->id, 'annee_scolaire_id' => $this->annee->id, 'nom' => 'ACCOUNTING 1-A',
        ]);
        GrilleFrais::create([
            'school_id' => $this->school->id, 'annee_scolaire_id' => $this->annee->id,
            'classe_id' => $classe->id, 'montant' => 800000,
        ]);
        $eleve = Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $classe->id,
            'nom_complet' => 'TEST ELEVE', 'sexe' => 'M', 'statut' => 'actif',
        ]);

        $scolarite = app(ScolariteService::class);
        $dossier = $scolarite->dossier($eleve, $this->annee);
        $scolarite->encaisser($dossier, ['montant' => $montant, 'date_versement' => '2026-10-01']);
    }

    /** Arrête un bulletin, pour alimenter le journal côté charges de personnel. */
    private function paie(): void
    {
        $agent = Personnel::create([
            'school_id' => $this->school->id, 'nom_complet' => 'AGENT TEST', 'sexe' => 'F', 'statut' => 'actif',
        ]);
        Remuneration::create([
            'school_id' => $this->school->id, 'personnel_id' => $agent->id, 'date_effet' => '2026-01-01',
            'salaire_base' => 42000, 'prime_anciennete' => 1000,
            'prime_communication' => 2500, 'prime_transport' => 2500,
        ]);

        $paie = app(PaieService::class);
        $paie->arreter($paie->preparer($agent, 2026, 10));
    }
}
