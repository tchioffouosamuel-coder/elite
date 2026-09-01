<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\Personnel;
use App\Models\School;
use App\Models\Seance;
use App\Models\User;
use App\Support\CataloguePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * L'appel ne doit pouvoir se faire qu'à partir de l'heure de la séance —
 * avant ça, personne n'est encore en cours pour être pointé.
 */
class SeanceAppelHoraireTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private ClasseMatiere $classeMatiere;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->school = School::create(['name' => 'Elites Tech', 'code' => 'EBT', 'type' => 'secondaire', 'is_active' => true]);

        $niveau = Niveau::create(['code' => 'college', 'name_fr' => 'Collège', 'name_en' => 'College', 'ordre' => 1]);
        $annee = AnneeScolaire::create([
            'school_id' => $this->school->id, 'libelle' => '2026-2027',
            'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31', 'is_active' => true,
        ]);
        $classe = Classe::create([
            'school_id' => $this->school->id, 'niveau_id' => $niveau->id, 'annee_scolaire_id' => $annee->id,
            'nom' => '6ème A',
        ]);
        $matiere = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Mathématiques']);
        $enseignant = Personnel::create([
            'school_id' => $this->school->id, 'nom_complet' => 'SONG ERIC MUNYAM', 'sexe' => 'M', 'statut' => 'actif',
        ]);
        $this->classeMatiere = ClasseMatiere::create([
            'classe_id' => $classe->id, 'matiere_id' => $matiere->id, 'personnel_id' => $enseignant->id,
            'statut' => 'actif',
        ]);

        $this->admin = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $this->admin->assignRole('super_admin');
    }

    private function creerSeance(string $dateSeance, string $heureDebut): Seance
    {
        return Seance::create([
            'school_id' => $this->school->id,
            'classe_id' => $this->classeMatiere->classe_id,
            'classe_matiere_id' => $this->classeMatiere->id,
            'date_seance' => $dateSeance,
            'heure_debut' => $heureDebut,
            'heure_fin' => '10:00',
            'statut' => 'prevue',
        ]);
    }

    public function test_refuse_l_appel_d_une_seance_qui_n_a_pas_encore_commence(): void
    {
        $demain = Carbon::now()->addDay();
        $seance = $this->creerSeance($demain->toDateString(), '08:00');

        $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->postJson("/api/v1/seances/{$seance->id}/appel", ['lignes' => []])
            ->assertStatus(403);
    }

    public function test_accepte_l_appel_d_une_seance_deja_commencee(): void
    {
        $hier = Carbon::now()->subDay();
        $seance = $this->creerSeance($hier->toDateString(), '08:00');

        $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->postJson("/api/v1/seances/{$seance->id}/appel", ['lignes' => []])
            ->assertStatus(422); // lignes vide refusé par la validation, mais on a passé le contrôle d'horaire.
    }
}
