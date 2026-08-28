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
 * Échéancier de remboursement (durée, mensualité plafonnée à la moitié du
 * brut) et circuit des demandes soumises par le personnel lui-même.
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

    /** @param array<string, mixed> $donnees */
    private function accorder(array $donnees = []): AvanceSalaire
    {
        return $this->avances()->accorder($this->school->id, [
            'personnel_id' => $this->agent->id,
            'montant' => 100000,
            'mensualite' => 20000,
            'date_avance' => '2024-04-01',
            ...$donnees,
        ], null);
    }

    public function test_l_avance_accordee_porte_son_echeancier(): void
    {
        $avance = $this->accorder();

        // 100 000 à 20 000/mois : 5 échéances, sous le plafond de 24 000.
        $this->assertSame(5, $avance->nombre_mois);
        $this->assertSame(20000, $avance->mensualite);
    }

    public function test_le_nombre_de_mois_arrondit_au_mois_superieur(): void
    {
        // 90 000 à 23 000/mois : 3,91 — la dernière échéance solde le reste
        // (21 000) plutôt que d'imposer une mensualité uniforme.
        $avance = $this->accorder(['montant' => 90000, 'mensualite' => 23000]);

        $this->assertSame(4, $avance->nombre_mois);
        $this->assertSame(23000, $avance->mensualite);
    }

    public function test_une_mensualite_au_dela_de_la_moitie_du_brut_est_refusee(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('dépasse 50%');

        $this->accorder(['mensualite' => 50000]);
    }

    public function test_la_mensualite_juste_egale_au_plafond_passe(): void
    {
        $avance = $this->accorder(['montant' => 48000, 'mensualite' => 24000]);

        $this->assertSame(24000, $avance->mensualite);
    }

    public function test_le_debut_de_remboursement_peut_etre_decale(): void
    {
        $avance = $this->accorder(['mois_debut_remboursement' => '2024-07-01']);

        $this->assertSame('2024-07-01', $avance->mois_debut_remboursement->format('Y-m-d'));
    }

    public function test_a_defaut_le_remboursement_commence_le_mois_en_cours(): void
    {
        $avance = $this->accorder();

        $this->assertSame(now()->startOfMonth()->format('Y-m-d'), $avance->mois_debut_remboursement->format('Y-m-d'));
    }

    public function test_la_retenue_n_est_pas_due_avant_le_mois_de_debut(): void
    {
        $this->accorder(['mois_debut_remboursement' => '2024-07-01']);

        $this->assertSame(0, $this->avances()->mensualiteDue($this->agent->id, '2024-06-30'));
        $this->assertSame(20000, $this->avances()->mensualiteDue($this->agent->id, '2024-07-15'));
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
        $demande = $this->demandes()->soumettre($this->agent, ['montant' => 100000, 'mensualite' => 20000]);

        $this->assertSame('en_attente', $demande->statut);
        $this->assertSame(0, AvanceSalaire::count());
    }

    public function test_la_validation_cree_l_avance_avec_l_echeancier_demande(): void
    {
        $admin = $this->admin();
        $demande = $this->demandes()->soumettre($this->agent, ['montant' => 100000, 'mensualite' => 20000, 'motif' => 'Frais médicaux']);

        $demande = $this->demandes()->valider($demande, $admin->id);

        $avance = AvanceSalaire::sole();
        $this->assertSame('validee', $demande->statut);
        $this->assertSame($avance->id, $demande->avance_salaire_id);
        $this->assertSame($admin->id, $demande->traite_par);
        $this->assertSame(100000, $avance->montant);
        $this->assertSame(5, $avance->nombre_mois);
        $this->assertSame(20000, $avance->mensualite);
        $this->assertSame('Frais médicaux', $avance->motif);
    }

    public function test_le_rejet_conserve_le_motif_et_n_accorde_rien(): void
    {
        $demande = $this->demandes()->soumettre($this->agent, ['montant' => 100000, 'mensualite' => 20000]);

        $demande = $this->demandes()->rejeter($demande, 'Trésorerie insuffisante ce mois-ci', $this->admin()->id);

        $this->assertSame('rejetee', $demande->statut);
        $this->assertSame('Trésorerie insuffisante ce mois-ci', $demande->motif_rejet);
        $this->assertSame(0, AvanceSalaire::count());
    }

    public function test_une_demande_deja_traitee_ne_se_traite_pas_deux_fois(): void
    {
        $demande = $this->demandes()->soumettre($this->agent, ['montant' => 100000, 'mensualite' => 20000]);
        $this->demandes()->valider($demande);

        $this->expectException(RuntimeException::class);
        $this->demandes()->valider($demande->fresh());
    }

    public function test_un_employe_n_a_qu_une_demande_en_attente_a_la_fois(): void
    {
        $this->demandes()->soumettre($this->agent, ['montant' => 50000, 'mensualite' => 10000]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('déjà en attente');

        $this->demandes()->soumettre($this->agent, ['montant' => 20000, 'mensualite' => 6667]);
    }

    public function test_une_demande_hors_plafond_est_refusee_des_la_soumission(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('dépasse 50%');

        $this->demandes()->soumettre($this->agent, ['montant' => 100000, 'mensualite' => 50000]);

        $this->assertSame(0, DemandeAvanceSalaire::count());
    }

    public function test_l_employe_consulte_ses_avances_et_son_plafond(): void
    {
        $this->accorder();
        $this->demandes()->soumettre($this->agent, ['montant' => 30000, 'mensualite' => 10000]);

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
