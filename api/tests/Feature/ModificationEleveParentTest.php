<?php

namespace Tests\Feature;

use App\Models\Eleve;
use App\Models\NotificationInterne;
use App\Models\School;
use App\Models\SyncOutbox;
use App\Models\Tuteur;
use App\Models\User;
use App\Services\ModificationEleveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ModificationEleveParentTest extends TestCase
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
        Permission::firstOrCreate(['name' => 'modifications_eleves.valider', 'guard_name' => 'web']);

        $this->school = School::create(['name' => 'Elites Tech', 'code' => 'ET', 'type' => 'secondaire', 'is_active' => true]);
        $this->eleve = Eleve::create([
            'school_id' => $this->school->id, 'matricule' => '26SEC1', 'nom_complet' => 'Fomesso Mark',
            'sexe' => 'M', 'statut' => 'actif',
        ]);
        $this->tuteur = Tuteur::create(['school_id' => $this->school->id, 'nom_complet' => 'Fomesso Paul', 'telephone' => '699000001']);
        $this->eleve->tuteurs()->attach($this->tuteur->id, ['is_principal' => true]);

        $this->userParent = User::create([
            'name' => 'Fomesso Paul', 'email' => 'fomesso@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $this->userParent->assignRole('parent');
        $this->userParent->givePermissionTo('eleves.view');
        $this->tuteur->update(['user_id' => $this->userParent->id]);
    }

    /**
     * Régression : une fois traitée, une demande de modification disparaissait
     * purement et simplement de l'écran parent — rejetée ou validée, rien ne
     * le lui indiquait plus jamais.
     */
    public function test_le_parent_voit_le_motif_dune_modification_rejetee(): void
    {
        $modification = app(ModificationEleveService::class)->soumettre($this->tuteur, $this->eleve, ['adresse' => 'Nouvelle adresse']);
        app(ModificationEleveService::class)->rejeter($modification, 'Adresse déjà à jour.');

        $reponse = $this->actingAs($this->userParent, 'sanctum')
            ->getJson("/api/v1/parent/enfants/{$this->eleve->id}/modifications");

        $reponse->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.statut', 'rejetee')
            ->assertJsonPath('data.0.motif_rejet', 'Adresse déjà à jour.');
    }

    /** Régression : sans `lien`, la notification créée à la soumission ne menait nulle part côté admin. */
    public function test_la_soumission_notifie_avec_un_lien_exploitable(): void
    {
        $destinataire = User::create([
            'name' => 'Censeur', 'email' => 'censeur@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $destinataire->givePermissionTo('modifications_eleves.valider');

        $modification = app(ModificationEleveService::class)->soumettre($this->tuteur, $this->eleve, ['adresse' => 'Nouvelle adresse']);

        $notification = NotificationInterne::where('user_id', $destinataire->id)->where('type', 'modification_eleve')->firstOrFail();
        $this->assertSame("/modifications-eleves?id={$modification->id}", $notification->lien);
    }

    /** Régression desktop : une validation rejouée avec un id local différent doit retrouver la demande distante équivalente. */
    public function test_le_push_sync_valide_une_modification_creee_localement_avec_id_different(): void
    {
        $admin = User::create([
            'name' => 'Super Admin', 'email' => 'admin@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $admin->givePermissionTo('modifications_eleves.valider');

        $donnees = ['adresse' => 'Nouvelle adresse'];
        $modificationServeur = app(ModificationEleveService::class)->soumettre($this->tuteur, $this->eleve, $donnees);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/sync', ['operations' => [[
                'id' => 'op-validation-modification-locale',
                'methode' => 'POST',
                'chemin' => 'modifications-eleves/999999/valider',
                'school_id' => $this->school->id,
                'corps' => [
                    '__sync' => [
                        'modification_eleve' => [
                            'id' => 999999,
                            'school_id' => $this->school->id,
                            'eleve_id' => $this->eleve->id,
                            'tuteur_id' => $this->tuteur->id,
                            'donnees' => $donnees,
                        ],
                    ],
                ],
            ]]])
            ->assertOk()
            ->assertJsonPath('data.resultats.0.statut', 200);

        $this->assertSame('validee', $modificationServeur->fresh()->statut);
        $this->assertSame('Nouvelle adresse', $this->eleve->fresh()->adresse);
    }

    /** Régression desktop : l'outbox doit transporter l'identité stable de la demande traitée localement. */
    public function test_loutbox_desktop_joint_lidentite_de_la_modification_validee(): void
    {
        config(['sync.local_replica' => true]);

        $admin = User::create([
            'name' => 'Super Admin', 'email' => 'admin-local@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $admin->givePermissionTo('modifications_eleves.valider');

        $modification = app(ModificationEleveService::class)->soumettre($this->tuteur, $this->eleve, ['adresse' => 'Adresse locale']);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/modifications-eleves/{$modification->id}/valider")
            ->assertOk();

        $outbox = SyncOutbox::query()->enAttente()->where('chemin', "modifications-eleves/{$modification->id}/valider")->first();

        $this->assertNotNull($outbox);
        $this->assertSame($modification->id, $outbox->corps['__sync']['modification_eleve']['id']);
        $this->assertSame($this->eleve->id, $outbox->corps['__sync']['modification_eleve']['eleve_id']);
        $this->assertSame(['adresse' => 'Adresse locale'], $outbox->corps['__sync']['modification_eleve']['donnees']);
    }
}
