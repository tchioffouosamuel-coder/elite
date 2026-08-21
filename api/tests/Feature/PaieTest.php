<?php

namespace Tests\Feature;

use App\Models\BulletinPaie;
use App\Models\EcritureComptable;
use App\Models\Personnel;
use App\Models\Remuneration;
use App\Models\School;
use App\Services\PaieService;
use Database\Seeders\PlanComptableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaieTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private Personnel $agent;

    protected function setUp(): void
    {
        parent::setUp();

        /*
         * Ce test porte sur le barème légal — IRPP progressif, TDL par tranche,
         * exonérations. L'établissement applique par défaut celui de ses
         * registres ; on épingle ici celui que les assertions décrivent.
         */
        config(['paie.bareme' => 'legal']);

        $this->seed(PlanComptableSeeder::class);

        $this->school = School::create(['name' => 'Elites Tech', 'code' => 'EBT', 'type' => 'secondaire', 'is_active' => true]);
        $this->agent = $this->personnel('BOIGA DJANABOU');
    }

    private function personnel(string $nom, ?School $school = null): Personnel
    {
        $school ??= $this->school;

        $personnel = Personnel::create([
            'school_id' => $school->id, 'nom_complet' => $nom, 'sexe' => 'F', 'statut' => 'actif',
        ]);

        // Le bulletin d'avril 2024 de l'établissement : 42 000 de base,
        // 1 000 d'ancienneté, 2 500 de communication, 2 500 de transport.
        Remuneration::create([
            'school_id' => $school->id, 'personnel_id' => $personnel->id, 'date_effet' => '2024-01-01',
            'salaire_base' => 42000, 'prime_anciennete' => 1000,
            'prime_communication' => 2500, 'prime_transport' => 2500,
        ]);

        return $personnel;
    }

    private function service(): PaieService
    {
        return app(PaieService::class);
    }

    public function test_le_bulletin_reprend_le_bareme_et_la_remuneration(): void
    {
        $bulletin = $this->service()->preparer($this->agent, 2024, 4);

        $this->assertSame('BP-EBT-0001', $bulletin->numero);
        $this->assertSame(48000, $bulletin->salaire_brut);
        $this->assertSame(43000, $bulletin->net_taxable);
        $this->assertSame('brouillon', $bulletin->statut);
        // 6 gains + 7 retenues du barème légal.
        $this->assertCount(13, $bulletin->lignes);
    }

    public function test_le_net_a_payer_deduit_charges_et_retenues_maison(): void
    {
        $bulletin = $this->service()->preparer($this->agent, 2024, 4, [
            'deduction_raff' => 3000,
            'deduction_njangi' => 15000,
        ]);

        $net = 48000 - $bulletin->charges_salariales - 18000;
        $this->assertSame($net, $bulletin->net_a_payer);
        $this->assertSame(18000, $bulletin->total_deductions);
    }

    public function test_les_absences_sont_retenues_au_prorata(): void
    {
        $bulletin = $this->service()->preparer($this->agent, 2024, 4, [
            'jours_ouvrables' => 22, 'jours_travailles' => 19,
        ]);

        // 3 jours sur 22 : 48 000 × 3/22 = 6 545.
        $this->assertSame(6545, $bulletin->deduction_absences);
    }

    public function test_le_net_ne_descend_jamais_sous_zero(): void
    {
        $bulletin = $this->service()->preparer($this->agent, 2024, 4, ['deduction_pret' => 500000]);

        $this->assertSame(0, $bulletin->net_a_payer);
    }

    public function test_un_brouillon_se_recalcule_sans_se_dupliquer(): void
    {
        $this->service()->preparer($this->agent, 2024, 4, ['deduction_raff' => 3000]);
        $bulletin = $this->service()->preparer($this->agent, 2024, 4, ['deduction_raff' => 5000]);

        $this->assertSame(1, BulletinPaie::count());
        $this->assertSame(5000, $bulletin->deduction_raff);
        $this->assertCount(13, $bulletin->lignes()->get());
    }

    /** Un bulletin arrêté est figé : les taux changeront, pas lui. */
    public function test_un_bulletin_arrete_ne_se_recalcule_plus(): void
    {
        $bulletin = $this->service()->preparer($this->agent, 2024, 4);
        $this->service()->arreter($bulletin);

        $this->expectExceptionMessage('déjà arrêté');
        $this->service()->preparer($this->agent, 2024, 4);
    }

    public function test_l_arrete_engage_la_comptabilite(): void
    {
        $bulletin = $this->service()->preparer($this->agent, 2024, 4);
        $this->service()->arreter($bulletin);

        $ecritures = EcritureComptable::where('origine_id', $bulletin->id)->with('compte')->get();
        $parCompte = $ecritures->keyBy(fn ($e) => $e->compte->code);

        $this->assertSame(48000, $parCompte['661']->montant);                     // salaires
        // Les charges patronales se ventilent comme l'état de synthèse les
        // présente : la CNPS en 662, les impôts et taxes en 663.
        $this->assertSame(
            $bulletin->charges_patronales,
            $parCompte['662']->montant + $parCompte['663']->montant,
        );
        $this->assertSame($bulletin->net_a_payer, $parCompte['421']->montant);    // dette envers l'agent

        // La partie double doit s'équilibrer.
        $this->assertSame(
            (int) $ecritures->where('sens', 'debit')->sum('montant'),
            (int) $ecritures->where('sens', 'credit')->sum('montant'),
        );
    }

    public function test_le_reglement_puis_l_emargement_suivent_l_arrete(): void
    {
        $bulletin = $this->service()->preparer($this->agent, 2024, 4);

        $this->expectExceptionMessage('doit être arrêté');
        $this->service()->payer($bulletin, 'especes');
    }

    public function test_le_cycle_complet_va_jusqu_a_l_emargement(): void
    {
        $bulletin = $this->service()->preparer($this->agent, 2024, 4);
        $bulletin = $this->service()->arreter($bulletin);
        $bulletin = $this->service()->payer($bulletin, 'especes', '2024-04-30');
        $bulletin = $this->service()->emarger($bulletin, 'Registre p. 12');

        $this->assertSame('paye', $bulletin->statut);
        $this->assertSame('2024-04-30', $bulletin->date_paiement->toDateString());
        $this->assertNotNull($bulletin->emarge_le);
        $this->assertSame('Registre p. 12', $bulletin->emargement_reference);
    }

    public function test_le_lot_signale_les_agents_sans_remuneration(): void
    {
        $this->personnel('AGBORNDE CATHERINE');
        Personnel::create([
            'school_id' => $this->school->id, 'nom_complet' => 'SANS SALAIRE', 'sexe' => 'M', 'statut' => 'actif',
        ]);

        $lot = $this->service()->preparerLot($this->school->id, 2024, 4);

        $this->assertCount(2, $lot['bulletins']);
        $this->assertCount(1, $lot['ignores']);
        $this->assertStringContainsString('SANS SALAIRE', $lot['ignores'][0]);
    }

    public function test_le_lot_agrege_prepare_les_agents_de_plusieurs_ecoles(): void
    {
        $autreEcole = School::create([
            'name' => 'Elites Primary', 'code' => 'EBP', 'type' => 'primaire', 'is_active' => true,
        ]);
        $this->personnel('AGBORNDE CATHERINE', $autreEcole);

        $lot = $this->service()->preparerLot([$this->school->id, $autreEcole->id], 2024, 4);

        $this->assertCount(2, $lot['bulletins']);
        $this->assertSame([], $lot['ignores']);
        $this->assertEqualsCanonicalizing(
            [$this->school->id, $autreEcole->id],
            $lot['bulletins']->pluck('school_id')->all(),
        );
    }

    public function test_la_masse_salariale_ne_compte_que_les_bulletins_arretes(): void
    {
        $this->service()->preparer($this->agent, 2024, 4);
        $totaux = $this->service()->masseSalariale($this->school->id, 2024, 4)['totaux'];

        $this->assertSame(1, $totaux['effectif']);
        $this->assertSame(0, $totaux['brut'], 'Un brouillon n\'engage pas la masse salariale.');

        $this->service()->arreter(BulletinPaie::first());
        $totaux = $this->service()->masseSalariale($this->school->id, 2024, 4)['totaux'];

        $this->assertSame(48000, $totaux['brut']);
        $this->assertSame(48000 + $totaux['charges_patronales'], $totaux['cout_employeur']);
    }
}
