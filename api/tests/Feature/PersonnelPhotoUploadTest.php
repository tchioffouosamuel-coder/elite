<?php

namespace Tests\Feature;

use App\Models\Personnel;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Point 21 de la punch-list Elites-tech : chaque agent téléverse sa propre photo de profil. */
class PersonnelPhotoUploadTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'personnel.update', 'guard_name' => 'web']);

        $this->school = School::create([
            'name' => 'Elites Tech',
            'code' => 'ET',
            'type' => 'secondaire',
            'is_active' => true,
        ]);
    }

    public function test_un_agent_peut_uploader_sa_propre_photo(): void
    {
        Storage::fake('public');

        [$personnel, $user] = $this->agent('Jean Teacher');

        $this->actingAs($user, 'sanctum')
            ->post("/api/v1/personnels/{$personnel->id}/photo", [
                'photo' => UploadedFile::fake()->image('portrait.png', 300, 500),
            ])
            ->assertOk()
            ->assertJsonPath('data.photo_url', fn ($url) => is_string($url) && str_contains($url, 'personnels/photos/'.$personnel->id.'.jpg'));

        Storage::disk('public')->assertExists('personnels/photos/'.$personnel->id.'.jpg');
    }

    public function test_un_agent_ne_peut_pas_changer_la_photo_dun_autre_sans_le_privilege(): void
    {
        Storage::fake('public');

        [$cible] = $this->agent('Cible');
        [, $autre] = $this->agent('Autre Agent');

        $this->actingAs($autre, 'sanctum')
            ->post("/api/v1/personnels/{$cible->id}/photo", [
                'photo' => UploadedFile::fake()->image('portrait.png', 300, 500),
            ])
            ->assertForbidden();
    }

    public function test_un_titulaire_de_personnel_update_peut_changer_la_photo_dun_autre_agent(): void
    {
        Storage::fake('public');

        [$cible] = $this->agent('Cible RH');
        [, $rh] = $this->agent('RH');
        $rh->givePermissionTo('personnel.update');

        $this->actingAs($rh, 'sanctum')
            ->post("/api/v1/personnels/{$cible->id}/photo", [
                'photo' => UploadedFile::fake()->image('portrait.png', 300, 500),
            ])
            ->assertOk();
    }

    /** @return array{0: Personnel, 1: User} */
    private function agent(string $nom): array
    {
        $personnel = Personnel::create([
            'school_id' => $this->school->id,
            'nom_complet' => $nom,
            'statut' => 'actif',
        ]);

        $user = User::create([
            'name' => $nom,
            'email' => strtolower(str_replace(' ', '.', $nom)).'@test.local',
            'password' => 'password',
            'school_id' => $this->school->id,
            'is_active' => true,
        ]);
        $personnel->update(['user_id' => $user->id]);

        return [$personnel, $user];
    }
}
