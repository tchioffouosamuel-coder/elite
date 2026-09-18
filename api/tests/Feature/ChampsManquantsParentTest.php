<?php

namespace Tests\Feature;

use App\Models\Eleve;
use App\Models\School;
use App\Models\Tuteur;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ChampsManquantsParentTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private Eleve $eleve;

    private Tuteur $tuteur;

    private User $userParent;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'parent', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'eleves.view', 'guard_name' => 'web']);

        $this->school = School::create(['name' => 'Elites Tech', 'code' => 'ET', 'type' => 'secondaire', 'is_active' => true]);
        $this->eleve = Eleve::create([
            'school_id' => $this->school->id, 'matricule' => '26SEC1', 'nom_complet' => 'Fomesso Mark',
            'sexe' => null, 'statut' => 'actif',
        ]);
        $this->tuteur = Tuteur::create(['school_id' => $this->school->id, 'nom_complet' => 'Fomesso Paul']);
        $this->eleve->tuteurs()->attach($this->tuteur->id, ['is_principal' => true]);

        $this->userParent = User::create([
            'name' => 'Fomesso Paul', 'email' => 'fomesso@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $this->userParent->assignRole('parent');
        $this->userParent->givePermissionTo('eleves.view');
        $this->tuteur->update(['user_id' => $this->userParent->id]);
    }

    public function test_champs_manquants_liste_les_champs_vides_de_lenfant_et_du_tuteur(): void
    {
        $reponse = $this->actingAs($this->userParent, 'sanctum')
            ->getJson('/api/v1/parent/champs-manquants');

        $reponse->assertOk()
            ->assertJsonPath('data.total', fn ($total) => $total > 0)
            ->assertJsonPath('data.enfants.0.id', $this->eleve->id)
            ->assertJsonFragment(['champs' => ['telephone', 'email', 'profession', 'lieu_service', 'adresse']]);

        $this->assertContains('sexe', $reponse->json('data.enfants.0.champs'));
    }

    public function test_completer_lenfant_remplit_un_champ_vide_directement(): void
    {
        $reponse = $this->actingAs($this->userParent, 'sanctum')
            ->postJson("/api/v1/parent/enfants/{$this->eleve->id}/completer", ['sexe' => 'M', 'adresse' => 'Rue neuve']);

        $reponse->assertOk();
        $this->eleve->refresh();
        $this->assertSame('M', $this->eleve->sexe);
        $this->assertSame('Rue neuve', $this->eleve->adresse);
    }

    /** Un champ déjà renseigné ne doit jamais être écrasé par cette voie directe — seule soumettreModification(), validée par l'école, le peut. */
    public function test_completer_ignore_un_champ_deja_renseigne(): void
    {
        $this->eleve->update(['adresse' => 'Adresse originale']);

        $reponse = $this->actingAs($this->userParent, 'sanctum')
            ->postJson("/api/v1/parent/enfants/{$this->eleve->id}/completer", ['adresse' => 'Adresse usurpée']);

        $reponse->assertStatus(422);
        $this->assertSame('Adresse originale', $this->eleve->fresh()->adresse);
    }

    public function test_completer_le_tuteur_cree_un_numero_de_telephone_principal(): void
    {
        $reponse = $this->actingAs($this->userParent, 'sanctum')
            ->postJson('/api/v1/parent/tuteur/completer', ['telephone' => '699000001', 'profession' => 'Enseignant']);

        $reponse->assertOk();
        $this->tuteur->refresh();
        $this->assertSame('699000001', $this->tuteur->telephone);
        $this->assertSame('Enseignant', $this->tuteur->profession);
        $this->assertTrue($this->tuteur->telephones()->where('is_principal', true)->exists());
    }
}
