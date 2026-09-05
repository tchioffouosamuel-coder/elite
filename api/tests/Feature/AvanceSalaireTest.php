<?php

namespace Tests\Feature;

use App\Models\AvanceSalaire;
use App\Models\DemandeAvanceSalaire;
use App\Models\Personnel;
use App\Models\Remuneration;
use App\Models\School;
use App\Models\User;
use App\Services\AvanceSalaireService;
use App\Services\DemandeAvanceSalaireService;
use App\Support\CataloguePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Échéancier de remboursement ligne à ligne (un montant par mois, plafonné à
 * la moitié du brut) et circuit des demandes soumises par le personnel
 * lui-même.
 */
class AvanceSalaireTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private Personnel $agent;

    protected function setUp(): void
    {
        parent::setUp();

        // `notifierParPermission` s'appuie sur le catalogue Spatie : sans les
        // privilèges en base, prévenir la paie d'une demande échouerait.
        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }

        $this->school = School::create(['name' => 'Elites Tech', 'code' => 'EBT', 'type' => 'secondaire', 'is_active' => true]);
        $this->agent = Personnel::create([
            'school_id' => $this->school->id, 'nom_complet' => 'BOIGA DJANABOU', 'sexe' => 'F', 'statut' => 'actif',
        ]);

        // Brut de 48 000 : la retenue mensuelle ne peut dépasser 24 000.
        Remuneration::create([
            'school_id' => $this->school->id, 'personnel_id' => $this->agent->id, 'date_effet' => '2024-01-01',
            'salaire_base' => 42000, 'prime_anciennete' => 1000,
            'prime_communication' => 2500, 'prime_transport' => 2500,
        ]);
    }

    private function avances(): AvanceSalaireService
    {
        return app(AvanceSalaireService::class);
    }

    private function demandes(): DemandeAvanceSalaireService
    {
        return app(DemandeAvanceSalaireService::class);
    }

    /** @return array<int, array{mois: string, montant: int}> */
    private function echeancierUniforme(int $montant = 100000, int $nombreMois = 5, string $moisDebut = '2024-04-01'): array
    {
        return $this->avances()->genererEcheancierUniforme($montant, $nombreMois, $moisDebut);
    }

    /** @param array<string, mixed> $donnees */
    private function accorder(array $donnees = []): AvanceSalaire
    {
        $montant = $donnees['montant'] ?? 100000;
        $echeancier = $donnees['echeancier'] ?? $this->echeancierUniforme($montant);

        return $this->avances()->accorder($this->school->id, [
            'personnel_id' => $this->agent->id,
            'montant' => $montant,
            'echeancier' => $echeancier,
            'date_avance' => '2024-04-01',
            ...array_diff_key($donnees, ['echeancier' => null]),
        ], null);
    }

    public function test_l_avance_accordee_porte_son_echeancier(): void
    {
        $avance = $this->accorder();

        // 100 000 sur 5 mois à 20 000 chacun, sous le plafond de 24 000.
        $this->assertSame(5, $avance->nombre_mois);
        $this->assertSame(20000, $avance->mensualite);
        $this->assertCount(5, $avance->echeances);
        $this->assertSame(20000, $avance->echeances->first()->montant_prevu);
    }

    public function test_un_echeancier_personnalise_non_uniforme_est_accepte(): void
    {
        // L'employé choisit lui-même combien débiter chaque mois, tant que la
        // somme correspond au montant emprunté et qu'aucune ligne ne dépasse
        // le plafond.
        $echeancier = [
            ['mois' => '2024-04-01', 'montant' => 15000],
            ['mois' => '2024-05-01', 'montant' => 24000],
            ['mois' => '2024-06-01', 'montant' => 11000],
        ];

        $avance = $this->accorder(['montant' => 50000, 'echeancier' => $echeancier]);

        $this->assertSame(3, $avance->nombre_mois);
        $this->assertSame(15000, $avance->echeances[0]->montant_prevu);
        $this->assertSame(24000, $avance->echeances[1]->montant_prevu);
        $this->assertSame(11000, $avance->echeances[2]->montant_prevu);
    }

    public function test_une_somme_d_echeancier_differente_du_montant_est_refusee(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ne correspond pas au montant');

        $this->accorder([
            'montant' => 50000,
            'echeancier' => [['mois' => '2024-04-01', 'montant' => 20000]],
        ]);
    }

    public function test_une_ligne_au_dela_de_la_moitie_du_brut_est_refusee(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('dépasse 50%');

        $this->accorder([
            'montant' => 50000,
            'echeancier' => [['mois' => '2024-04-01', 'montant' => 50000]],
        ]);
    }

    public function test_une_ligne_juste_egale_au_plafond_passe(): void
    {
        $avance = $this->accorder([
            'montant' => 24000,
            'echeancier' => [['mois' => '2024-04-01', 'montant' => 24000]],
        ]);

        $this->assertSame(24000, $avance->echeances->first()->montant_prevu);
    }

    public function test_le_debut_de_remboursement_se_deduit_du_mois_minimal(): void
    {
        $avance = $this->accorder(['echeancier' => $this->echeancierUniforme(100000, 5, '2024-07-01')]);

        $this->assertSame('2024-07-01', $avance->mois_debut_remboursement->format('Y-m-d'));
    }

    public function test_la_retenue_n_est_pas_due_avant_le_mois_de_debut(): void
    {
        $this->accorder(['echeancier' => $this->echeancierUniforme(100000, 5, '2024-07-01')]);

        $this->assertSame(0, $this->avances()->mensualiteDue($this->agent->id, '2024-06-30'));
        $this->assertSame(20000, $this->avances()->mensualiteDue($this->agent->id, '2024-07-15'));
    }

    public function test_la_retenue_du_mois_suit_le_montant_planifie_pour_ce_mois(): void
    {
        // Échéancier non uniforme : la retenue du mois doit suivre la ligne
        // prévue pour ce mois précis, pas une mensualité moyenne.
        $this->accorder([
            'montant' => 50000,
            'echeancier' => [
                ['mois' => '2024-04-01', 'montant' => 15000],
                ['mois' => '2024-05-01', 'montant' => 24000],
                ['mois' => '2024-06-01', 'montant' => 11000],
            ],
        ]);

        $this->assertSame(15000, $this->avances()->mensualiteDue($this->agent->id, '2024-04-10'));
        $this->assertSame(24000, $this->avances()->mensualiteDue($this->agent->id, '2024-05-10'));
        $this->assertSame(11000, $this->avances()->mensualiteDue($this->agent->id, '2024-06-10'));

        // Une fois le solde épuisé, plus rien n'est dû même après la fin du
        // plan (le rattrapage ne s'applique qu'à un solde encore ouvert).
        $avance = AvanceSalaire::sole();
        $avance->remboursements()->createMany([
            ['montant' => 15000, 'date_remboursement' => '2024-04-10', 'mode' => 'retenue_salaire'],
            ['montant' => 24000, 'date_remboursement' => '2024-05-10', 'mode' => 'retenue_salaire'],
            ['montant' => 11000, 'date_remboursement' => '2024-06-10', 'mode' => 'retenue_salaire'],
        ]);
        $this->assertSame(0, $this->avances()->mensualiteDue($this->agent->id, '2024-07-10'));
    }

    public function test_le_plafond_suit_la_derniere_remuneration_en_date(): void
    {
        Remuneration::create([
            'school_id' => $this->school->id, 'personnel_id' => $this->agent->id, 'date_effet' => '2024-03-01',
            'salaire_base' => 100000, 'prime_anciennete' => 0,
            'prime_communication' => 0, 'prime_transport' => 0,
        ]);

        $this->assertSame(
            ['salaire_brut' => 100000, 'plafond_mensualite' => 50000],
            $this->avances()->plafond($this->agent->fresh()),
        );
    }

    public function test_sans_remuneration_aucun_plafond_ne_peut_etre_calcule(): void
    {
        $sansPaie = Personnel::create([
            'school_id' => $this->school->id, 'nom_complet' => 'NKOLO ETIENNE', 'sexe' => 'M', 'statut' => 'actif',
        ]);

        $this->assertNull($this->avances()->plafond($sansPaie));

        $this->expectException(RuntimeException::class);
        $this->accorder(['personnel_id' => $sansPaie->id]);
    }

    public function test_une_demande_du_personnel_n_accorde_rien_avant_validation(): void
    {
        $demande = $this->demandes()->soumettre($this->agent, ['montant' => 100000, 'echeancier' => $this->echeancierUniforme()]);

        $this->assertSame('en_attente', $demande->statut);
        $this->assertSame(0, AvanceSalaire::count());
    }

    public function test_la_validation_cree_l_avance_avec_l_echeancier_demande(): void
    {
        $admin = $this->admin();
        $demande = $this->demandes()->soumettre($this->agent, [
            'montant' => 100000,
            'echeancier' => $this->echeancierUniforme(),
            'motif' => 'Frais médicaux',
        ]);

        $demande = $this->demandes()->valider($demande, $admin->id);

        $avance = AvanceSalaire::sole();
        $this->assertSame('validee', $demande->statut);
        $this->assertSame($avance->id, $demande->avance_salaire_id);
        $this->assertSame($admin->id, $demande->traite_par);
        $this->assertSame(100000, $avance->montant);
        $this->assertSame(5, $avance->nombre_mois);
        $this->assertSame(20000, $avance->mensualite);
        $this->assertSame('Frais médicaux', $avance->motif);
        $this->assertCount(5, $avance->echeances);
    }

    public function test_le_rejet_conserve_le_motif_et_n_accorde_rien(): void
    {
        $demande = $this->demandes()->soumettre($this->agent, ['montant' => 100000, 'echeancier' => $this->echeancierUniforme()]);

        $demande = $this->demandes()->rejeter($demande, 'Trésorerie insuffisante ce mois-ci', $this->admin()->id);

        $this->assertSame('rejetee', $demande->statut);
        $this->assertSame('Trésorerie insuffisante ce mois-ci', $demande->motif_rejet);
        $this->assertSame(0, AvanceSalaire::count());
    }

    public function test_une_demande_deja_traitee_ne_se_traite_pas_deux_fois(): void
    {
        $demande = $this->demandes()->soumettre($this->agent, ['montant' => 100000, 'echeancier' => $this->echeancierUniforme()]);
        $this->demandes()->valider($demande);

        $this->expectException(RuntimeException::class);
        $this->demandes()->valider($demande->fresh());
    }

    public function test_un_employe_n_a_qu_une_demande_en_attente_a_la_fois(): void
    {
        $this->demandes()->soumettre($this->agent, ['montant' => 50000, 'echeancier' => $this->echeancierUniforme(50000, 5, '2024-04-01')]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('déjà en attente');

        $this->demandes()->soumettre($this->agent, ['montant' => 20000, 'echeancier' => $this->echeancierUniforme(20000, 3, '2024-04-01')]);
    }

    public function test_une_demande_hors_plafond_est_refusee_des_la_soumission(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('dépasse 50%');

        $this->demandes()->soumettre($this->agent, [
            'montant' => 100000,
            'echeancier' => [['mois' => '2024-04-01', 'montant' => 50000], ['mois' => '2024-05-01', 'montant' => 50000]],
        ]);

        $this->assertSame(0, DemandeAvanceSalaire::count());
    }

    public function test_l_employe_consulte_ses_avances_et_son_plafond(): void
    {
        $this->accorder();
        $this->demandes()->soumettre($this->agent, ['montant' => 30000, 'echeancier' => $this->echeancierUniforme(30000, 3, '2024-04-01')]);

        $reponse = $this->actingAs($this->compteDe($this->agent), 'sanctum')
            ->getJson('/api/v1/mon-espace/avances')
            ->assertOk();

        $reponse->assertJsonPath('data.plafond.plafond_mensualite', 24000);
        $reponse->assertJsonPath('data.avances.0.mensualite', 20000);
        $reponse->assertJsonPath('data.demandes.0.statut', 'en_attente');
    }

    public function test_un_compte_sans_fiche_personnel_n_a_pas_d_espace_avances(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/v1/mon-espace/avances')
            ->assertNotFound();
    }

    private function admin(): User
    {
        return User::create([
            'school_id' => $this->school->id, 'name' => 'Économe', 'email' => 'econome@elites.test',
            'password' => Hash::make('secret'), 'is_active' => true,
        ]);
    }

    private function compteDe(Personnel $personnel): User
    {
        $user = User::create([
            'school_id' => $personnel->school_id, 'name' => $personnel->nom_complet,
            'email' => 'agent@elites.test', 'password' => Hash::make('secret'), 'is_active' => true,
        ]);

        $personnel->update(['user_id' => $user->id]);

        return $user;
    }
}
