<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\DossierScolarite;
use App\Models\Eleve;
use App\Models\GrilleFrais;
use App\Models\School;
use App\Models\Setting;
use App\Models\TrancheScolarite;
use App\Models\User;
use App\Services\EcheancierService;
use App\Services\ScolariteService;
use App\Support\CataloguePermissions;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Échéancier de scolarité : découpage de l'année en tranches, restitution au
 * parent et qualification de l'insolvabilité.
 *
 * Le point tenu ici : une famille à jour de sa première tranche ne doit plus
 * figurer parmi les insolvables à côté de celle qui n'a rien versé.
 */
class EcheancierScolariteTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private AnneeScolaire $annee;

    private Classe $classe;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->school = School::create([
            'name' => 'Elites Primaire', 'code' => 'EP', 'type' => 'primaire', 'is_active' => true,
        ]);

        $this->annee = AnneeScolaire::create([
            'school_id' => $this->school->id, 'libelle' => '2025-2026',
            'date_debut' => '2025-09-01', 'date_fin' => '2026-07-31', 'is_active' => true,
        ]);

        $this->classe = Classe::create([
            'school_id' => $this->school->id, 'nom' => 'CE1-A',
        ]);

        GrilleFrais::create([
            'school_id' => $this->school->id,
            'annee_scolaire_id' => $this->annee->id,
            'classe_id' => null,
            'montant' => 90000,
        ]);

        $this->admin = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $this->admin->assignRole('super_admin');
    }

    /** Échéancier en trois tranches : 40 % au 15/10, 30 % au 15/01, 30 % au 15/04. */
    private function echeancier(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/v1/tranches-scolarite', [
                'school_id' => $this->school->id,
                'annee_scolaire_id' => $this->annee->id,
                'tranches' => [
                    ['libelle' => '1re tranche', 'pourcentage' => 40, 'date_echeance' => '2025-10-15'],
                    ['libelle' => '2e tranche', 'pourcentage' => 30, 'date_echeance' => '2026-01-15'],
                    ['libelle' => '3e tranche', 'pourcentage' => 30, 'date_echeance' => '2026-04-15'],
                ],
            ])
            ->assertOk();
    }

    private function dossier(int $verse = 0): DossierScolarite
    {
        $eleve = Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $this->classe->id,
            'nom_complet' => 'ELEVE UN', 'sexe' => 'M', 'statut' => 'actif',
        ]);

        // `dossier()` ouvre le dossier au premier accès — c'est le point
        // d'entrée réel du service, pas une méthode de création dédiée.
        $dossier = app(ScolariteService::class)->dossier($eleve, $this->annee);

        if ($verse > 0) {
            app(ScolariteService::class)->encaisser($dossier, [
                'montant' => $verse,
                'mode' => 'especes',
                'date_versement' => '2025-09-20',
            ], $this->admin->id);
        }

        return $dossier->fresh();
    }

    // --------------------------------------------------------- Paramétrage

    public function test_l_echeancier_s_enregistre_et_se_relit(): void
    {
        $this->echeancier();

        $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/tranches-scolarite?annee_scolaire_id={$this->annee->id}")
            ->assertOk()
            ->assertJsonCount(3, 'data.tranches')
            ->assertJsonPath('data.tranches.0.libelle', '1re tranche')
            ->assertJsonPath('data.tranches.0.ordre', 1);
    }

    /** La somme doit valoir 100 % : un échéancier bancal fausserait tout le reste. */
    public function test_un_echeancier_qui_ne_fait_pas_cent_pourcent_est_refuse(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/v1/tranches-scolarite', [
                'school_id' => $this->school->id,
                'annee_scolaire_id' => $this->annee->id,
                'tranches' => [
                    ['libelle' => '1re', 'pourcentage' => 40, 'date_echeance' => '2025-10-15'],
                    ['libelle' => '2e', 'pourcentage' => 30, 'date_echeance' => '2026-01-15'],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('tranches');
    }

    /** L'ordre découle des dates : il ne peut pas contredire le calendrier. */
    public function test_l_ordre_suit_les_dates_quel_que_soit_l_ordre_de_saisie(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/v1/tranches-scolarite', [
                'school_id' => $this->school->id,
                'annee_scolaire_id' => $this->annee->id,
                'tranches' => [
                    ['libelle' => 'Avril', 'pourcentage' => 30, 'date_echeance' => '2026-04-15'],
                    ['libelle' => 'Octobre', 'pourcentage' => 40, 'date_echeance' => '2025-10-15'],
                    ['libelle' => 'Janvier', 'pourcentage' => 30, 'date_echeance' => '2026-01-15'],
                ],
            ])
            ->assertOk();

        $this->assertSame(
            ['Octobre', 'Janvier', 'Avril'],
            TrancheScolarite::orderBy('ordre')->pluck('libelle')->all(),
        );
    }

    /** Un échéancier vide rend la scolarité exigible en une fois. */
    public function test_un_echeancier_vide_supprime_le_decoupage(): void
    {
        $this->echeancier();

        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/v1/tranches-scolarite', [
                'school_id' => $this->school->id,
                'annee_scolaire_id' => $this->annee->id,
                'tranches' => [],
            ])
            ->assertOk();

        $this->assertSame(0, TrancheScolarite::count());
    }

    // ------------------------------------------------------------ Répartition

    public function test_les_montants_se_repartissent_selon_les_pourcentages(): void
    {
        $this->echeancier();
        $dossier = $this->dossier();

        $echeancier = app(EcheancierService::class)
            ->pourDossier($dossier, CarbonImmutable::parse('2025-09-01'));

        $this->assertTrue($echeancier['actif']);
        $this->assertSame([36000, 27000, 27000], array_column($echeancier['tranches'], 'montant'));
        $this->assertSame(90000, array_sum(array_column($echeancier['tranches'], 'montant')));
    }

    /** La dernière tranche absorbe l'arrondi : la somme retombe sur le dû. */
    public function test_la_derniere_tranche_absorbe_l_arrondi(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/v1/tranches-scolarite', [
                'school_id' => $this->school->id,
                'annee_scolaire_id' => $this->annee->id,
                'tranches' => [
                    ['libelle' => 'T1', 'pourcentage' => 33.33, 'date_echeance' => '2025-10-15'],
                    ['libelle' => 'T2', 'pourcentage' => 33.33, 'date_echeance' => '2026-01-15'],
                    ['libelle' => 'T3', 'pourcentage' => 33.34, 'date_echeance' => '2026-04-15'],
                ],
            ])
            ->assertOk();

        $echeancier = app(EcheancierService::class)
            ->pourDossier($this->dossier(), CarbonImmutable::parse('2025-09-01'));

        $this->assertSame(90000, array_sum(array_column($echeancier['tranches'], 'montant')));
    }

    /** Les versements s'imputent sur les échéances les plus anciennes d'abord. */
    public function test_les_versements_s_imputent_de_la_plus_ancienne_a_la_plus_recente(): void
    {
        $this->echeancier();
        $dossier = $this->dossier(50000);

        $tranches = app(EcheancierService::class)
            ->pourDossier($dossier, CarbonImmutable::parse('2025-11-01'))['tranches'];

        // 50 000 couvrent les 36 000 de la 1re, puis 14 000 de la 2e.
        $this->assertSame(36000, $tranches[0]['montant_paye']);
        $this->assertSame('soldee', $tranches[0]['statut']);
        $this->assertSame(14000, $tranches[1]['montant_paye']);
        $this->assertSame(0, $tranches[2]['montant_paye']);
    }

    // --------------------------------------------------------- Insolvabilité

    /** Le cœur de la demande : à jour de sa tranche, la famille n'est pas insolvable. */
    public function test_une_famille_a_jour_de_sa_tranche_n_est_pas_insolvable(): void
    {
        $this->echeancier();
        $this->travelTo(CarbonImmutable::parse('2025-11-01'));

        // 36 000 = exactement la 1re tranche, seule échue au 1er novembre.
        $this->dossier(36000);

        $insolvables = app(ScolariteService::class)->insolvables([$this->school->id]);

        $this->assertCount(0, $insolvables['lignes']);
    }

    /** Alors qu'elle reste redevable de 54 000 F sur l'année. */
    public function test_la_meme_famille_reste_redevable_du_solde_annuel(): void
    {
        $this->echeancier();
        $this->travelTo(CarbonImmutable::parse('2025-11-01'));
        $dossier = $this->dossier(36000);

        $this->assertSame(54000, $dossier->fresh()->reste_a_payer);
    }

    public function test_une_famille_en_retard_sur_une_tranche_est_insolvable(): void
    {
        $this->echeancier();
        $this->travelTo(CarbonImmutable::parse('2025-11-01'));

        $this->dossier(10000);

        $insolvables = app(ScolariteService::class)->insolvables([$this->school->id]);

        $this->assertCount(1, $insolvables['lignes']);
        $ligne = $insolvables['lignes'][0];

        $this->assertTrue($ligne['echeancier_actif']);
        $this->assertSame(36000, $ligne['du_a_ce_jour']);
        $this->assertSame(26000, $ligne['retard']);
        $this->assertCount(1, $ligne['tranches_en_retard']);
        $this->assertSame('1re tranche', $ligne['tranches_en_retard'][0]['libelle']);
    }

    /** Le délai de grâce diffère le basculement, il ne l'annule pas. */
    public function test_le_delai_de_grace_differe_le_retard(): void
    {
        $this->echeancier();
        Setting::set($this->school->id, 'delai_grace_echeance', '15');

        // Au 20 octobre, l'échéance du 15 est passée mais le délai court encore.
        $this->travelTo(CarbonImmutable::parse('2025-10-20'));
        $this->dossier(0);

        $this->assertCount(0, app(ScolariteService::class)->insolvables([$this->school->id])['lignes']);

        // Au 5 novembre, les 15 jours sont écoulés.
        $this->travelTo(CarbonImmutable::parse('2025-11-05'));
        $this->assertCount(1, app(ScolariteService::class)->insolvables([$this->school->id])['lignes']);
    }

    /** Sans échéancier, le comportement antérieur tient : tout est exigible. */
    public function test_sans_echeancier_le_total_de_l_annee_reste_exigible(): void
    {
        $this->travelTo(CarbonImmutable::parse('2025-11-01'));
        $dossier = $this->dossier(36000);

        $echeancier = app(EcheancierService::class)->pourDossier($dossier);

        $this->assertFalse($echeancier['actif']);
        $this->assertSame(90000, $echeancier['du_a_ce_jour']);
        $this->assertSame(54000, $echeancier['retard']);
        $this->assertCount(1, app(ScolariteService::class)->insolvables([$this->school->id])['lignes']);
    }

    // -------------------------------------------------------- Portail parent

    public function test_le_parent_recoit_son_echeancier(): void
    {
        $this->echeancier();
        $this->travelTo(CarbonImmutable::parse('2025-11-01'));
        $dossier = $this->dossier(36000);

        $echeancier = app(EcheancierService::class)->pourDossier($dossier->fresh());

        $this->assertSame('soldee', $echeancier['tranches'][0]['statut']);
        $this->assertSame('a_venir', $echeancier['tranches'][1]['statut']);
        // La prochaine échéance est celle que le portail met en avant.
        $this->assertSame('2e tranche', $echeancier['prochaine_echeance']['libelle']);
        $this->assertSame(0, $echeancier['retard']);
    }
}
