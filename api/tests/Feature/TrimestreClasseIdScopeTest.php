<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Niveau;
use App\Models\School;
use App\Models\Trimestre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Régression : un super administrateur en mode agrégé (plusieurs écoles,
 * aucun `X-School-Id`) reçoit par défaut les trimestres de son école de
 * rattachement (`app('tenant.school_id')`), pas forcément celle de la classe
 * qu'il est en train de noter. `GET /trimestres?classe_id=...` doit alors
 * répondre avec les trimestres de l'école QUI PORTE cette classe — sinon le
 * `trimestre_id` renvoyé ne correspond à rien côté `NotePrimaireController`,
 * qui résout lui correctement par `classe->school_id` (422 « Aucun
 * trimestre actif pour cet établissement » observé en conditions réelles).
 */
class TrimestreClasseIdScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_classe_id_borne_les_trimestres_a_l_ecole_de_cette_classe(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $ecoleParDefaut = School::create(['name' => 'École A', 'code' => 'EA', 'type' => 'secondaire', 'is_active' => true]);
        $ecoleDeLaClasse = School::create(['name' => 'École B', 'code' => 'EB', 'type' => 'primaire', 'is_active' => true]);
        $niveau = Niveau::create(['code' => 'college', 'name_fr' => 'Collège', 'name_en' => 'Secondary']);

        $anneeA = AnneeScolaire::create([
            'school_id' => $ecoleParDefaut->id, 'libelle' => '2026-2027',
            'date_debut' => '2026-09-01', 'date_fin' => '2027-07-15', 'is_active' => true,
        ]);
        $trimestreA = Trimestre::create([
            'annee_scolaire_id' => $anneeA->id, 'libelle' => 'Trimestre 1 (École A)',
            'ordre' => 1, 'date_debut' => '2026-09-01', 'date_fin' => '2026-12-19', 'is_active' => true,
        ]);

        $anneeB = AnneeScolaire::create([
            'school_id' => $ecoleDeLaClasse->id, 'libelle' => '2026-2027',
            'date_debut' => '2026-09-01', 'date_fin' => '2027-07-15', 'is_active' => true,
        ]);
        $trimestreB = Trimestre::create([
            'annee_scolaire_id' => $anneeB->id, 'libelle' => 'Trimestre 1 (École B)',
            'ordre' => 1, 'date_debut' => '2026-09-01', 'date_fin' => '2026-12-19', 'is_active' => true,
        ]);

        $classe = Classe::create([
            'school_id' => $ecoleDeLaClasse->id, 'niveau_id' => $niveau->id, 'nom' => 'SIL-A',
        ]);

        // Compte super admin rattaché à l'école A, mais accédant aux deux
        // écoles (pas de complexe configuré ici : `ecolesAccessibles()`
        // renvoie alors tout établissement actif).
        $admin = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $ecoleParDefaut->id, 'is_active' => true,
        ]);
        $admin->assignRole('super_admin');

        // Sans classe_id ni X-School-Id : le repli historique reste l'école
        // de rattachement du compte.
        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/trimestres')
            ->assertOk()
            ->assertJsonFragment(['id' => $trimestreA->id])
            ->assertJsonMissing(['id' => $trimestreB->id]);

        // Avec classe_id : les trimestres de l'école qui porte CETTE classe,
        // pas celle par défaut du compte.
        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/trimestres?classe_id={$classe->id}")
            ->assertOk()
            ->assertJsonFragment(['id' => $trimestreB->id])
            ->assertJsonMissing(['id' => $trimestreA->id]);
    }
}
