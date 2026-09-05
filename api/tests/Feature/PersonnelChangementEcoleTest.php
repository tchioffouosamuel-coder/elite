<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\FonctionReferentiel;
use App\Models\Niveau;
use App\Models\School;
use App\Models\User;
use App\Services\PersonnelService;
use App\Support\CataloguePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Le formulaire de modification d'un agent porte désormais le champ école :
 * une mutation entre écoles du complexe se fait sur la fiche, sans repasser
 * par une suppression/recréation qui perdrait le dossier.
 */
class PersonnelChangementEcoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_agent_change_d_ecole_avec_son_compte_et_perd_ses_responsabilites(): void
    {
        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $depart = School::create(['name' => 'Elites Secondaire', 'code' => 'ES', 'type' => 'secondaire', 'is_active' => true]);
        $arrivee = School::create(['name' => 'Elites Primaire', 'code' => 'EP', 'type' => 'primaire', 'is_active' => true]);

        $superAdmin = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $depart->id, 'is_active' => true,
        ]);
        $superAdmin->assignRole('super_admin');

        $fonctionDepart = FonctionReferentiel::create(['school_id' => $depart->id, 'label_fr' => 'Enseignant']);
        $fonctionArrivee = FonctionReferentiel::create(['school_id' => $arrivee->id, 'label_fr' => 'Enseignant']);

        $agent = app(PersonnelService::class)->create($depart->id, [
            'nom_complet' => 'AGENT MUTE', 'fonction_id' => $fonctionDepart->id, 'statut' => 'actif',
        ]);

        $niveau = Niveau::create(['code' => 'college', 'name_fr' => 'Collège', 'name_en' => 'Secondary']);
        AnneeScolaire::create([
            'school_id' => $depart->id, 'libelle' => '2026-2027',
            'date_debut' => '2026-09-01', 'date_fin' => '2027-07-15', 'is_active' => true,
        ]);
        $classe = Classe::create([
            'school_id' => $depart->id, 'niveau_id' => $niveau->id, 'nom' => '6e A',
            'professeur_principal_id' => $agent->id,
        ]);

        $this->actingAs($superAdmin, 'sanctum')
            ->withHeader('X-School-Id', (string) $depart->id)
            ->putJson("/api/v1/personnels/{$agent->id}", [
                'nom_complet' => 'AGENT MUTE',
                'school_id' => $arrivee->id,
                'fonction_id' => $fonctionArrivee->id,
            ])
            ->assertOk();

        $this->assertDatabaseHas('personnels', ['id' => $agent->id, 'school_id' => $arrivee->id, 'fonction_id' => $fonctionArrivee->id]);
        $this->assertDatabaseHas('users', ['id' => $agent->refresh()->user_id, 'school_id' => $arrivee->id]);
        $this->assertDatabaseHas('classes', ['id' => $classe->id, 'professeur_principal_id' => null]);
    }

    public function test_une_ecole_hors_perimetre_est_refusee(): void
    {
        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $ecole = School::create(['name' => 'Elites Secondaire', 'code' => 'ES', 'type' => 'secondaire', 'is_active' => true]);
        $etrangere = School::create(['name' => 'Autre complexe', 'code' => 'AC', 'type' => 'primaire', 'is_active' => false]);

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.local', 'password' => 'password',
            'school_id' => $ecole->id, 'is_active' => true,
        ]);
        $admin->assignRole('super_admin');

        $fonction = FonctionReferentiel::create(['school_id' => $ecole->id, 'label_fr' => 'Enseignant']);
        $agent = app(PersonnelService::class)->create($ecole->id, [
            'nom_complet' => 'AGENT', 'fonction_id' => $fonction->id, 'statut' => 'actif',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->withHeader('X-School-Id', (string) $ecole->id)
            ->putJson("/api/v1/personnels/{$agent->id}", [
                'nom_complet' => 'AGENT',
                'school_id' => $etrangere->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('personnels', ['id' => $agent->id, 'school_id' => $ecole->id]);
    }
}
