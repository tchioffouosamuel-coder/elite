<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\EmploiDuTemps;
use App\Models\FonctionReferentiel;
use App\Models\Matiere;
use App\Models\NotificationInterne;
use App\Models\Personnel;
use App\Models\ProgressionItem;
use App\Models\School;
use App\Models\Seance;
use App\Models\Trimestre;
use App\Models\User;
use App\Support\CataloguePermissions;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * La direction valide des leçons au même titre que l'enseignant : chaque
 * validation garde son auteur, et la direction est prévenue à chaque fois.
 */
class MaJourneeValidationLeconTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private ClasseMatiere $classeMatiere;

    private User $admin;

    private User $directeur;

    private User $prof;

    /** @var array<int, ProgressionItem> */
    private array $lecons = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin_ecole', 'guard_name' => 'web']);

        $this->school = School::create([
            'name' => 'Elites Test', 'code' => 'ET3', 'type' => 'secondaire', 'is_active' => true,
        ]);

        $annee = AnneeScolaire::create([
            'school_id' => $this->school->id, 'libelle' => '2026-2027',
            'date_debut' => '2026-09-01', 'date_fin' => '2027-07-15', 'is_active' => true,
        ]);
        Trimestre::create([
            'annee_scolaire_id' => $annee->id, 'libelle' => 'Trimestre 1', 'ordre' => 1,
            'date_debut' => '2026-09-01', 'date_fin' => '2027-07-15', 'is_active' => true,
        ]);

        $classe = Classe::create([
            'school_id' => $this->school->id, 'nom' => '5e A', 'qr_token' => 'TOKEN-SALLE-5EA',
        ]);
        $matiere = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Histoire', 'statut' => 'actif']);

        $this->admin = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $this->admin->assignRole('super_admin');

        $this->directeur = User::create([
            'name' => 'Directeur', 'email' => 'directeur@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $this->directeur->assignRole('admin_ecole');

        $fonction = FonctionReferentiel::firstOrCreate([
            'school_id' => $this->school->id, 'label_fr' => 'Enseignant',
        ]);
        $fonction->synchroniserPermissions(RolePermissionSeeder::ROLE_PERMISSIONS['enseignant']);

        $prof = User::create([
            'name' => 'Compte prof', 'email' => 'prof@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        Personnel::create([
            'school_id' => $this->school->id, 'user_id' => $prof->id, 'fonction_id' => $fonction->id,
            'nom_complet' => 'MBARGA Paul', 'sexe' => 'M', 'statut' => 'actif',
        ]);
        $this->prof = $prof->fresh();

        $this->classeMatiere = ClasseMatiere::create([
            'classe_id' => $classe->id, 'matiere_id' => $matiere->id,
            'personnel_id' => $this->prof->personnel->id, 'statut' => 'actif',
        ]);

        // Créneau couvrant toute la journée, tous les jours : la suite tourne
        // quel que soit le jour.
        foreach (range(1, 7) as $jour) {
            EmploiDuTemps::create([
                'school_id' => $this->school->id, 'classe_id' => $classe->id,
                'classe_matiere_id' => $this->classeMatiere->id,
                'jour' => $jour, 'heure_debut' => '00:00', 'heure_fin' => '23:59',
            ]);
        }

        foreach (['Les empires africains', 'La colonisation'] as $i => $titre) {
            $this->lecons[] = ProgressionItem::create([
                'classe_matiere_id' => $this->classeMatiere->id, 'type' => 'lecon',
                'titre' => $titre, 'ordre' => $i,
            ]);
        }
    }

    /** @param list<int> $leconIds */
    private function valider(User $user, array $leconIds)
    {
        return $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/ma-journee/{$this->classeMatiere->id}", [
                'lecons' => $leconIds,
                'appel' => [],
                'qr_token' => 'TOKEN-SALLE-5EA',
            ]);
    }

    private function validePar(ProgressionItem $lecon): ?int
    {
        return DB::table('lecon_seance')->where('progression_item_id', $lecon->id)->value('valide_par');
    }

    public function test_l_admin_peut_valider_une_lecon_et_en_reste_l_auteur(): void
    {
        $this->valider($this->admin, [$this->lecons[0]->id])
            ->assertOk()
            ->assertJsonPath('data.lecons.0.faite_aujourdhui', true)
            ->assertJsonPath('data.lecons.0.validee_par', 'Root');

        $this->assertSame($this->admin->id, $this->validePar($this->lecons[0]));
    }

    public function test_une_correction_de_l_admin_ne_s_approprie_pas_la_validation_du_prof(): void
    {
        $this->valider($this->prof, [$this->lecons[0]->id])->assertOk();

        $this->valider($this->admin, [$this->lecons[0]->id, $this->lecons[1]->id])
            ->assertOk()
            ->assertJsonPath('data.lecons.0.validee_par', 'MBARGA Paul')
            ->assertJsonPath('data.lecons.1.validee_par', 'Root');

        $this->assertSame($this->prof->id, $this->validePar($this->lecons[0]));
        $this->assertSame($this->admin->id, $this->validePar($this->lecons[1]));
    }

    public function test_la_direction_est_notifiee_avec_le_nom_du_validateur(): void
    {
        $this->valider($this->prof, [$this->lecons[0]->id])->assertOk();

        $notification = NotificationInterne::where('user_id', $this->directeur->id)->sole();
        $this->assertSame('lecon_validee', $notification->type);
        $this->assertStringContainsString('MBARGA Paul', $notification->message);
        $this->assertStringContainsString('Les empires africains', $notification->message);
        $this->assertStringContainsString("classe_matiere_id={$this->classeMatiere->id}", $notification->lien);

        // L'enseignant n'est pas de la direction : il ne reçoit rien.
        $this->assertSame(0, NotificationInterne::where('user_id', $this->prof->id)->count());
    }

    public function test_l_auteur_n_est_pas_notifie_de_sa_propre_validation(): void
    {
        $this->valider($this->admin, [$this->lecons[0]->id])->assertOk();

        $this->assertSame(0, NotificationInterne::where('user_id', $this->admin->id)->count());
        $this->assertSame(1, NotificationInterne::where('user_id', $this->directeur->id)->count());
    }

    public function test_un_nouvel_enregistrement_sans_nouvelle_lecon_ne_notifie_pas(): void
    {
        $this->valider($this->prof, [$this->lecons[0]->id])->assertOk();
        $this->valider($this->prof, [$this->lecons[0]->id])->assertOk();

        $this->assertSame(1, NotificationInterne::where('user_id', $this->directeur->id)->count());
    }

    public function test_decocher_une_lecon_la_retire_de_la_seance(): void
    {
        $this->valider($this->admin, [$this->lecons[0]->id, $this->lecons[1]->id])->assertOk();
        $this->valider($this->admin, [$this->lecons[1]->id])->assertOk();

        $seance = Seance::where('classe_matiere_id', $this->classeMatiere->id)->sole();
        $this->assertSame([$this->lecons[1]->id], $seance->lecons()->pluck('progression_items.id')->all());
    }
}
