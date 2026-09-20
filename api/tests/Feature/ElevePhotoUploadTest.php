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
