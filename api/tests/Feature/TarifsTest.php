<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\DossierScolarite;
use App\Models\Eleve;
use App\Models\GrilleFrais;
use App\Models\School;
use App\Models\User;
use App\Services\ScolariteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TarifsTest extends TestCase
{
    use RefreshDatabase;

    public function test_modifier_le_tarif_d_une_classe_met_a_jour_les_dossiers_deja_ouverts(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $school = School::create(['name' => 'Elites Tech', 'code' => 'ET', 'type' => 'secondaire', 'is_active' => true]);
        $annee = AnneeScolaire::create([
            'school_id' => $school->id,
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-07-31',
            'is_active' => true,
        ]);
        $classe = Classe::create(['school_id' => $school->id, 'nom' => '6e A']);

        GrilleFrais::create([
            'school_id' => $school->id,
            'annee_scolaire_id' => $annee->id,
            'classe_id' => $classe->id,
            'montant' => 100000,
        ]);

        $eleve = Eleve::create([
            'school_id' => $school->id,
            'classe_id' => $classe->id,
            'nom_complet' => 'Alice Ngono',
            'sexe' => 'F',
            'statut' => 'actif',
        ]);
        $dossier = app(ScolariteService::class)->dossier($eleve, $annee);
        $this->assertSame(100000, $dossier->montant_scolarite);

        $user = User::create([
            'name' => 'Root',
            'email' => 'root@test.local',
            'password' => 'password',
            'school_id' => $school->id,
            'is_active' => true,
        ]);
        $user->assignRole('super_admin');

        $reponse = $this->actingAs($user, 'sanctum')
            ->withHeader('X-School-Id', $school->id)
            ->postJson('/api/v1/tarifs', ['classe_id' => $classe->id, 'montant' => 150000]);

        $reponse->assertOk()->assertJsonPath('data.dossiers_mis_a_jour', 1);

        $this->assertSame(150000, $dossier->fresh()->montant_scolarite);
    }

    public function test_un_frais_annexe_peut_etre_circonscrit_a_des_classes(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $school = School::create(['name' => 'Elites Tech', 'code' => 'ET', 'type' => 'secondaire', 'is_active' => true]);
        $annee = AnneeScolaire::create([
            'school_id' => $school->id,
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-07-31',
            'is_active' => true,
        ]);
        $classeA = Classe::create(['school_id' => $school->id, 'nom' => '6e A']);
        $classeB = Classe::create(['school_id' => $school->id, 'nom' => '6e B']);

        $user = User::create([
            'name' => 'Root',
            'email' => 'root@test.local',
            'password' => 'password',
            'school_id' => $school->id,
            'is_active' => true,
        ]);
        $user->assignRole('super_admin');

        $creation = $this->actingAs($user, 'sanctum')
            ->withHeader('X-School-Id', $school->id)
            ->postJson('/api/v1/tarifs/frais-annexes', [
                'libelle' => 'Tenue 6e A',
                'montant' => 15000,
                'obligatoire' => true,
                'classe_ids' => [$classeA->id],
            ]);

        $creation->assertCreated()->assertJsonCount(1, 'data.classes');

        $eleveA = Eleve::create([
            'school_id' => $school->id,
            'classe_id' => $classeA->id,
            'nom_complet' => 'Alice Ngono',
            'sexe' => 'F',
            'statut' => 'actif',
        ]);
        $eleveB = Eleve::create([
            'school_id' => $school->id,
            'classe_id' => $classeB->id,
            'nom_complet' => 'Bruno Essomba',
            'sexe' => 'M',
            'statut' => 'actif',
        ]);

        $service = app(ScolariteService::class);
        $dossierA = $service->dossier($eleveA, $annee);
        $dossierB = $service->dossier($eleveB, $annee);

        $this->assertCount(1, $dossierA->fraisAnnexes);
        $this->assertCount(0, $dossierB->fraisAnnexes);
    }

    public function test_modifier_un_frais_annexe_met_a_jour_les_dossiers_non_regles(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $school = School::create(['name' => 'Elites Tech', 'code' => 'ET', 'type' => 'secondaire', 'is_active' => true]);
        $annee = AnneeScolaire::create([
            'school_id' => $school->id,
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-07-31',
            'is_active' => true,
        ]);
        $classe = Classe::create(['school_id' => $school->id, 'nom' => '6e A']);
        $user = User::create([
            'name' => 'Root',
            'email' => 'root@test.local',
            'password' => 'password',
            'school_id' => $school->id,
            'is_active' => true,
        ]);
        $user->assignRole('super_admin');

        $creation = $this->actingAs($user, 'sanctum')
            ->withHeader('X-School-Id', $school->id)
            ->postJson('/api/v1/tarifs/frais-annexes', [
                'libelle' => 'Tenue de sport',
                'montant' => 15000,
                'obligatoire' => true,
            ]);
        $fraisId = $creation->json('data.id');

        $eleve = Eleve::create([
            'school_id' => $school->id,
            'classe_id' => $classe->id,
            'nom_complet' => 'Alice Ngono',
            'sexe' => 'F',
            'statut' => 'actif',
        ]);
        $dossier = app(ScolariteService::class)->dossier($eleve, $annee);

        $this->actingAs($user, 'sanctum')
            ->withHeader('X-School-Id', $school->id)
            ->putJson("/api/v1/tarifs/frais-annexes/{$fraisId}", [
                'libelle' => 'Nouvelle tenue',
                'montant' => 20000,
            ])
            ->assertOk();

        $ligne = $dossier->fresh()->fraisAnnexes->first();
        $this->assertSame('Nouvelle tenue', $ligne->libelle);
        $this->assertSame(20000, $ligne->montant);
    }

    public function test_rendre_un_frais_obligatoire_facultatif_le_retire_des_dossiers_non_regles(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $school = School::create(['name' => 'Elites Tech', 'code' => 'ET', 'type' => 'secondaire', 'is_active' => true]);
        $annee = AnneeScolaire::create([
            'school_id' => $school->id,
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-07-31',
            'is_active' => true,
        ]);
        $classe = Classe::create(['school_id' => $school->id, 'nom' => '6e A']);

        $user = User::create([
            'name' => 'Root',
            'email' => 'root@test.local',
            'password' => 'password',
            'school_id' => $school->id,
            'is_active' => true,
        ]);
        $user->assignRole('super_admin');

        $creation = $this->actingAs($user, 'sanctum')
            ->withHeader('X-School-Id', $school->id)
            ->postJson('/api/v1/tarifs/frais-annexes', [
                'libelle' => 'Tenue de sport',
                'montant' => 15000,
                'obligatoire' => true,
            ]);
        $fraisId = $creation->json('data.id');

        $eleve = Eleve::create([
            'school_id' => $school->id,
            'classe_id' => $classe->id,
            'nom_complet' => 'Alice Ngono',
            'sexe' => 'F',
            'statut' => 'actif',
        ]);
        $dossier = app(ScolariteService::class)->dossier($eleve, $annee);
        $this->assertCount(1, $dossier->fraisAnnexes);

        $reponse = $this->actingAs($user, 'sanctum')
            ->withHeader('X-School-Id', $school->id)
            ->putJson("/api/v1/tarifs/frais-annexes/{$fraisId}", ['obligatoire' => false]);

        $reponse->assertOk();
        $this->assertCount(0, $dossier->fresh()->fraisAnnexes);

        // Rendu à nouveau obligatoire : réattaché au dossier déjà ouvert.
        $this->actingAs($user, 'sanctum')
            ->withHeader('X-School-Id', $school->id)
            ->putJson("/api/v1/tarifs/frais-annexes/{$fraisId}", ['obligatoire' => true])
            ->assertOk();

        $this->assertCount(1, $dossier->fresh()->fraisAnnexes);
    }

    public function test_desactiver_un_frais_annexe_le_retire_du_dossier_mais_epargne_ce_qui_est_deja_regle(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $school = School::create(['name' => 'Elites Tech', 'code' => 'ET', 'type' => 'secondaire', 'is_active' => true]);
        $annee = AnneeScolaire::create([
            'school_id' => $school->id,
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-07-31',
            'is_active' => true,
        ]);
        $classe = Classe::create(['school_id' => $school->id, 'nom' => '6e A']);

        $user = User::create([
            'name' => 'Root',
            'email' => 'root@test.local',
            'password' => 'password',
            'school_id' => $school->id,
            'is_active' => true,
        ]);
        $user->assignRole('super_admin');

        $creation = $this->actingAs($user, 'sanctum')
            ->withHeader('X-School-Id', $school->id)
            ->postJson('/api/v1/tarifs/frais-annexes', [
                'libelle' => 'Tenue de sport',
                'montant' => 15000,
                'obligatoire' => true,
            ]);
        $fraisId = $creation->json('data.id');

        $eleveNonPaye = Eleve::create([
            'school_id' => $school->id,
            'classe_id' => $classe->id,
            'nom_complet' => 'Alice Ngono',
            'sexe' => 'F',
            'statut' => 'actif',
        ]);
        $eleveDejaPaye = Eleve::create([
            'school_id' => $school->id,
            'classe_id' => $classe->id,
            'nom_complet' => 'Bruno Essomba',
            'sexe' => 'M',
            'statut' => 'actif',
        ]);

        $service = app(ScolariteService::class);
        $dossierNonPaye = $service->dossier($eleveNonPaye, $annee);
        $dossierDejaPaye = $service->dossier($eleveDejaPaye, $annee);

        $ligneFrais = $dossierDejaPaye->fraisAnnexes->firstWhere('frais_annexe_id', $fraisId);
        $service->encaisser($dossierDejaPaye, [
            'montant' => 15000,
            'lignes' => [[
                'affectation' => 'frais_annexe',
                'dossier_frais_annexe_id' => $ligneFrais->id,
                'libelle' => $ligneFrais->libelle,
                'montant' => 15000,
            ]],
        ]);

        $this->actingAs($user, 'sanctum')
            ->withHeader('X-School-Id', $school->id)
            ->deleteJson("/api/v1/tarifs/frais-annexes/{$fraisId}")
            ->assertOk();

        $this->assertCount(0, $dossierNonPaye->fresh()->fraisAnnexes);
        $this->assertCount(1, $dossierDejaPaye->fresh()->fraisAnnexes);
    }
}
