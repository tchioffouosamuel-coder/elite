<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\Departement;
use App\Models\Eleve;
use App\Models\Matiere;
use App\Models\Note;
use App\Models\School;
use App\Models\Sequence;
use App\Models\Trimestre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Régression relevée par la revue du centre de documents : les statistiques
 * d'un département (écran et PDF) visaient une relation `Matiere::classes`
 * inexistante et des colonnes absentes de `notes` (`classe_id`,
 * `trimestre_id`, `note`) — elles plantaient dès qu'une matière était
 * rattachée au département, la fiche du département aussi.
 */
class DepartementStatistiquesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private School $ecole;

    private Departement $departement;

    private Trimestre $trimestre;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $this->ecole = School::create(['name' => 'Elites Secondaire', 'code' => 'ES', 'type' => 'secondaire', 'is_active' => true]);
        $this->admin = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $this->ecole->id, 'is_active' => true,
        ]);
        $this->admin->assignRole('super_admin');

        $annee = AnneeScolaire::create([
            'school_id' => $this->ecole->id, 'libelle' => '2026-2027',
            'date_debut' => '2026-09-01', 'date_fin' => '2027-07-15', 'is_active' => true,
        ]);
        $this->trimestre = Trimestre::create([
            'annee_scolaire_id' => $annee->id, 'libelle' => 'Trimestre 1', 'ordre' => 1,
            'date_debut' => '2026-09-01', 'date_fin' => '2026-12-15', 'is_active' => true,
        ]);
        $sequence = Sequence::create(['trimestre_id' => $this->trimestre->id, 'ordre' => 1, 'libelle' => 'Séquence 1']);

        $this->departement = Departement::create(['school_id' => $this->ecole->id, 'nom' => 'Sciences']);
        $matiere = Matiere::create(['school_id' => $this->ecole->id, 'departement_id' => $this->departement->id, 'nom' => 'Mathématiques']);
        $classe = Classe::create(['school_id' => $this->ecole->id, 'nom' => '6e A']);
        $cm = ClasseMatiere::create(['classe_id' => $classe->id, 'matiere_id' => $matiere->id, 'coefficient' => 4]);

        foreach ([8, 12, 16] as $i => $valeur) {
            $eleve = Eleve::create([
                'school_id' => $this->ecole->id, 'classe_id' => $classe->id, 'matricule' => "E{$i}",
                'nom_complet' => "Eleve {$i}", 'sexe' => 'M', 'statut' => 'actif',
            ]);
            Note::create(['eleve_id' => $eleve->id, 'classe_matiere_id' => $cm->id, 'sequence_id' => $sequence->id, 'valeur' => $valeur]);
        }
    }

    public function test_les_statistiques_portent_sur_les_notes_du_trimestre(): void
    {
        $reponse = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/departements/{$this->departement->id}/statistiques/pedagogiques?trimestre_id={$this->trimestre->id}")
            ->assertOk();

        $this->assertSame(3, $reponse->json('data.matieres.0.effectif_eleves'));
        $this->assertEquals(12, $reponse->json('data.matieres.0.moyenne'));
        $this->assertEquals(66.67, $reponse->json('data.matieres.0.taux_reussite'));
        $this->assertEquals(12, $reponse->json('data.stats_consolidees.moyenne_generale'));
    }

    public function test_le_pdf_des_statistiques_est_produit(): void
    {
        $reponse = $this->actingAs($this->admin, 'sanctum')
            ->get("/api/v1/departements/{$this->departement->id}/statistiques/pedagogiques/export-pdf")
            ->assertOk();

        $this->assertStringStartsWith('%PDF', $reponse->streamedContent());
    }

    public function test_la_fiche_du_departement_charge_ses_matieres_et_leurs_classes(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/departements/{$this->departement->id}")
            ->assertOk();
    }
}
