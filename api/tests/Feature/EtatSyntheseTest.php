<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\CompteComptable;
use App\Models\DossierScolarite;
use App\Models\EcritureComptable;
use App\Models\Eleve;
use App\Models\School;
use App\Services\Comptabilite\EtatSyntheseService;
use App\Services\Comptabilite\PrelevementsEleveService;
use Database\Seeders\PlanComptableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * État de synthèse : reproduction du document de l'établissement, et lecture
 * qui sépare ce qui use l'exercice de ce qui le dépasse.
 */
class EtatSyntheseTest extends TestCase
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

    private function inscrire(int $nombre): void
    {
        for ($i = 0; $i < $nombre; $i++) {
            $eleve = Eleve::create([
                'school_id' => $this->school->id, 'nom_complet' => 'ELEVE '.$i, 'sexe' => 'M',
            ]);

            DossierScolarite::create([
                'school_id' => $this->school->id,
                'annee_scolaire_id' => $this->annee->id,
                'eleve_id' => $eleve->id,
            ]);
        }
    }

    private function etat(): array
    {
        return app(EtatSyntheseService::class)->etablir($this->school->id, $this->annee->id);
    }

    // ----------------------------------------------------------- plan de comptes

    public function test_le_plan_reprend_les_codes_du_document(): void
    {
        $attendus = [
            '611' => 'Fournitures de bureau, tables-bancs, tableaux',
            '624' => 'Construction et entretien/réparation bâtiments',
            '662' => 'Cotisation CNPS',
            '664' => 'Cotisations frais Fenasco-B',
            '699' => 'Amortissements bâtiments',
            '700' => 'Inscriptions',
            '701' => 'Scolarité',
            '702' => 'APEE',
        ];

        foreach ($attendus as $code => $libelle) {
            $this->assertSame($libelle, CompteComptable::where('code', $code)->value('libelle'), "compte {$code}");
        }
    }

    public function test_seule_la_construction_est_un_investissement(): void
    {
        $this->assertSame(['624'], CompteComptable::investissement()->pluck('code')->all());
    }

    public function test_le_depot_de_l_exploitant_n_est_pas_une_charge(): void
    {
        $compte = CompteComptable::where('code', '100')->firstOrFail();

        $this->assertSame('capital', $compte->nature);
        $this->assertSame(1, $compte->classe);
    }

    // ---------------------------------------------------------------- le document

    public function test_l_etat_reproduit_la_balance_du_document(): void
    {
        $this->ecrire('701', 60_000_000, 'credit');   // scolarité
        $this->ecrire('700', 5_000_000, 'credit');    // inscriptions
        $this->ecrire('661', 16_000_000, 'debit');    // salaires
        $this->ecrire('624', 15_400_000, 'debit');    // construction
        $this->ecrire('100', 5_000_000, 'debit');     // dépôt de l'exploitant

        $document = $this->etat()['document'];

        $this->assertSame(65_000_000, $document['total_recettes']);
        // Le document additionne tout ce que porte sa colonne, dépôt compris.
        $this->assertSame(36_400_000, $document['total_depenses']);
        $this->assertSame(28_600_000, $document['balance']);
    }

    public function test_la_lecture_analytique_sort_l_investissement_et_le_capital(): void
    {
        $this->ecrire('701', 60_000_000, 'credit');
        $this->ecrire('661', 16_000_000, 'debit');
        $this->ecrire('624', 15_400_000, 'debit');
        $this->ecrire('100', 5_000_000, 'debit');

        $analytique = $this->etat()['analytique'];

        $this->assertSame(16_000_000, $analytique['charges_exploitation']);
        $this->assertSame(60_000_000, $analytique['produits_exploitation']);
        $this->assertSame(44_000_000, $analytique['resultat_exploitation']);
        $this->assertSame(15_400_000, $analytique['investissement']);
        $this->assertSame(5_000_000, $analytique['capital']);
    }

    public function test_un_exercice_deficitaire_au_document_peut_etre_excedentaire_en_exploitation(): void
    {
        // Le cas de 2022-2023 : la construction fait basculer la balance alors
        // que l'exploitation dégage un excédent.
        $this->ecrire('701', 56_355_000, 'credit');
        $this->ecrire('661', 15_939_470, 'debit');
        $this->ecrire('624', 22_185_600, 'debit');
        $this->ecrire('100', 1_200_000, 'debit');
        $this->ecrire('650', 18_000_000, 'debit');

        $etat = $this->etat();

        $this->assertLessThan(0, $etat['document']['balance']);
        $this->assertGreaterThan(0, $etat['analytique']['resultat_exploitation']);
    }

    public function test_une_contrepassation_diminue_le_compte_de_charge(): void
    {
        $this->ecrire('611', 1_000_000, 'debit');
        $this->ecrire('611', 400_000, 'credit');

        $ligne = collect($this->etat()['depenses'])->firstWhere('code', '611');

        $this->assertSame(600_000, $ligne['montant']);
    }

    public function test_les_comptes_techniques_ne_sortent_jamais_a_l_etat(): void
    {
        $this->ecrire('571', 9_000_000, 'debit');   // caisse
        $this->ecrire('421', 3_000_000, 'credit');  // personnel

        $etat = $this->etat();
        $codes = array_merge(
            array_column($etat['depenses'], 'code'),
            array_column($etat['produits'], 'code'),
        );

        $this->assertNotContains('571', $codes);
        $this->assertNotContains('421', $codes);
        $this->assertSame(0, $etat['document']['total_depenses']);
    }

    public function test_l_apport_du_fondateur_se_lit_sous_la_balance(): void
    {
        $this->ecrire('108', 3_956_259, 'credit');

        $this->assertSame(3_956_259, $this->etat()['document']['apport_fondateur']);
        // Un apport n'est ni une charge ni un produit : il ne bouge pas le solde.
        $this->assertSame(0, $this->etat()['document']['balance']);
    }

    public function test_l_effectif_de_l_exercice_est_celui_des_dossiers(): void
    {
        $this->inscrire(7);

        $this->assertSame(7, $this->etat()['exercice']['effectif']);
    }

    public function test_un_compte_desactive_et_vide_n_encombre_pas_la_grille(): void
    {
        CompteComptable::where('code', '693')->update(['is_active' => false]);

        $codes = array_column($this->etat()['depenses'], 'code');

        $this->assertNotContains('693', $codes);
    }

    public function test_un_compte_desactive_qui_porte_des_ecritures_reste_visible(): void
    {
        $this->ecrire('693', 45000, 'debit');
        CompteComptable::where('code', '693')->update(['is_active' => false]);

        $etat = $this->etat();

        // Le masquer ferait disparaître 45 000 F du total sans rien signaler.
        $this->assertContains('693', array_column($etat['depenses'], 'code'));
        $this->assertSame(45000, $etat['document']['total_depenses']);
    }

    // --------------------------------------------------- prélèvements par élève

    private function prelevements(): PrelevementsEleveService
    {
        return app(PrelevementsEleveService::class);
    }

    public function test_les_prelevements_suivent_l_effectif_au_tarif_du_compte(): void
    {
        $this->inscrire(10);

        $lignes = collect($this->prelevements()->projeter($this->school->id, $this->annee->id))
            ->keyBy('code');

        $this->assertSame(2000, $lignes['654']['du']);   // SEDUC   : 200 F
        $this->assertSame(2000, $lignes['664']['du']);   // Fenasco : 200 F
        $this->assertSame(1000, $lignes['655']['du']);   // Assurance : 100 F
    }

    public function test_regulariser_passe_la_depense_et_l_ecriture(): void
    {
        $this->inscrire(10);

        $this->prelevements()->regulariser($this->school->id, $this->annee->id);

        $ligne = collect($this->etat()['depenses'])->firstWhere('code', '654');
        $this->assertSame(2000, $ligne['montant']);
    }

    public function test_regulariser_deux_fois_ne_double_pas_le_prelevement(): void
    {
        $this->inscrire(10);

        $this->prelevements()->regulariser($this->school->id, $this->annee->id);
        $secondPassage = $this->prelevements()->regulariser($this->school->id, $this->annee->id);

        $this->assertSame([], $secondPassage);
        $this->assertSame(2000, collect($this->etat()['depenses'])->firstWhere('code', '654')['montant']);
    }

    public function test_une_inscription_tardive_ne_passe_que_la_difference(): void
    {
        $this->inscrire(10);
        $this->prelevements()->regulariser($this->school->id, $this->annee->id);

        $this->inscrire(3);
        $rattrapage = collect($this->prelevements()->regulariser($this->school->id, $this->annee->id))
            ->keyBy('code');

        // 3 élèves de plus × 200 F, et non 13 × 200 F une seconde fois.
        $this->assertSame(600, $rattrapage['654']['ecart']);
        $this->assertSame(2600, collect($this->etat()['depenses'])->firstWhere('code', '654')['montant']);
    }
}
