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
use App\Models\ProgressionColonne;
use App\Models\School;
use App\Models\Seance;
use App\Models\Trimestre;
use App\Models\User;
use App\Support\CataloguePermissions;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
        $fonction->synchroniserPermissions(RolePermissionSeeder::permissionsDuRole('enseignant'));

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

    private function action(string $action, int $id, array $fiche = []): array
    {
        return [
            'operation_id' => (string) Str::uuid(), 'action' => $action, 'lecon_id' => $id,
            ...($action === 'supprimer' ? [] : ['fiche' => $fiche]),
        ];
    }

    private function enregistrerActions(array $actions, array $ids = [], array $autres = [])
    {
        return $this->actingAs($this->prof, 'sanctum')
            ->postJson("/api/v1/ma-journee/{$this->classeMatiere->id}", [
                'lecons' => $ids, 'appel' => [], 'qr_token' => 'TOKEN-SALLE-5EA',
                'actions_lecons' => $actions, ...$autres,
            ]);
    }

    public function test_creation_de_la_fiche_et_validation_avec_trace_dans_les_observations(): void
    {
        $colonne = ProgressionColonne::create(['classe_matiere_id' => $this->classeMatiere->id, 'libelle' => 'Support', 'ordre' => 0]);
        $action = $this->action('creer', -1, [
            'titre' => 'Les indépendances', 'topic' => 'Les indépendances',
            'expected_learning_outcomes' => 'Identifier les dates',
            'semaine' => '4', 'date_prevue' => now()->toDateString(), 'duree' => '2',
            'colonnes_libres' => [$colonne->id => 'Carte'],
        ]);
        $this->enregistrerActions([$action], [-1], ['observations' => 'Cours terminé.'])
            ->assertOk()->assertJsonPath('data.lecons.2.faite_aujourdhui', true)
            ->assertJsonPath('data.seance.observations_libres', 'Cours terminé.');
        $lecon = ProgressionItem::where('titre', 'Les indépendances')->sole();
        $this->assertSame('Identifier les dates', $lecon->expected_learning_outcomes);
        $this->assertSame('Carte', $lecon->colonnes_libres[$colonne->id]);
        $this->assertSame(now()->toDateString(), $lecon->date_realisee->toDateString());
        $this->assertSame($this->prof->id, $this->validePar($lecon));
        $seance = Seance::where('classe_matiere_id', $this->classeMatiere->id)->sole();
        $this->assertStringContainsString("L'enseignant a créé la leçon « Les indépendances ».", $seance->observations);

        // Une requête rejouée après une coupure ne recrée ni la leçon ni sa trace.
        $this->enregistrerActions([$action], [-1], ['observations' => 'Cours terminé.'])->assertOk();
        $this->assertSame(1, ProgressionItem::where('titre', 'Les indépendances')->count());
        $this->assertCount(1, $seance->refresh()->journal_lecons);
        $this->assertSame(1, substr_count($seance->observations, 'a créé la leçon'));
        $this->enregistrerActions([], [$lecon->id], ['observations' => 'Note corrigée.'])->assertOk();
        $this->assertStringContainsString('Note corrigée.', $seance->refresh()->observations);
        $this->assertStringContainsString('a créé la leçon', $seance->observations);
    }

    public function test_creation_sans_cocher_faite_ne_valide_pas_la_lecon(): void
    {
        $this->enregistrerActions([$this->action('creer', -1, ['titre' => 'À préparer'])])->assertOk();
        $lecon = ProgressionItem::where('titre', 'À préparer')->sole();
        $this->assertNull($lecon->date_realisee);
        $this->assertFalse($lecon->estTraitee());
    }

    public function test_modification_preserve_la_date_planifiee_et_trace_le_changement(): void
    {
        $lecon = $this->lecons[0];
        $lecon->update(['date_prevue' => '2026-09-15']);
        $this->enregistrerActions([$this->action('modifier', $lecon->id, [
            'titre' => 'Les empires', 'assessment' => 'Quiz',
        ])], [$lecon->id])->assertOk();
        $this->assertSame('2026-09-15', $lecon->refresh()->date_prevue->toDateString());
        $this->assertSame('Quiz', $lecon->assessment);
        $seance = Seance::where('classe_matiere_id', $this->classeMatiere->id)->sole();
        $this->assertStringContainsString("L'enseignant a modifié la leçon « Les empires africains »", $seance->observations);
        $this->enregistrerActions([$this->action('modifier', $lecon->id, [
            'titre' => 'Autre titre', 'date_prevue' => '2026-10-15',
        ])])->assertUnprocessable()->assertJsonValidationErrors('actions_lecons.0.fiche.date_prevue');
        $this->assertSame('Les empires', $lecon->refresh()->titre);
    }

    public function test_une_action_invalide_annule_tous_les_changements_de_lecons(): void
    {
        $this->enregistrerActions([
            $this->action('creer', -1, ['titre' => 'Ne doit pas rester']),
            $this->action('modifier', 999999, ['titre' => 'Inconnue']),
        ])->assertUnprocessable();
        $this->assertDatabaseMissing('progression_items', ['titre' => 'Ne doit pas rester']);
        $this->assertSame('prevue', Seance::where('classe_matiere_id', $this->classeMatiere->id)->sole()->statut);
    }

    public function test_suppression_tracee_et_refusee_pour_une_lecon_deja_traitee(): void
    {
        $lecon = $this->lecons[0];
        $this->enregistrerActions([$this->action('supprimer', $lecon->id)])->assertOk();
        $this->assertDatabaseMissing('progression_items', ['id' => $lecon->id]);
        $this->assertStringContainsString('a supprimé la leçon', Seance::where('classe_matiere_id', $this->classeMatiere->id)->sole()->observations);
        $this->valider($this->prof, [$this->lecons[1]->id])->assertOk();
        $this->enregistrerActions([$this->action('supprimer', $this->lecons[1]->id)])->assertUnprocessable();
        $this->assertDatabaseHas('progression_items', ['id' => $this->lecons[1]->id]);
    }

    public function test_les_actions_exigent_la_preuve_et_ne_modifient_pas_la_structure_du_programme(): void
    {
        $action = $this->action('creer', -1, ['titre' => 'Nouvelle', 'parent_id' => 99999, 'type' => 'module']);
        $this->enregistrerActions([$action], [], ['qr_token' => null])->assertForbidden();
        $this->assertDatabaseMissing('progression_items', ['titre' => 'Nouvelle']);
        $this->enregistrerActions([$action])->assertOk();
        $lecon = ProgressionItem::where('titre', 'Nouvelle')->sole();
        $this->assertSame('lecon', $lecon->type);
        $this->assertNull($lecon->parent_id);
        $this->enregistrerActions([$this->action('modifier', $lecon->id, ['titre' => 'Nouvelle', 'date_realisee' => now()->toDateString()])])
            ->assertUnprocessable()->assertJsonValidationErrors('actions_lecons.0.fiche.date_realisee');
    }
}
