<?php

namespace Tests\Feature;

use App\Models\Eleve;
use App\Models\School;
use App\Models\Tuteur;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ElevePhotoUploadTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'eleves.manage', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'parent', 'guard_name' => 'web']);

        $this->school = School::create([
            'name' => 'Elites Tech',
            'code' => 'ET',
            'type' => 'secondaire',
            'is_active' => true,
        ]);
    }

    public function test_admin_peut_uploader_une_photo_eleve(): void
    {
        Storage::fake('public');

        $eleve = $this->eleve('26SEC1', 'Admin Photo');
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.local',
            'password' => 'password',
            'school_id' => $this->school->id,
            'is_active' => true,
        ]);
        $admin->givePermissionTo('eleves.manage');

        $this->actingAs($admin, 'sanctum')
            ->post("/api/v1/eleves/{$eleve->id}/photo", [
                'photo' => UploadedFile::fake()->image('portrait.png', 300, 500),
            ])
            ->assertOk()
            ->assertJsonPath('data.photo_url', fn ($url) => is_string($url) && str_contains($url, 'eleves/photos/'.$eleve->id.'.jpg'));

        $this->assertPhotoCarreeStockee($eleve->id);
    }

    public function test_parent_peut_completer_la_photo_de_son_enfant(): void
    {
        Storage::fake('public');

        $eleve = $this->eleve('26SEC2', 'Parent Photo');
        $parent = $this->parentPour($eleve);

        $this->actingAs($parent, 'sanctum')
            ->post("/api/v1/parent/enfants/{$eleve->id}/completer/photo", [
                'photo' => UploadedFile::fake()->image('portrait.jpg', 500, 300),
            ])
            ->assertOk()
            ->assertJsonPath('data.photo_url', fn ($url) => is_string($url) && str_contains($url, 'eleves/photos/'.$eleve->id.'.jpg'));

        $this->assertPhotoCarreeStockee($eleve->id);
    }

    public function test_parent_propose_un_changement_de_photo_deja_existante_soumis_a_validation(): void
    {
        Storage::fake('public');

        $eleve = $this->eleve('26SEC3', 'Parent Rephoto');
        Storage::disk('public')->put('eleves/photos/'.$eleve->id.'.jpg', 'ancienne-photo');
        $eleve->update(['photo_path' => 'eleves/photos/'.$eleve->id.'.jpg']);
        $parent = $this->parentPour($eleve);

        $this->actingAs($parent, 'sanctum')
            ->post("/api/v1/parent/enfants/{$eleve->id}/modification", [
                'photo' => UploadedFile::fake()->image('nouvelle.jpg', 400, 400),
            ])
            ->assertCreated();

        // La photo officielle n'a pas bougé tant que ce n'est pas validé…
        $this->assertSame('ancienne-photo', Storage::disk('public')->get('eleves/photos/'.$eleve->id.'.jpg'));
        Storage::disk('public')->assertExists('eleves/photos_pending/'.$eleve->id.'.jpg');

        $modification = \App\Models\ModificationEleve::where('eleve_id', $eleve->id)->latest()->firstOrFail();

        app(\App\Services\ModificationEleveService::class)->valider($modification);

        $this->assertPhotoCarreeStockee($eleve->id);
        Storage::disk('public')->assertMissing('eleves/photos_pending/'.$eleve->id.'.jpg');
    }

    public function test_le_rejet_dune_proposition_de_photo_nettoie_le_fichier_en_attente(): void
    {
        Storage::fake('public');

        $eleve = $this->eleve('26SEC4', 'Parent Rephoto Rejet');
        Storage::disk('public')->put('eleves/photos/'.$eleve->id.'.jpg', 'ancienne-photo');
        $eleve->update(['photo_path' => 'eleves/photos/'.$eleve->id.'.jpg']);
        $parent = $this->parentPour($eleve);

        $this->actingAs($parent, 'sanctum')
            ->post("/api/v1/parent/enfants/{$eleve->id}/modification", [
                'photo' => UploadedFile::fake()->image('nouvelle.jpg', 400, 400),
            ])
            ->assertCreated();

        $modification = \App\Models\ModificationEleve::where('eleve_id', $eleve->id)->latest()->firstOrFail();

        app(\App\Services\ModificationEleveService::class)->rejeter($modification, 'Photo floue.');

        Storage::disk('public')->assertMissing('eleves/photos_pending/'.$eleve->id.'.jpg');
        $this->assertSame('ancienne-photo', Storage::disk('public')->get('eleves/photos/'.$eleve->id.'.jpg'));
    }

    private function eleve(string $matricule, string $nom): Eleve
    {
        return Eleve::create([
            'school_id' => $this->school->id,
            'matricule' => $matricule,
            'nom_complet' => $nom,
            'sexe' => 'M',
            'statut' => 'actif',
        ]);
    }

    private function parentPour(Eleve $eleve): User
    {
        $tuteur = Tuteur::create([
            'school_id' => $this->school->id,
            'nom_complet' => 'Parent '.$eleve->id,
        ]);
        $eleve->tuteurs()->attach($tuteur->id, ['is_principal' => true]);

        $user = User::create([
            'name' => $tuteur->nom_complet,
            'email' => 'parent'.$eleve->id.'@test.local',
            'password' => 'password',
            'school_id' => $this->school->id,
            'is_active' => true,
        ]);
        $user->assignRole('parent');
        $tuteur->update(['user_id' => $user->id]);

        return $user;
    }

    private function assertPhotoCarreeStockee(int $eleveId): void
    {
        $path = 'eleves/photos/'.$eleveId.'.jpg';

        Storage::disk('public')->assertExists($path);

        $image = Storage::disk('public')->get($path);
        $dimensions = getimagesizefromstring($image);

        $this->assertNotFalse($dimensions);
        $this->assertSame([600, 600, IMAGETYPE_JPEG], [$dimensions[0], $dimensions[1], $dimensions[2]]);
    }
}
