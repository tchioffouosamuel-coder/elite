<?php

namespace Tests\Feature;

use App\Models\BibliothequeDocument;
use App\Models\Eleve;
use App\Models\Personnel;
use App\Models\School;
use App\Models\Tuteur;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Bibliothèque numérique : un document déposé par l'admin peut viser
 * plusieurs écoles à la fois (contrairement aux annonces, scopées à une
 * seule), et le personnel/les parents n'y accèdent qu'en lecture, bornés à
 * leur propre école.
 */
class BibliothequeTest extends TestCase
{
    use RefreshDatabase;

    private School $ecoleA;

    private School $ecoleB;

    private School $ecoleC;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        Permission::firstOrCreate(['name' => 'bibliotheque.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'bibliotheque.manage', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'parent', 'guard_name' => 'web']);

        $this->ecoleA = School::create(['name' => 'École A', 'code' => 'EA', 'type' => 'secondaire', 'is_active' => true]);
        $this->ecoleB = School::create(['name' => 'École B', 'code' => 'EB', 'type' => 'secondaire', 'is_active' => true]);
        $this->ecoleC = School::create(['name' => 'École C', 'code' => 'EC', 'type' => 'secondaire', 'is_active' => true]);
    }

    private function admin(School $school): User
    {
        $user = User::create([
            'name' => 'Admin', 'email' => 'admin'.$school->id.'@test.local', 'password' => 'password',
            'school_id' => $school->id, 'is_active' => true,
        ]);
        $user->givePermissionTo(['bibliotheque.view', 'bibliotheque.manage']);

        return $user;
    }

    /** Super admin, rattaché à aucun complexe : voit et peut cibler les 3 écoles de test en mode agrégé (sans en-tête X-School-Id). */
    private function superAdmin(): User
    {
        $user = User::create([
            'name' => 'Super Admin', 'email' => 'super@test.local', 'password' => 'password',
            'school_id' => $this->ecoleA->id, 'is_active' => true,
        ]);
        $user->assignRole('super_admin');
        $user->givePermissionTo(['bibliotheque.view', 'bibliotheque.manage']);

        return $user;
    }

    public function test_un_document_peut_viser_plusieurs_ecoles_a_la_fois(): void
    {
        $admin = $this->superAdmin();
        $fichier = UploadedFile::fake()->create('cours.pdf', 500, 'application/pdf');

        $reponse = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/bibliotheque', [
                'titre' => 'Programme officiel',
                'description' => 'Programme 2026-2027',
                'fichier' => $fichier,
                'school_ids' => [$this->ecoleA->id, $this->ecoleB->id],
            ]);

        $reponse->assertCreated()->assertJsonPath('data.titre', 'Programme officiel');
        $document = BibliothequeDocument::sole();
        $this->assertCount(2, $document->ecoles);
        Storage::disk('public')->assertExists($document->fichier_path);
    }

    public function test_une_ecole_hors_perimetre_ne_peut_pas_etre_ciblee(): void
    {
        $admin = $this->admin($this->ecoleA);
        $fichier = UploadedFile::fake()->create('cours.pdf', 100, 'application/pdf');

        $reponse = $this->actingAs($admin, 'sanctum')
            ->withHeader('X-School-Id', $this->ecoleA->id)
            ->postJson('/api/v1/bibliotheque', [
                'titre' => 'Document',
                'fichier' => $fichier,
                'school_ids' => [$this->ecoleC->id],
            ]);

        $reponse->assertStatus(422);
        $this->assertSame(0, BibliothequeDocument::count());
    }

    public function test_le_personnel_ne_voit_que_les_documents_de_sa_propre_ecole(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/bibliotheque', [
            'titre' => 'Pour A et B',
            'fichier' => UploadedFile::fake()->create('a.pdf', 100, 'application/pdf'),
            'school_ids' => [$this->ecoleA->id, $this->ecoleB->id],
        ])->assertCreated();
        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/bibliotheque', [
            'titre' => 'Pour C seulement',
            'fichier' => UploadedFile::fake()->create('c.pdf', 100, 'application/pdf'),
            'school_ids' => [$this->ecoleC->id],
        ])->assertCreated();

        $personnelB = Personnel::create([
            'school_id' => $this->ecoleB->id, 'nom_complet' => 'Nkomo Alice', 'sexe' => 'F', 'statut' => 'actif',
        ]);
        $userB = User::create([
            'name' => 'Nkomo Alice', 'email' => 'alice@test.local', 'password' => 'password',
            'school_id' => $this->ecoleB->id, 'is_active' => true,
        ]);
        $personnelB->update(['user_id' => $userB->id]);

        $reponse = $this->actingAs($userB, 'sanctum')->getJson('/api/v1/mon-espace/bibliotheque');

        $reponse->assertOk();
        $this->assertCount(1, $reponse->json('data'));
        $this->assertSame('Pour A et B', $reponse->json('data.0.titre'));
    }

    public function test_le_parent_ne_voit_que_les_documents_des_ecoles_de_ses_enfants(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/bibliotheque', [
            'titre' => 'Pour A',
            'fichier' => UploadedFile::fake()->create('a.pdf', 100, 'application/pdf'),
            'school_ids' => [$this->ecoleA->id],
        ])->assertCreated();
        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/bibliotheque', [
            'titre' => 'Pour C',
            'fichier' => UploadedFile::fake()->create('c.pdf', 100, 'application/pdf'),
            'school_ids' => [$this->ecoleC->id],
        ])->assertCreated();

        $tuteur = Tuteur::create(['school_id' => $this->ecoleA->id, 'nom_complet' => 'Mballa Jean', 'telephone' => '699000000']);
        $userParent = User::create([
            'name' => 'Mballa Jean', 'email' => 'mballa@test.local', 'password' => 'password',
            'school_id' => $this->ecoleA->id, 'is_active' => true,
        ]);
        $userParent->assignRole('parent');
        $tuteur->update(['user_id' => $userParent->id]);
        $eleve = Eleve::create([
            'school_id' => $this->ecoleA->id, 'matricule' => 'A1', 'nom_complet' => 'Mballa Junior',
            'sexe' => 'M', 'statut' => 'actif',
        ]);
        $eleve->tuteurs()->attach($tuteur->id, ['is_principal' => true]);

        $reponse = $this->actingAs($userParent, 'sanctum')->getJson('/api/v1/parent/bibliotheque');

        $reponse->assertOk();
        $this->assertCount(1, $reponse->json('data'));
        $this->assertSame('Pour A', $reponse->json('data.0.titre'));
    }

    public function test_supprimer_un_document_retire_aussi_le_fichier(): void
    {
        $admin = $this->admin($this->ecoleA);
        $creation = $this->actingAs($admin, 'sanctum')->withHeader('X-School-Id', $this->ecoleA->id)->postJson('/api/v1/bibliotheque', [
            'titre' => 'À supprimer',
            'fichier' => UploadedFile::fake()->create('x.pdf', 100, 'application/pdf'),
            'school_ids' => [$this->ecoleA->id],
        ]);
        $document = BibliothequeDocument::sole();

        $reponse = $this->actingAs($admin, 'sanctum')
            ->withHeader('X-School-Id', $this->ecoleA->id)
            ->deleteJson("/api/v1/bibliotheque/{$document->id}");

        $reponse->assertOk();
        $this->assertSame(0, BibliothequeDocument::count());
        Storage::disk('public')->assertMissing($document->fichier_path);
    }
}
