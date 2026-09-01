<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\EmploiDuTemps;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\Personnel;
use App\Models\School;
use App\Models\Seance;
use App\Models\Trimestre;
use App\Models\User;
use App\Support\CataloguePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Modification des trimestres/années scolaires (auparavant seulement
 * créables/activables) et génération en masse des séances d'un trimestre —
 * pour ouvrir une période sans repasser classe par classe.
 */
class SessionTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private AnneeScolaire $annee;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->school = School::create(['name' => 'Elites Tech', 'code' => 'EBT', 'type' => 'secondaire', 'is_active' => true]);
        $this->annee = AnneeScolaire::create([
            'school_id' => $this->school->id, 'libelle' => '2026-2027',
            'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31', 'is_active' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $this->admin->assignRole('super_admin');
    }

    public function test_modifie_une_annee_scolaire(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->putJson("/api/v1/annees-scolaires/{$this->annee->id}", [
                'libelle' => '2026-2027 (corrigé)',
                'date_debut' => '2026-09-02',
                'date_fin' => '2027-07-30',
            ])
            ->assertOk()
            ->assertJsonPath('data.libelle', '2026-2027 (corrigé)');
    }

    public function test_refuse_un_libelle_deja_pris_par_une_autre_annee(): void
    {
        $autre = AnneeScolaire::create([
            'school_id' => $this->school->id, 'libelle' => '2027-2028',
            'date_debut' => '2027-09-01', 'date_fin' => '2028-07-31',
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->putJson("/api/v1/annees-scolaires/{$autre->id}", [
                'libelle' => '2026-2027', // déjà pris par $this->annee
                'date_debut' => '2027-09-01',
                'date_fin' => '2028-07-31',
            ])
            ->assertStatus(422);
    }

    public function test_modifie_un_trimestre_sans_toucher_a_son_annee(): void
    {
        $trimestre = Trimestre::create([
            'annee_scolaire_id' => $this->annee->id, 'libelle' => 'Trimestre 1', 'ordre' => 1,
            'date_debut' => '2026-09-01', 'date_fin' => '2026-12-19',
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->putJson("/api/v1/trimestres/{$trimestre->id}", [
                'libelle' => 'Trimestre 1 (corrigé)',
                'ordre' => 1,
                'date_debut' => '2026-09-01',
                'date_fin' => '2026-12-20',
            ])
            ->assertOk()
            ->assertJsonPath('data.libelle', 'Trimestre 1 (corrigé)')
            ->assertJsonPath('data.date_fin', '2026-12-20');
    }

    public function test_genere_les_seances_de_toutes_les_classes_de_l_annee_pour_un_trimestre(): void
    {
        $trimestre = Trimestre::create([
            'annee_scolaire_id' => $this->annee->id, 'libelle' => 'Trimestre 1', 'ordre' => 1,
            'date_debut' => '2026-09-07', 'date_fin' => '2026-09-13', // une semaine, un seul lundi (le 7)
        ]);

        $niveau = Niveau::create(['code' => 'college', 'name_fr' => 'Collège', 'name_en' => 'College', 'ordre' => 1]);
        $matiere = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Mathématiques']);
        $enseignant = Personnel::create([
            'school_id' => $this->school->id, 'nom_complet' => 'SONG ERIC MUNYAM', 'sexe' => 'M', 'statut' => 'actif',
        ]);

        $classeA = Classe::create([
            'school_id' => $this->school->id, 'niveau_id' => $niveau->id, 'nom' => '6ème A',
        ]);
        $classeMatiereA = ClasseMatiere::create([
            'classe_id' => $classeA->id, 'matiere_id' => $matiere->id, 'personnel_id' => $enseignant->id, 'statut' => 'actif',
        ]);
        EmploiDuTemps::create([
            'school_id' => $this->school->id, 'classe_id' => $classeA->id, 'classe_matiere_id' => $classeMatiereA->id,
            'jour' => 1, 'heure_debut' => '08:00', 'heure_fin' => '09:00',
        ]);

        $classeB = Classe::create([
            'school_id' => $this->school->id, 'niveau_id' => $niveau->id, 'nom' => '6ème B',
        ]);
        $classeMatiereB = ClasseMatiere::create([
            'classe_id' => $classeB->id, 'matiere_id' => $matiere->id, 'personnel_id' => $enseignant->id, 'statut' => 'actif',
        ]);
        EmploiDuTemps::create([
            'school_id' => $this->school->id, 'classe_id' => $classeB->id, 'classe_matiere_id' => $classeMatiereB->id,
            'jour' => 1, 'heure_debut' => '08:00', 'heure_fin' => '09:00',
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->postJson("/api/v1/trimestres/{$trimestre->id}/generer-seances")
            ->assertOk()
            ->assertJsonPath('data.classes', 2)
            ->assertJsonPath('data.creees', 2);

        $this->assertSame(1, Seance::where('classe_id', $classeA->id)->count());
        $this->assertSame(1, Seance::where('classe_id', $classeB->id)->count());
    }
}
