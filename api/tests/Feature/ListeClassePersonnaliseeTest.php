<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\Eleve;
use App\Models\School;
use App\Models\User;
use App\Support\CataloguePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Liste personnalisée de classe : génération dans les trois formats avec des
 * colonnes au choix (y compris celles calculées — finance, transport,
 * moyenne — qui doivent se contenter d'un « — » plutôt que d'échouer quand
 * les données sous-jacentes n'existent pas), et gestion des modèles
 * réutilisables.
 */
class ListeClassePersonnaliseeTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private User $admin;
    private Classe $classe;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->school = School::create(['name' => 'Elites Tech', 'code' => 'ET', 'type' => 'secondaire', 'is_active' => true]);

        $this->admin = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $this->admin->assignRole('super_admin');

        $this->classe = Classe::create(['school_id' => $this->school->id, 'nom' => '3ème A']);

        Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $this->classe->id,
            'matricule' => 'M1', 'nom_complet' => 'Kamga Paul', 'sexe' => 'M',
            'date_naissance' => '2010-05-12', 'lieu_naissance' => 'Douala', 'statut' => 'actif',
        ]);
        Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $this->classe->id,
            'matricule' => 'M2', 'nom_complet' => 'Fotso Alice', 'sexe' => 'F',
            'date_naissance' => '2011-02-03', 'lieu_naissance' => 'Yaoundé', 'statut' => 'actif',
        ]);
    }

    private function acteur()
    {
        return $this->actingAs($this->admin, 'sanctum')->withHeader('X-School-Id', $this->school->id);
    }

    public function test_genere_le_pdf_avec_les_colonnes_de_base(): void
    {
        $this->acteur()
            ->get("/api/v1/classes/{$this->classe->id}/liste-personnalisee/pdf?".http_build_query([
                'titre_fr' => 'Liste des élèves', 'titre_en' => 'Student list', 'colonnes' => 'numero,nom_prenom,sexe,date_naissance',
            ]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_genere_le_word_avec_les_colonnes_calculees(): void
    {
        $this->acteur()
            ->get("/api/v1/classes/{$this->classe->id}/liste-personnalisee/word?".http_build_query([
                'titre_fr' => 'Liste des élèves', 'titre_en' => 'Student list',
                'colonnes' => 'numero,nom_prenom,statut_solvabilite,reste_scolarite_a_payer,situation_transport,dette_anterieure,moyenne',
                'moyenne_type' => 'annuelle',
            ]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    }

    public function test_genere_l_excel(): void
    {
        $this->acteur()
            ->get("/api/v1/classes/{$this->classe->id}/liste-personnalisee/excel?".http_build_query([
                'titre_fr' => 'Liste des élèves', 'titre_en' => 'Student list', 'colonnes' => 'numero,nom_prenom',
            ]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_refuse_une_colonne_inconnue(): void
    {
        $this->acteur()
            ->get("/api/v1/classes/{$this->classe->id}/liste-personnalisee/pdf?".http_build_query([
                'titre_fr' => 'Liste', 'titre_en' => 'List', 'colonnes' => 'numero,inexistante',
            ]))
            ->assertStatus(422);
    }

    public function test_exige_la_periode_exacte_pour_une_moyenne_de_trimestre(): void
    {
        $this->acteur()
            ->get("/api/v1/classes/{$this->classe->id}/liste-personnalisee/pdf?".http_build_query([
                'titre_fr' => 'Liste', 'titre_en' => 'List', 'colonnes' => 'numero,moyenne', 'moyenne_type' => 'trimestre',
            ]))
            ->assertStatus(422);
    }

    public function test_cree_puis_supprime_un_modele(): void
    {
        $creation = $this->acteur()
            ->postJson('/api/v1/classes/liste-personnalisee/modeles', [
                'titre_fr' => 'Admis en classe supérieure',
                'titre_en' => 'Promoted students',
                'colonnes' => ['numero', 'nom_prenom', 'moyenne'],
                'moyenne_type' => 'annuelle',
            ])
            ->assertCreated();

        $id = $creation->json('data.id');

        $this->acteur()->getJson('/api/v1/classes/liste-personnalisee/modeles')
            ->assertOk()
            ->assertJsonFragment(['titre_fr' => 'Admis en classe supérieure']);

        $this->acteur()->deleteJson("/api/v1/classes/liste-personnalisee/modeles/{$id}")->assertOk();

        $this->acteur()->getJson('/api/v1/classes/liste-personnalisee/modeles')
            ->assertOk()
            ->assertJsonMissing(['titre_fr' => 'Admis en classe supérieure']);
    }
}
