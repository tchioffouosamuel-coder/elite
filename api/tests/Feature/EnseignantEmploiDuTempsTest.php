<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\EmploiDuTemps;
use App\Models\FonctionReferentiel;
use App\Models\Matiere;
use App\Models\Personnel;
use App\Models\School;
use App\Models\User;
use App\Support\CataloguePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * La semaine de l'enseignant : ses créneaux à lui, toutes classes confondues,
 * et rien des classes où il n'intervient pas — y compris par un identifiant
 * deviné, que la liste des classes ne lui propose pourtant pas.
 */
class EnseignantEmploiDuTempsTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $prof;

    private Classe $maClasse;

    private Classe $classeVoisine;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->school = School::create([
            'name' => 'Elites College', 'code' => 'EBTC', 'type' => 'secondaire', 'is_active' => true,
        ]);
        AnneeScolaire::create([
            'school_id' => $this->school->id, 'libelle' => '2025-2026',
            'date_debut' => '2025-09-01', 'date_fin' => '2026-07-31', 'is_active' => true,
        ]);

        $fonction = FonctionReferentiel::firstOrCreate(
            ['school_id' => $this->school->id, 'label_fr' => 'Enseignant'],
        );
        $fonction->synchroniserPermissions(['classes.view', 'emploi_du_temps.view', 'dashboard.view']);

        $this->prof = User::create([
            'name' => 'Prof', 'email' => 'prof@test.local',
            'password' => 'password', 'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $personnel = Personnel::create([
            'school_id' => $this->school->id, 'user_id' => $this->prof->id, 'fonction_id' => $fonction->id,
            'nom_complet' => 'Prof', 'sexe' => 'M', 'statut' => 'actif',
        ]);
        $this->prof = $this->prof->fresh();

        $this->maClasse = Classe::create(['school_id' => $this->school->id, 'nom' => '6ème A']);
        $this->classeVoisine = Classe::create(['school_id' => $this->school->id, 'nom' => '6ème B']);

        $maths = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Mathématiques']);
        $anglais = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Anglais']);

        $mienne = ClasseMatiere::create([
            'classe_id' => $this->maClasse->id, 'matiere_id' => $maths->id,
            'personnel_id' => $personnel->id, 'coefficient' => 1, 'statut' => 'actif',
        ]);
        // Même classe, mais la matière d'un collègue : hors de SA semaine.
        $duCollegue = ClasseMatiere::create([
            'classe_id' => $this->maClasse->id, 'matiere_id' => $anglais->id,
            'personnel_id' => null, 'coefficient' => 1, 'statut' => 'actif',
        ]);

        EmploiDuTemps::create([
            'school_id' => $this->school->id, 'classe_id' => $this->maClasse->id,
            'classe_matiere_id' => $mienne->id, 'type' => 'cours',
            'jour' => 2, 'heure_debut' => '10:00', 'heure_fin' => '11:00',
        ]);
        EmploiDuTemps::create([
            'school_id' => $this->school->id, 'classe_id' => $this->maClasse->id,
            'classe_matiere_id' => $mienne->id, 'type' => 'cours',
            'jour' => 1, 'heure_debut' => '08:00', 'heure_fin' => '09:00',
        ]);
        EmploiDuTemps::create([
            'school_id' => $this->school->id, 'classe_id' => $this->maClasse->id,
            'classe_matiere_id' => $duCollegue->id, 'type' => 'cours',
            'jour' => 1, 'heure_debut' => '09:00', 'heure_fin' => '10:00',
        ]);
    }

    public function test_ma_semaine_ne_porte_que_mes_creneaux_tries_dans_l_ordre(): void
    {
        $reponse = $this->actingAs($this->prof, 'sanctum')
            ->getJson('/api/v1/enseignant/mon-emploi-du-temps')
            ->assertOk();

        $creneaux = $reponse->json('data');

        $this->assertCount(2, $creneaux, 'Le cours du collègue est remonté.');
        $this->assertSame([1, 2], array_column($creneaux, 'jour'));
        $this->assertSame('08:00', $creneaux[0]['heure_debut']);
        $this->assertSame('Mathématiques', $creneaux[0]['matiere']);
        $this->assertSame('6ème A', $creneaux[0]['classe']);
    }

    /** Un compte sans fiche personnel n'a pas de semaine, mais pas d'erreur non plus. */
    public function test_un_compte_sans_fiche_personnel_recoit_une_semaine_vide(): void
    {
        $sansFiche = User::create([
            'name' => 'Sans fiche', 'email' => 'sansfiche@test.local',
            'password' => 'password', 'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $sansFiche->assignRole('super_admin');

        $this->actingAs($sansFiche, 'sanctum')
            ->getJson('/api/v1/enseignant/mon-emploi-du-temps')
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_l_emploi_du_temps_d_une_classe_voisine_est_refuse(): void
    {
        $this->actingAs($this->prof, 'sanctum')
            ->getJson("/api/v1/classes/{$this->maClasse->id}/emploi-du-temps")
            ->assertOk();

        $this->actingAs($this->prof, 'sanctum')
            ->getJson("/api/v1/classes/{$this->classeVoisine->id}/emploi-du-temps")
            ->assertForbidden();
    }

    /** La liste des classes de l'écran emploi du temps est bornée de la même façon. */
    public function test_la_liste_des_classes_ne_montre_que_les_miennes(): void
    {
        $reponse = $this->actingAs($this->prof, 'sanctum')
            ->getJson('/api/v1/emploi-du-temps/classes')
            ->assertOk();

        $this->assertSame(
            [$this->maClasse->id],
            array_column($reponse->json('data'), 'id'),
        );
    }
}
