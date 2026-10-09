<?php

namespace Tests\Feature;

use App\Models\ActionAnnulable;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\ClasseCompetence;
use App\Models\ClasseMatiere;
use App\Models\Competence;
use App\Models\DesktopProvisioning;
use App\Models\Eleve;
use App\Models\Matiere;
use App\Models\Note;
use App\Models\ObservationEvaluation;
use App\Models\Personnel;
use App\Models\School;
use App\Models\Sequence;
use App\Models\SyncOutbox;
use App\Models\SyncTombstone;
use App\Models\Trimestre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HistoriqueActionsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private School $school;

    private Eleve $eleve;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $this->school = School::create(['name' => 'Test', 'code' => 'T', 'type' => 'secondaire', 'is_active' => true]);
        $this->user = User::create(['name' => 'Root', 'email' => 'root@history.test', 'password' => 'secret123', 'school_id' => $this->school->id, 'is_active' => true]);
        $this->user->assignRole('super_admin');
        $this->eleve = Eleve::create(['school_id' => $this->school->id, 'nom_complet' => 'Avant', 'sexe' => 'F', 'statut' => 'actif']);
        $this->actingAs($this->user, 'sanctum');
    }

    private function modifier(string $nom): ActionAnnulable
    {
        $this->putJson('/api/v1/eleves/'.$this->eleve->id, ['nom_complet' => $nom])->assertOk();

        return ActionAnnulable::orderByDesc('id')->firstOrFail();
    }

    private function executer(ActionAnnulable $action, bool $redo = false, ?int $revision = null)
    {
        return $this->postJson('/api/v1/historique-actions/'.$action->uuid.'/'.($redo ? 'retablir' : 'annuler'), [
            'revision' => $revision ?? $action->fresh()->revision,
        ]);
    }

    private function notes(): array
    {
        $annee = AnneeScolaire::create(['school_id' => $this->school->id, 'libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-01', 'is_active' => true]);
        $trimestre = Trimestre::create(['annee_scolaire_id' => $annee->id, 'libelle' => 'T1', 'ordre' => 1, 'is_active' => true, 'date_debut' => '2026-09-01', 'date_fin' => '2026-12-01']);
        $sequence = Sequence::create(['trimestre_id' => $trimestre->id, 'libelle' => 'S1', 'ordre' => 1]);
        $classe = Classe::create(['school_id' => $this->school->id, 'nom' => '6e']);
        $this->eleve->update(['classe_id' => $classe->id]);
        $matiere = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Calcul', 'statut' => 'actif']);
        $attribution = ClasseMatiere::create(['classe_id' => $classe->id, 'matiere_id' => $matiere->id, 'statut' => 'actif']);

        return [$sequence, $attribution];
    }

    public function test_annule_et_retablit_plusieurs_modifications_dans_le_bon_ordre(): void
    {
        $a = $this->modifier('A');
        $b = $this->modifier('B');
        $this->executer($a)->assertStatus(409);
        $this->executer($b)->assertOk();
        $this->assertSame('A', $this->eleve->fresh()->nom_complet);
        $this->executer($a)->assertOk();
        $this->assertSame('Avant', $this->eleve->fresh()->nom_complet);
        $this->executer($b, true)->assertStatus(409);
        $this->executer($a, true)->assertOk();
        $this->executer($b, true)->assertOk();
        $this->assertSame('B', $this->eleve->fresh()->nom_complet);
        $this->assertSame(2, ActionAnnulable::count());
    }

    public function test_une_modification_plus_recente_declenche_un_conflit_sans_ecrasement(): void
    {
        $action = $this->modifier('A');
        $this->eleve->update(['nom_complet' => 'Autre utilisateur']);
        $this->executer($action)->assertStatus(409);
        $this->assertSame('Autre utilisateur', $this->eleve->fresh()->nom_complet);
        $this->assertSame('appliquee', $action->fresh()->etat);
    }

    public function test_une_nouvelle_action_abandonne_le_retablissement_et_un_echec_ne_le_fait_pas(): void
    {
        $a = $this->modifier('A');
        $this->executer($a)->assertOk();
        $this->putJson('/api/v1/eleves/'.$this->eleve->id, ['sexe' => 'invalide'])->assertUnprocessable();
        $this->assertSame('annulee', $a->fresh()->etat);
        $this->modifier('B');
        $this->executer($a, true)->assertStatus(409);
        $this->getJson('/api/v1/historique-actions')->assertOk()->assertJsonPath('data.retablir', null);
    }

    public function test_l_historique_est_isole_par_compte_et_ecole_et_reverifie_les_permissions(): void
    {
        Permission::firstOrCreate(['name' => 'eleves.update', 'guard_name' => 'web']);
        $action = $this->modifier('A');
        $autre = User::create(['name' => 'Autre', 'email' => 'autre@history.test', 'password' => 'secret123', 'school_id' => $this->school->id, 'is_active' => true]);
        $this->actingAs($autre, 'sanctum')->getJson('/api/v1/historique-actions')->assertJsonPath('data.annuler', null);
        $this->executer($action)->assertNotFound();
        $this->actingAs($this->user, 'sanctum');
        $autreEcole = School::create(['name' => 'Autre', 'code' => 'AUT', 'type' => 'primaire', 'is_active' => true]);
        $this->withHeader('X-School-Id', $autreEcole->id)->getJson('/api/v1/historique-actions')->assertJsonPath('data.annuler', null);
        $this->executer($action)->assertNotFound();
        $this->withHeader('X-School-Id', $this->school->id);
        $this->user->removeRole('super_admin');
        $this->actingAs($this->user->fresh(), 'sanctum');
        $this->executer($action)->assertForbidden();
        $this->assertSame('A', $this->eleve->fresh()->nom_complet);
    }

    public function test_la_revision_empeche_un_double_clic_ancien_de_reannuler(): void
    {
        $a = $this->modifier('A');
        $this->executer($a, false, 0)->assertOk();
        $this->executer($a, true, 1)->assertOk();
        $this->executer($a, false, 0)->assertStatus(409);
        $this->assertSame('A', $this->eleve->fresh()->nom_complet);
    }

    public function test_une_saisie_de_notes_et_observations_s_annule_en_bloc_sans_troncature(): void
    {
        [$sequence, $attribution] = $this->notes();
        $autreEleve = Eleve::create(['school_id' => $this->school->id, 'classe_id' => $attribution->classe_id, 'nom_complet' => 'Deux', 'sexe' => 'M', 'statut' => 'actif']);
        $note = Note::create(['eleve_id' => $this->eleve->id, 'classe_matiere_id' => $attribution->id, 'sequence_id' => $sequence->id, 'composante' => 'unique', 'valeur' => 12]);
        $texte = trim(str_repeat('Observation ', 90));
        $this->postJson('/api/v1/classe-matieres/'.$attribution->id.'/notes', [
            'sequence_id' => $sequence->id, 'notes' => [
                ['eleve_id' => $this->eleve->id, 'valeur' => 15, 'observation' => $texte],
                ['eleve_id' => $autreEleve->id, 'valeur' => 18],
            ],
        ])->assertOk();
        $action = ActionAnnulable::sole();
        $this->assertCount(3, $action->changements);
        $nouvelle = Note::where('eleve_id', $autreEleve->id)->sole();
        $this->executer($action)->assertOk();
        $this->assertSame('12.00', $note->fresh()->valeur);
        $this->assertSame(1, Note::count());
        $this->assertSame(0, ObservationEvaluation::count());
        $this->executer($action, true)->assertOk();
        $this->assertSame('15.00', $note->fresh()->valeur);
        $retablie = Note::where('eleve_id', $autreEleve->id)->sole();
        $this->assertSame('18.00', $retablie->valeur);
        $this->assertSame($texte, ObservationEvaluation::sole()->texte);
        $this->assertNotSame($nouvelle->id, $retablie->id);
        $this->assertFalse(SyncTombstone::where('entite', 'notes')->where('entite_id', $retablie->id)->exists());
    }

    public function test_un_conflit_dans_un_lot_annule_toute_la_transaction(): void
    {
        [$sequence, $attribution] = $this->notes();
        $this->postJson('/api/v1/classe-matieres/'.$attribution->id.'/notes', ['sequence_id' => $sequence->id,
            'notes' => [['eleve_id' => $this->eleve->id, 'valeur' => 15, 'observation' => 'Initiale']],
        ])->assertOk();
        $note = Note::sole();
        $note->update(['valeur' => 17]);
        $this->executer(ActionAnnulable::sole())->assertStatus(409);
        $this->assertSame('Initiale', ObservationEvaluation::sole()->texte);
        $this->assertSame('17.00', $note->fresh()->valeur);
    }

    public function test_un_enseignant_ne_peut_plus_annuler_apres_fermeture_de_la_sequence(): void
    {
        [$sequence, $attribution] = $this->notes();
        Permission::firstOrCreate(['name' => 'notes.create', 'guard_name' => 'web']);
        $prof = User::create(['name' => 'Prof', 'email' => 'prof@history.test', 'password' => 'secret123', 'school_id' => $this->school->id, 'is_active' => true]);
        $prof->givePermissionTo('notes.create');
        $personnel = Personnel::create(['school_id' => $this->school->id, 'user_id' => $prof->id, 'nom_complet' => 'Prof', 'sexe' => 'M', 'statut' => 'actif']);
        $attribution->update(['personnel_id' => $personnel->id]);
        $this->actingAs($prof, 'sanctum')->postJson('/api/v1/classe-matieres/'.$attribution->id.'/notes', [
            'sequence_id' => $sequence->id, 'notes' => [['eleve_id' => $this->eleve->id, 'valeur' => 15]],
        ])->assertOk();
        $sequence->update(['saisie_ouverte' => false]);
        $this->executer(ActionAnnulable::sole())->assertForbidden();
        $this->assertSame('15.00', Note::sole()->valeur);
    }

    public function test_les_evaluations_du_primaire_s_annulent_et_se_retablissent(): void
    {
        [$sequence, $matiere] = $this->notes();
        $this->school->update(['type' => 'primaire']);
        $competence = Competence::create(['school_id' => $this->school->id, 'label_fr' => 'Calcul']);
        $attribution = ClasseCompetence::create(['classe_id' => $matiere->classe_id, 'competence_id' => $competence->id, 'notation' => 20,
            'repartition_volets' => ['oral' => 10, 'ecrit' => 5, 'savoir_etre' => 5],
        ]);
        $this->postJson('/api/v1/classe-competences/'.$attribution->id.'/notes-primaire', ['notes' => [
            ['eleve_id' => $this->eleve->id, 'sequence_id' => $sequence->id, 'composante' => 'oral', 'valeur' => 8, 'observation' => 'Bien'],
        ]])->assertOk();
        $a = ActionAnnulable::sole();
        $this->executer($a)->assertOk();
        $this->assertSame(0, Note::count());
        $this->executer($a, true)->assertOk();
        $this->assertSame('8.00', Note::sole()->valeur);
    }

    public function test_un_changement_de_relation_forme_une_limite_non_annulable(): void
    {
        $this->modifier('A');
        $classe = Classe::create(['school_id' => $this->school->id, 'nom' => '6e']);
        $this->putJson('/api/v1/eleves/'.$this->eleve->id, ['classe_id' => $classe->id])->assertOk();
        $this->getJson('/api/v1/historique-actions')->assertJsonPath('data.annuler', null);
        $this->assertSame('indisponible', ActionAnnulable::orderByDesc('id')->first()->etat);
    }

    public function test_l_outbox_transporte_l_identifiant_stable_et_l_annulation_exacte(): void
    {
        config(['sync.local_replica' => true]);
        DesktopProvisioning::create(['user_id' => $this->user->id, 'serveur_url' => 'https://example.test', 'token' => 'fake', 'refresh_token' => 'fake-refresh', 'password' => 'secret', 'provisionne_le' => now()]);
        $uuid = Str::uuid()->toString();
        $this->withHeader('X-Action-Id', $uuid);
        $this->modifier('A');
        $a = ActionAnnulable::sole();
        $this->assertSame($uuid, SyncOutbox::sole()->corps['__historique_action']);
        $this->executer($a)->assertOk();
        $outbox = SyncOutbox::latest('created_at')->get()->firstWhere('chemin', 'historique-actions/'.$uuid.'/annuler');
        $this->assertNotNull($outbox);
        $this->assertSame(0, $outbox->corps['revision']);
    }

    public function test_le_rejeu_desktop_utilise_son_uuid_et_les_identifiants_de_notes_du_serveur(): void
    {
        [$sequence, $attribution] = $this->notes();
        $uuid = Str::uuid()->toString();
        $operation = ['id' => Str::uuid()->toString(), 'methode' => 'POST', 'chemin' => 'classe-matieres/'.$attribution->id.'/notes',
            'school_id' => $this->school->id, 'corps' => ['sequence_id' => $sequence->id,
                '__historique_action' => $uuid, 'notes' => [['eleve_id' => $this->eleve->id, 'valeur' => 15]],
            ],
        ];
        $this->postJson('/api/v1/sync', ['operations' => [$operation]])->assertOk()->assertJsonPath('data.resultats.0.statut', 200);
        $this->assertSame($uuid, ActionAnnulable::sole()->uuid);
        $this->modifier('Action web plus recente');
        $annulation = ['id' => Str::uuid()->toString(), 'methode' => 'POST', 'chemin' => 'historique-actions/'.$uuid.'/annuler', 'school_id' => $this->school->id, 'corps' => ['revision' => 0]];
        $this->postJson('/api/v1/sync', ['operations' => [$annulation]])->assertOk()->assertJsonPath('data.resultats.0.statut', 200);
        $this->assertSame(0, Note::count());
        $this->assertSame('Action web plus recente', $this->eleve->fresh()->nom_complet);
        $annulation['id'] = Str::uuid()->toString();
        $annulation['chemin'] = 'historique-actions/'.$uuid.'/retablir';
        $annulation['corps']['revision'] = 1;
        $this->postJson('/api/v1/sync', ['operations' => [$annulation]])->assertJsonPath('data.resultats.0.statut', 200);
        $this->assertSame('15.00', Note::sole()->valeur);
        $this->postJson('/api/v1/sync', ['operations' => [$annulation]])->assertJsonPath('data.resultats.0.statut', 200);
        $this->assertSame(1, Note::count());
    }

    public function test_un_identifiant_de_note_modifie_par_la_synchro_ne_vise_pas_une_autre_note(): void
    {
        [$sequence, $attribution] = $this->notes();
        $this->postJson('/api/v1/classe-matieres/'.$attribution->id.'/notes', ['sequence_id' => $sequence->id,
            'notes' => [['eleve_id' => $this->eleve->id, 'valeur' => 15]],
        ])->assertOk();
        $action = ActionAnnulable::sole();
        $note = Note::sole();
        $note->forceFill(['id' => $note->id + 100])->saveQuietly();
        $this->executer($action)->assertOk();
        $this->assertSame(0, Note::count());
        $this->executer($action, true)->assertOk();
        $this->assertSame('15.00', Note::sole()->valeur);
    }

    public function test_une_observation_seule_reverifie_aussi_la_fermeture_des_notes(): void
    {
        [$sequence, $attribution] = $this->notes();
        Permission::firstOrCreate(['name' => 'notes.create', 'guard_name' => 'web']);
        $prof = User::create(['name' => 'Prof', 'email' => 'observation@history.test', 'password' => 'secret123', 'school_id' => $this->school->id, 'is_active' => true]);
        $prof->givePermissionTo('notes.create');
        $personnel = Personnel::create(['school_id' => $this->school->id, 'user_id' => $prof->id, 'nom_complet' => 'Prof', 'sexe' => 'M', 'statut' => 'actif']);
        $attribution->update(['personnel_id' => $personnel->id]);
        $note = Note::create(['eleve_id' => $this->eleve->id, 'classe_matiere_id' => $attribution->id, 'sequence_id' => $sequence->id, 'composante' => 'unique', 'valeur' => 15, 'saisi_par' => $personnel->id]);
        $this->actingAs($prof, 'sanctum')->postJson('/api/v1/classe-matieres/'.$attribution->id.'/notes', [
            'sequence_id' => $sequence->id, 'notes' => [['eleve_id' => $this->eleve->id, 'valeur' => 15, 'observation' => 'Seule modification']],
        ])->assertOk();
        $action = ActionAnnulable::sole();
        $this->assertCount(1, $action->changements);
        $sequence->update(['saisie_ouverte' => false]);
        $this->executer($action)->assertForbidden();
        $this->assertSame('Seule modification', ObservationEvaluation::sole()->texte);
        $this->assertSame('15.00', $note->fresh()->valeur);
    }

    public function test_une_fiche_personnel_restaure_les_dates_et_le_json(): void
    {
        $personnel = Personnel::create(['school_id' => $this->school->id, 'nom_complet' => 'Avant', 'sexe' => 'M', 'statut' => 'actif',
            'enfants' => [['nom_complet' => 'Enfant', 'sexe' => 'F', 'date_naissance' => '2020-05-01']],
        ]);
        $this->putJson('/api/v1/personnels/'.$personnel->id, ['nom_complet' => 'Apres', 'date_naissance' => '1990-01-02', 'enfants' => []])->assertOk();
        $action = ActionAnnulable::sole();
        $this->executer($action)->assertOk();
        $this->assertSame('Avant', $personnel->fresh()->nom_complet);
        $this->assertCount(1, $personnel->fresh()->enfants);
        $this->assertNull($personnel->fresh()->date_naissance);
        $this->executer($action, true)->assertOk();
        $this->assertSame('1990-01-02', $personnel->fresh()->date_naissance->format('Y-m-d'));
        $this->assertSame([], $personnel->fresh()->enfants);
    }

    public function test_une_panne_de_l_outbox_annule_aussi_la_modification_et_son_historique(): void
    {
        config(['sync.local_replica' => true]);
        SyncOutbox::creating(function () {
            throw new \RuntimeException('Panne outbox simulee');
        });
        $this->putJson('/api/v1/eleves/'.$this->eleve->id, ['nom_complet' => 'Apres'])->assertStatus(500);
        $this->assertSame('Avant', $this->eleve->fresh()->nom_complet);
        $this->assertSame(0, ActionAnnulable::count());
        $this->assertSame(0, SyncOutbox::count());
    }

    public function test_une_sauvegarde_sans_changement_ne_cree_pas_d_action(): void
    {
        $this->putJson('/api/v1/eleves/'.$this->eleve->id, ['nom_complet' => 'Avant'])->assertOk();
        $this->assertSame(0, ActionAnnulable::count());
    }
}
