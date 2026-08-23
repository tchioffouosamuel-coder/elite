<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\Matiere;
use App\Models\Seance;
use App\Models\AvanceSalaire;
use App\Models\CompteComptable;
use App\Models\Immobilisation;
use App\Models\Personnel;
use App\Models\Remuneration;
use App\Models\School;
use App\Services\AvanceSalaireService;
use App\Services\Comptabilite\AmortissementService;
use App\Services\Comptabilite\EtatSyntheseService;
use App\Services\DepenseService;
use App\Services\Paie\BordereauVirementService;
use App\Services\PaieService;
use Database\Seeders\PlanComptableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Les quatre pièces qui referment le circuit : amortissement de
 * l'investissement, retenue de prêt commandée par l'échéancier, vacation
 * horaire, bordereau de virement.
 */
class FlowFinancierTest extends TestCase
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

    private function agent(string $nom = 'BOIGA DJANABOU', array $remuneration = []): Personnel
    {
        $personnel = Personnel::create([
            'school_id' => $this->school->id, 'nom_complet' => $nom, 'sexe' => 'F', 'statut' => 'actif',
        ]);

        Remuneration::create([
            'school_id' => $this->school->id, 'personnel_id' => $personnel->id,
            'date_effet' => '2025-09-01', 'salaire_base' => 60000,
        ] + $remuneration);

        return $personnel;
    }

    // ------------------------------------------------------ 1. amortissement

    private function construire(int $montant = 20_000_000): Immobilisation
    {
        app(DepenseService::class)->enregistrer($this->school->id, [
            'annee_scolaire_id' => $this->annee->id,
            'compte_comptable_id' => CompteComptable::where('code', '624')->value('id'),
            'date_depense' => '2025-10-01',
            'libelle' => 'Construction bloc pédagogique',
            'montant' => $montant,
            'mode' => 'virement',
        ]);

        return Immobilisation::forSchool($this->school->id)->latest('id')->firstOrFail();
    }

    public function test_une_depense_de_construction_s_inscrit_a_l_actif(): void
    {
        $bien = $this->construire();

        $this->assertSame(20_000_000, $bien->montant);
        $this->assertSame(20, $bien->duree_annees);
        $this->assertSame(1_000_000, $bien->dotationAnnuelle());
    }

    public function test_une_depense_de_fonctionnement_ne_s_immobilise_pas(): void
    {
        app(DepenseService::class)->enregistrer($this->school->id, [
            'annee_scolaire_id' => $this->annee->id,
            'compte_comptable_id' => CompteComptable::where('code', '613')->value('id'),
            'date_depense' => '2025-10-01',
            'libelle' => "Produits d'entretien",
            'montant' => 200000,
        ]);

        $this->assertSame(0, Immobilisation::forSchool($this->school->id)->count());
    }

    public function test_la_dotation_ramene_la_construction_au_resultat(): void
    {
        $this->construire();

        app(AmortissementService::class)->doter($this->school->id, $this->annee->id);

        $etat = app(EtatSyntheseService::class)->etablir($this->school->id, $this->annee->id);
        $dotation = collect($etat['depenses'])->firstWhere('code', '699');

        $this->assertSame(1_000_000, $dotation['montant']);
        // L'investissement reste hors exploitation ; seule la dotation y entre.
        $this->assertSame(20_000_000, $etat['analytique']['investissement']);
        $this->assertSame(1_000_000, $etat['analytique']['charges_exploitation']);
    }

    public function test_doter_deux_fois_ne_double_pas_la_charge(): void
    {
        $this->construire();
        $service = app(AmortissementService::class);

        $service->doter($this->school->id, $this->annee->id);
        $second = $service->doter($this->school->id, $this->annee->id);

        $this->assertSame([], $second);
        $this->assertSame(1_000_000, collect(
            app(EtatSyntheseService::class)->etablir($this->school->id, $this->annee->id)['depenses'],
        )->firstWhere('code', '699')['montant']);
    }

    public function test_la_derniere_annuite_solde_le_reliquat(): void
    {
        // 100 000 sur 20 ans : 5 000 par an, mais un bien de 30 000 ne peut
        // pas se doter au-delà de sa valeur.
        $bien = Immobilisation::create([
            'school_id' => $this->school->id,
            'libelle' => 'Réfection toiture',
            'montant' => 30000,
            'date_mise_en_service' => '2025-10-01',
            'duree_annees' => 2,
        ]);

        $bien->amortissements()->create([
            'annee_scolaire_id' => $this->annee->id,
            'montant' => 15000,
            'date_dotation' => '2026-07-31',
        ]);

        $suivante = AnneeScolaire::create([
            'school_id' => $this->school->id, 'libelle' => '2026-2027',
            'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31',
        ]);

        app(AmortissementService::class)->doter($this->school->id, $suivante->id);

        $this->assertSame(30000, $bien->fresh()->cumul_amorti);
        $this->assertSame(0, $bien->fresh()->valeur_residuelle);
    }

    public function test_la_projection_porte_la_duree_pour_l_ecran(): void
    {
        $this->construire(20_000_000);

        $ligne = app(AmortissementService::class)->projeter($this->school->id, $this->annee->id)[0];

        $this->assertSame(20, $ligne['duree_annees']);
        $this->assertSame(1_000_000, $ligne['dotation']);
    }

    public function test_la_duree_d_amortissement_se_revise_bien_par_bien(): void
    {
        // Une réfection de toiture ne s'étale pas comme un bâtiment.
        $bien = $this->construire(3_000_000);

        $revise = app(AmortissementService::class)->reviser($bien, ['duree_annees' => 5]);

        $this->assertSame(5, $revise->duree_annees);
        $this->assertSame(600_000, $revise->dotationAnnuelle());
    }

    public function test_la_duree_ne_peut_pas_passer_sous_les_annuites_deja_passees(): void
    {
        $bien = $this->construire(20_000_000);
        app(AmortissementService::class)->doter($this->school->id, $this->annee->id);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('déjà 1 annuité');

        app(AmortissementService::class)->reviser($bien->fresh(), ['duree_annees' => 0]);
    }

    public function test_reviser_la_duree_ne_recalcule_pas_les_dotations_passees(): void
    {
        $bien = $this->construire(20_000_000);
        $service = app(AmortissementService::class);
        $service->doter($this->school->id, $this->annee->id);

        $service->reviser($bien->fresh(), ['duree_annees' => 10]);

        // L'annuité déjà passée reste à 1 000 000 : un exercice clos ne se
        // réécrit pas. La nouvelle durée vaut pour la suite.
        $this->assertSame(1_000_000, $bien->fresh()->cumul_amorti);
        $this->assertSame(2_000_000, $bien->fresh()->dotationAnnuelle());
    }

    // --------------------------------------------------------- 2. prêt ↔ paie

    private function accorder(Personnel $agent, int $montant, int $mois): AvanceSalaire
    {
        return app(AvanceSalaireService::class)->accorder($this->school->id, [
            'personnel_id' => $agent->id,
            'montant' => $montant,
            'nombre_mois' => $mois,
            'date_avance' => '2025-10-01',
        ], null);
    }

    public function test_la_retenue_de_pret_vient_de_l_echeancier(): void
    {
        $agent = $this->agent();
        $this->accorder($agent, 100000, 5);   // 20 000 par mois

        $bulletin = app(PaieService::class)->preparer($agent, 2026, 1);

        $this->assertSame(20000, $bulletin->deduction_pret);
    }

    public function test_l_arrete_enregistre_le_remboursement_sur_l_avance(): void
    {
        $agent = $this->agent();
        $avance = $this->accorder($agent, 100000, 5);

        $service = app(PaieService::class);
        $service->arreter($service->preparer($agent, 2026, 1));

        $this->assertSame(20000, $avance->fresh()->montant_rembourse);
        $this->assertSame(80000, $avance->fresh()->solde);
    }

    public function test_le_brouillon_n_impute_rien(): void
    {
        $agent = $this->agent();
        $avance = $this->accorder($agent, 100000, 5);

        app(PaieService::class)->preparer($agent, 2026, 1);

        $this->assertSame(0, $avance->fresh()->montant_rembourse);
    }

    public function test_la_retenue_ne_depasse_pas_le_solde_restant(): void
    {
        $agent = $this->agent();
        $avance = $this->accorder($agent, 100000, 5);

        // Quatre mensualités déjà remboursées : il ne reste que 20 000.
        app(AvanceSalaireService::class)->imputerSurPaie($agent->id, 80000, '2026-01-31');

        $service = app(PaieService::class);
        $bulletin = $service->arreter($service->preparer($agent, 2026, 6));

        $this->assertSame(20000, $bulletin->deduction_pret);
        $this->assertSame(0, $avance->fresh()->solde);
    }

    public function test_une_retenue_superieure_au_du_revient_a_l_agent(): void
    {
        $agent = $this->agent();
        $this->accorder($agent, 30000, 3);   // 10 000 par mois, 30 000 dus

        $service = app(PaieService::class);
        // On force une retenue de 50 000 alors que l'agent ne doit que 30 000.
        $bulletin = $service->arreter($service->preparer($agent, 2026, 1, ['deduction_pret' => 50000]));

        $this->assertSame(30000, $bulletin->deduction_pret);
        $this->assertSame(30000, $bulletin->salaire_brut - $bulletin->net_a_payer);
    }

    public function test_la_saisie_garde_le_dernier_mot_sur_l_echeancier(): void
    {
        $agent = $this->agent();
        $this->accorder($agent, 100000, 5);

        $bulletin = app(PaieService::class)->preparer($agent, 2026, 1, ['deduction_pret' => 5000]);

        $this->assertSame(5000, $bulletin->deduction_pret);
    }

    // ----------------------------------------------------- 3. vacation horaire

    public function test_un_vacataire_est_paye_aux_heures_faites(): void
    {
        $agent = $this->agent('SONG ERIC MUNYAM', [
            'mode' => 'horaire', 'taux_horaire' => 1100, 'salaire_base' => 0,
        ]);

        $bulletin = app(PaieService::class)->preparer($agent, 2026, 1, ['heures' => 40]);

        $this->assertSame(44000, $bulletin->salaire_brut);
        $this->assertSame(40, $bulletin->heures);
        $this->assertSame(1100, $bulletin->taux_horaire);
    }

    public function test_une_vacation_ne_se_proratise_pas_sur_les_jours(): void
    {
        $agent = $this->agent('SONG ERIC MUNYAM', [
            'mode' => 'horaire', 'taux_horaire' => 1000, 'salaire_base' => 0,
        ]);

        $bulletin = app(PaieService::class)->preparer($agent, 2026, 1, [
            'heures' => 30, 'jours_ouvrables' => 20, 'jours_travailles' => 12,
        ]);

        // Les heures non faites ne sont pas payées : les retenir en plus les
        // compterait deux fois.
        $this->assertSame(0, $bulletin->deduction_absences);
        $this->assertSame(30000, $bulletin->net_a_payer);
    }

    /**
     * Le lien complet : emploi du temps tenu, cours déclaré fait dans Ma
     * journée, heures qui en découlent sans aucune saisie manuelle. Une
     * heure pédagogique dure 50 minutes — un créneau de 100 minutes (deux
     * périodes accolées) vaut deux heures, pas 1,67.
     */
    public function test_les_heures_se_deduisent_des_seances_effectuees(): void
    {
        $agent = $this->agent('SONG ERIC MUNYAM', [
            'mode' => 'horaire', 'taux_horaire' => 1000, 'salaire_base' => 0,
        ]);
        $classeMatiere = $this->affectation($agent);

        // Trois créneaux de 50 minutes tenus, un quatrième resté à l'état
        // prévu (jamais pointé) : celui-ci ne doit rien rapporter.
        foreach (['2026-01-05', '2026-01-12', '2026-01-19'] as $date) {
            $this->seance($classeMatiere, $date, '08:00', '08:50', 'effectuee');
        }
        $this->seance($classeMatiere, '2026-01-26', '08:00', '08:50', 'prevue');

        // Un créneau de rattrapage de 100 minutes, deux périodes accolées :
        // il vaut deux heures pédagogiques, pas 1,67 heure d'horloge.
        $this->seance($classeMatiere, '2026-01-20', '14:00', '15:40', 'effectuee');

        // Une séance annulée ne compte pas davantage qu'une séance prévue.
        $this->seance($classeMatiere, '2026-01-27', '08:00', '08:50', 'annulee');

        $bulletin = app(PaieService::class)->preparer($agent, 2026, 1);

        // 3 x 50 min + 100 min = 250 min, soit 5 heures pédagogiques.
        $this->assertSame(5, $bulletin->heures);
        $this->assertSame(5000, $bulletin->salaire_brut);
        $this->assertSame(5000, $bulletin->net_a_payer);
    }

    /** La saisie manuelle garde le dernier mot sur ce que Ma journée a validé. */
    public function test_la_saisie_manuelle_prevaut_sur_les_seances_effectuees(): void
    {
        $agent = $this->agent('SONG ERIC MUNYAM', [
            'mode' => 'horaire', 'taux_horaire' => 1000, 'salaire_base' => 0,
        ]);
        $classeMatiere = $this->affectation($agent);
        $this->seance($classeMatiere, '2026-01-05', '08:00', '08:50', 'effectuee');

        // Un rattrapage tenu hors emploi du temps, jamais pointé dans Ma
        // journée : l'économe le sait et le corrige à la saisie.
        $bulletin = app(PaieService::class)->preparer($agent, 2026, 1, ['heures' => 10]);

        $this->assertSame(10, $bulletin->heures);
        $this->assertSame(10000, $bulletin->salaire_brut);
    }

    /** Zéro saisi à la main est une confirmation, pas une absence de réponse : il n'est pas refusé comme un zéro déduit de Ma journée le serait. */
    public function test_zero_heure_saisi_explicitement_est_accepte(): void
    {
        $agent = $this->agent('SONG ERIC MUNYAM', [
            'mode' => 'horaire', 'taux_horaire' => 1000, 'salaire_base' => 0,
        ]);

        $bulletin = app(PaieService::class)->preparer($agent, 2026, 1, ['heures' => 0]);

        $this->assertSame(0, $bulletin->heures);
        $this->assertSame(0, $bulletin->salaire_brut);
        $this->assertSame(0, $bulletin->net_a_payer);
    }

    private function affectation(Personnel $enseignant): ClasseMatiere
    {
        $classe = Classe::create(['school_id' => $this->school->id, 'nom' => 'ELECTRICAL 1']);
        $matiere = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Electrical Installation']);

        return ClasseMatiere::create([
            'classe_id' => $classe->id, 'matiere_id' => $matiere->id,
            'personnel_id' => $enseignant->id, 'coefficient' => 1,
        ]);
    }

    private function seance(ClasseMatiere $classeMatiere, string $date, string $debut, string $fin, string $statut): Seance
    {
        return Seance::create([
            'school_id' => $this->school->id,
            'classe_id' => $classeMatiere->classe_id,
            'classe_matiere_id' => $classeMatiere->id,
            'date_seance' => $date,
            'heure_debut' => $debut,
            'heure_fin' => $fin,
            'statut' => $statut,
        ]);
    }

    public function test_un_vacataire_sans_heures_saisies_est_refuse(): void
    {
        $agent = $this->agent('SONG ERIC MUNYAM', [
            'mode' => 'horaire', 'taux_horaire' => 1000, 'salaire_base' => 0,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("payé à l'heure");

        app(PaieService::class)->preparer($agent, 2026, 1);
    }

    /**
     * Un vacataire n'est pas salarié : ni IRPP, ni CNPS, ni aucune charge du
     * barème légal, quel que soit le montant de ses heures. Forcer le barème
     * légal (celui qui prélève réellement, contrairement au barème maison des
     * autres tests) est ce qui distingue ce test des précédents — sans le
     * contournement du barème pour les vacataires, cette assertion échouerait.
     */
    public function test_un_vacataire_ne_subit_aucune_charge_meme_sous_le_bareme_legal(): void
    {
        config(['paie.bareme' => 'legal']);
        $this->app->forgetInstance(\App\Services\Paie\Bareme::class);

        $agent = $this->agent('SONG ERIC MUNYAM', [
            'mode' => 'horaire', 'taux_horaire' => 5000, 'salaire_base' => 0,
        ]);

        $bulletin = app(PaieService::class)->preparer($agent, 2026, 1, ['heures' => 100]);

        // 100 h x 5000 F : sous le barème légal, un salarié mensuel paierait
        // ici IRPP et CNPS. Le vacataire, lui, perçoit tout.
        $this->assertSame(500000, $bulletin->salaire_brut);
        $this->assertSame(0, $bulletin->net_taxable);
        $this->assertSame(0, $bulletin->charges_salariales);
        $this->assertSame(0, $bulletin->charges_patronales);
        $this->assertSame(500000, $bulletin->net_a_payer);
        $this->assertCount(0, $bulletin->retenues);

        // Une seule ligne de gain : les heures, pas les six libellés du salarié mensuel.
        $this->assertCount(1, $bulletin->gains);
        $this->assertStringContainsString('Vacation', $bulletin->gains->first()->libelle);
    }

    public function test_le_mensuel_ignore_les_heures(): void
    {
        $bulletin = app(PaieService::class)->preparer($this->agent(), 2026, 1, ['heures' => 999]);

        $this->assertSame(60000, $bulletin->salaire_brut);
        $this->assertNull($bulletin->taux_horaire);
    }

    public function test_une_remuneration_horaire_s_enregistre_sans_salaire_de_base(): void
    {
        $agent = $this->agent('SONG ERIC MUNYAM');

        $remuneration = Remuneration::updateOrCreate(
            ['personnel_id' => $agent->id, 'date_effet' => '2025-10-01'],
            ['school_id' => $this->school->id, 'mode' => 'horaire', 'taux_horaire' => 1100, 'salaire_base' => 0],
        );

        $this->assertTrue($remuneration->estHoraire());
        $this->assertSame(1100, $remuneration->taux_horaire);
    }

    // ------------------------------------------------------- 4. bordereau

    private function bulletinPaye(string $nom, ?string $banque, ?string $compte): void
    {
        $agent = $this->agent($nom);
        $agent->update(['banque' => $banque, 'numero_compte' => $compte]);

        $service = app(PaieService::class);
        $service->arreter($service->preparer($agent, 2026, 1));
    }

    public function test_le_bordereau_se_range_par_banque(): void
    {
        $this->bulletinPaye('NCHANG SYLVIE', 'NTARINKON', '827508');
        $this->bulletinPaye('AWA MISPA LUM', 'NTARINKON', '5285-006');
        $this->bulletinPaye('BOUBA EMMANUEL', 'SUNRISE', '319');

        $bordereau = app(BordereauVirementService::class)->etablir($this->school->id, 2026, 1);

        $this->assertCount(2, $bordereau['banques']);
        $this->assertSame('NTARINKON', $bordereau['banques'][0]['banque']);
        $this->assertSame(2, $bordereau['banques'][0]['effectif']);
        $this->assertSame(1, $bordereau['banques'][1]['effectif']);
    }

    public function test_le_montant_vire_est_arrondi_a_la_centaine_inferieure(): void
    {
        config(['paie.maison.charges_salariales_supportees_par_employeur' => false]);
        $this->bulletinPaye('NCHANG SYLVIE', 'NTARINKON', '827508');

        $ligne = app(BordereauVirementService::class)->etablir($this->school->id, 2026, 1)['banques'][0]['lignes'][0];

        // Net de 56 652 → 56 600 viré, 52 F d'appoint restés en caisse.
        $this->assertSame(56652, $ligne['net_a_payer']);
        $this->assertSame(56600, $ligne['montant']);
        $this->assertSame(52, $ligne['arrondi']);
    }

    public function test_un_agent_sans_domiciliation_est_signale_hors_bordereau(): void
    {
        $this->bulletinPaye('NCHANG SYLVIE', 'NTARINKON', '827508');
        $this->bulletinPaye('EUPHRASIE', null, null);

        $bordereau = app(BordereauVirementService::class)->etablir($this->school->id, 2026, 1);

        $this->assertCount(1, $bordereau['sans_domiciliation']);
        $this->assertSame('EUPHRASIE', $bordereau['sans_domiciliation'][0]['nom_complet']);
        $this->assertSame(1, $bordereau['banques'][0]['effectif']);
    }

    public function test_un_brouillon_ne_part_pas_au_bordereau(): void
    {
        $agent = $this->agent('NCHANG SYLVIE');
        $agent->update(['banque' => 'NTARINKON', 'numero_compte' => '827508']);
        app(PaieService::class)->preparer($agent, 2026, 1);

        $bordereau = app(BordereauVirementService::class)->etablir($this->school->id, 2026, 1);

        $this->assertSame([], $bordereau['banques']);
        $this->assertSame(0, $bordereau['total']);
    }
}
