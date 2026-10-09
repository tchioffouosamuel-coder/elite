<?php

namespace Tests\Feature;

use App\Models;
use App\Models\ActionAnnulable;
use App\Models\AnneeScolaire;
use App\Models\Banque;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\Departement;
use App\Models\DesktopProvisioning;
use App\Models\DetteAnterieure;
use App\Models\DossierScolarite;
use App\Models\Eleve;
use App\Models\EleveTuteur;
use App\Models\FonctionReferentiel;
use App\Models\Matiere;
use App\Models\Note;
use App\Models\Personnel;
use App\Models\School;
use App\Models\Sequence;
use App\Models\SyncOutbox;
use App\Models\SyncTombstone;
use App\Models\Trimestre;
use App\Models\Tuteur;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HistoriqueImportsTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $user;

    private array $fichiers = [];

    private array $dossiers = [];

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $this->school = School::create(['name' => 'Test', 'code' => 'T', 'type' => 'secondaire', 'is_active' => true]);
        $this->user = User::create(['name' => 'Root', 'email' => 'root@imports.test', 'password' => 'secret123', 'school_id' => $this->school->id, 'is_active' => true]);
        $this->user->assignRole('super_admin');
        $this->actingAs($this->user, 'sanctum')->withHeader('X-School-Id', $this->school->id);
    }

    protected function tearDown(): void
    {
        foreach ($this->fichiers as $chemin) {
            @unlink($chemin);
        }
        foreach ($this->dossiers as $dossier) {
            foreach (glob($dossier.'/*.xlsx') ?: [] as $chemin) {
                @unlink($chemin);
            }
            @rmdir($dossier);
        }
        parent::tearDown();
    }

    private function fichier(array $lignes, int $entete = 1): UploadedFile
    {
        $classeur = new Spreadsheet;
        $classeur->getActiveSheet()->fromArray($lignes, null, 'A'.$entete);
        $chemin = tempnam(sys_get_temp_dir(), 'undo-import-');
        $this->fichiers[] = $chemin;
        (new Xlsx($classeur))->save($chemin);
        $classeur->disconnectWorksheets();

        return new UploadedFile($chemin, 'import.xlsx', null, null, true);
    }

    private function importerEleves(array $lignes): ActionAnnulable
    {
        $reponse = $this->postJson('/api/v1/eleves/import', ['file' => $this->fichier($lignes)]);
        $this->assertSame(200, $reponse->status(), $reponse->getContent());

        return ActionAnnulable::latest('id')->firstOrFail();
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
        $matiere = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Calcul', 'statut' => 'actif']);
        $attribution = ClasseMatiere::create(['classe_id' => $classe->id, 'matiere_id' => $matiere->id, 'statut' => 'actif']);

        return [$sequence, $attribution, $annee];
    }

    public function test_un_import_de_notes_restaure_les_modifications_et_retire_les_creations(): void
    {
        [$sequence, $attribution] = $this->notes();
        $un = Eleve::create(['school_id' => $this->school->id, 'classe_id' => $attribution->classe_id, 'matricule' => 'E1', 'nom_complet' => 'Un', 'sexe' => 'F']);
        $deux = Eleve::create(['school_id' => $this->school->id, 'classe_id' => $attribution->classe_id, 'matricule' => 'E2', 'nom_complet' => 'Deux', 'sexe' => 'M']);
        $note = Note::create(['eleve_id' => $un->id, 'classe_matiere_id' => $attribution->id, 'sequence_id' => $sequence->id, 'valeur' => 12]);
        $this->postJson('/api/v1/classe-matieres/'.$attribution->id.'/notes/import', [
            'sequence_id' => $sequence->id, 'file' => $this->fichier([['Matricule', 'Note'], ['E1', 16], ['E2', 18]]),
        ])->assertOk();
        $action = ActionAnnulable::sole();
        $this->assertCount(2, $action->changements);
        $this->executer($action)->assertOk();
        $this->assertSame('12.00', $note->fresh()->valeur);
        $this->assertSame(1, Note::count());
        $this->executer($action, true)->assertOk();
        $this->assertSame('16.00', $note->fresh()->valeur);
        $this->assertSame('18.00', Note::where('eleve_id', $deux->id)->sole()->valeur);
        $this->executer($action)->assertOk();
    }

    public function test_un_import_eleves_restaure_aussi_les_tuteurs_liens_classes_et_dettes(): void
    {
        [$sequence, $attribution, $annee] = $this->notes();
        $eleve = Eleve::create(['school_id' => $this->school->id, 'matricule' => 'E1', 'nom_complet' => 'Avant', 'sexe' => 'F']);
        $ancien = Tuteur::create(['school_id' => $this->school->id, 'nom_complet' => 'Ancien', 'telephone' => '600']);
        $eleve->tuteurs()->attach($ancien, ['lien_parente' => 'Ancien', 'is_principal' => true]);
        $dossier = DossierScolarite::create(['school_id' => $this->school->id, 'annee_scolaire_id' => $annee->id, 'eleve_id' => $eleve->id, 'montant_scolarite' => 1000, 'report_dette' => 10]);
        $action = $this->importerEleves([
            ['Matricule', 'Nom complet', 'Sexe', 'Classe', 'Nom du pere', 'Telephone du pere', 'Frais scolarite', 'Montant scolarite', 'Annee scol'],
            ['E1', 'Apres', 'F', '6e', 'Pere', '699000001', 100, 40, '2025-2026'],
            ['E2', 'Nouveau', 'M', '6e', 'Pere', '699000001', 0, 0, '2025-2026'],
        ]);
        $this->assertSame('appliquee', $action->etat);
        $this->assertSame(70, $dossier->fresh()->report_dette);
        $this->assertSame(1, DetteAnterieure::count());
        $this->assertSame(2, Eleve::count());
        $this->executer($action)->assertOk();
        $this->assertSame('Avant', $eleve->fresh()->nom_complet);
        $this->assertNull($eleve->fresh()->classe_id);
        $this->assertSame([$ancien->id], $eleve->fresh()->tuteurs()->pluck('tuteurs.id')->all());
        $this->assertSame(1, Eleve::count());
        $this->assertSame(1, Tuteur::count());
        $this->assertSame(0, DetteAnterieure::count());
        $this->assertSame(10, $dossier->fresh()->report_dette);
        $this->executer($action, true)->assertOk();
        $this->assertSame('Apres', $eleve->fresh()->nom_complet);
        $this->assertSame(70, $dossier->fresh()->report_dette);
        $this->assertSame(2, Eleve::count());
        $this->assertSame(2, EleveTuteur::count());
        $this->assertSame(1, DetteAnterieure::count());
        $this->executer($action)->assertOk();
        $this->assertSame(1, Eleve::count());
    }

    public function test_une_nouvelle_note_sur_un_eleve_importe_empeche_sa_suppression_en_cascade(): void
    {
        [$sequence, $attribution] = $this->notes();
        $action = $this->importerEleves([['Matricule', 'Nom complet', 'Sexe', 'Classe'], ['E1', 'Un', 'F', '6e'], ['E2', 'Deux', 'M', '6e']]);
        $eleve = Eleve::where('matricule', 'E1')->sole();
        Note::create(['eleve_id' => $eleve->id, 'classe_matiere_id' => $attribution->id, 'sequence_id' => $sequence->id, 'valeur' => 14]);
        $this->executer($action)->assertStatus(409);
        $this->assertSame(2, Eleve::count());
        $this->assertSame(1, Note::count());
        $this->assertSame('appliquee', $action->fresh()->etat);
    }

    public function test_une_modification_sur_une_seule_ligne_bloque_tout_le_lot(): void
    {
        $action = $this->importerEleves([['Matricule', 'Nom complet', 'Sexe'], ['E1', 'Un', 'F'], ['E2', 'Deux', 'M']]);
        Eleve::where('matricule', 'E1')->firstOrFail()->update(['adresse' => 'Nouvelle adresse']);
        $this->executer($action)->assertStatus(409);
        $this->assertSame(2, Eleve::count());
    }

    public function test_un_tuteur_cree_partage_ulterieurement_bloque_l_annulation(): void
    {
        $action = $this->importerEleves([['Matricule', 'Nom complet', 'Sexe', 'Nom du pere', 'Telephone du pere'], ['E1', 'Un', 'F', 'Pere', '699000001']]);
        $autre = Eleve::create(['school_id' => $this->school->id, 'nom_complet' => 'Autre', 'sexe' => 'M']);
        $autre->tuteurs()->attach(Tuteur::sole());
        $this->executer($action)->assertStatus(409);
        $this->assertSame(2, EleveTuteur::count());
    }

    public function test_l_import_personnel_restaure_les_referentiels_titulaires_et_comptes(): void
    {
        $comptesAvant = User::count();
        $ancien = Personnel::create(['school_id' => $this->school->id, 'nom_complet' => 'Ancien', 'statut' => 'actif']);
        $classe = Classe::create(['school_id' => $this->school->id, 'nom' => '6e', 'titulaire_id' => $ancien->id]);
        $this->postJson('/api/v1/personnels/import', ['file' => $this->fichier([
            ['Matricule', 'Nom complet', 'Civilite', 'Fonction', 'Departement', 'Banque', 'Affectation'],
            ['P1', 'Nouvel Agent', 'Mme', 'Fonction test', 'Departement test', 'Banque test', '6e'],
        ], 3)])->assertOk();
        $action = ActionAnnulable::sole();
        $this->assertSame('appliquee', $action->etat);
        $personnel = Personnel::where('matricule', 'P1')->sole();
        $compte = $personnel->user;
        $motDePasse = $compte->getRawOriginal('password');
        $this->assertSame($personnel->id, $classe->fresh()->titulaire_id);
        $this->executer($action)->assertOk();
        $this->assertSame($ancien->id, $classe->fresh()->titulaire_id);
        $this->assertSame(1, Personnel::count());
        $this->assertSame($comptesAvant, User::count());
        $this->assertSame(0, FonctionReferentiel::count());
        $this->assertSame(0, Departement::count());
        $this->assertSame(0, Banque::count());
        $this->executer($action, true)->assertOk();
        $retabli = Personnel::where('matricule', 'P1')->sole();
        $this->assertSame($retabli->id, $classe->fresh()->titulaire_id);
        $this->assertSame($motDePasse, $retabli->user->getRawOriginal('password'));
        $this->assertNotSame($personnel->id, $retabli->id);
        $this->assertNotSame($compte->id, $retabli->user_id);
        $this->assertTrue(SyncTombstone::where('entite', 'personnels')->where('entite_id', $personnel->id)->exists());
        $this->executer($action)->assertOk();
    }

    public function test_un_compte_importe_ayant_recu_un_role_ne_peut_plus_etre_supprime(): void
    {
        $comptesAvant = User::count();
        $this->postJson('/api/v1/personnels/import', ['file' => $this->fichier([['Matricule', 'Nom complet'], ['P1', 'Agent']], 3)])->assertOk();
        Role::create(['name' => 'nouveau_role', 'guard_name' => 'web']);
        Personnel::sole()->user->assignRole('nouveau_role');
        $this->executer(ActionAnnulable::sole())->assertStatus(409);
        $this->assertSame($comptesAvant + 1, User::count());
    }

    public function test_les_lots_consecutifs_d_un_import_massif_forment_une_seule_action_sans_limite_audit(): void
    {
        $lignes = [['Matricule', 'Nom complet', 'Sexe', 'Nom du pere', 'Telephone du pere']];
        for ($i = 1; $i <= 205; $i++) {
            $lignes[] = ['E'.$i, 'Eleve '.$i, 'F', 'Pere commun', '699000001'];
        }
        $resultat = $this->postJson('/api/v1/eleves/import/preparer', ['file' => $this->fichier($lignes)])->assertOk()->json('data');
        $this->dossiers[] = storage_path('app/private/imports-eleves/'.$resultat['token']);
        $this->assertSame(0, ActionAnnulable::count());
        for ($i = 0; $i < $resultat['lots']; $i++) {
            $this->withHeader('X-Action-Id', (string) Str::uuid())
                ->postJson('/api/v1/eleves/import/traiter/'.$resultat['token'], ['index' => $i])->assertOk();
        }
        $action = ActionAnnulable::sole();
        $this->assertSame($resultat['lots'] - 1, $action->revision);
        $this->assertSame(205, Eleve::count());
        $this->assertCount(411, $action->changements);
        $this->executer($action, false, 0)->assertStatus(409);
        $this->executer($action)->assertOk();
        $this->assertSame(0, Eleve::count());
        $this->assertSame(0, EleveTuteur::count());
        $this->assertSame(0, Tuteur::count());
        $this->executer($action, true)->assertOk();
        $this->assertSame(205, Eleve::count());
        $this->assertSame(205, EleveTuteur::count());
        $this->assertSame(1, Tuteur::count());
    }

    public function test_l_import_massif_desktop_emporte_les_fichiers_de_lots_et_un_uuid_commun(): void
    {
        config(['sync.local_replica' => true]);
        DesktopProvisioning::create(['user_id' => $this->user->id, 'serveur_url' => 'https://example.test', 'token' => 'fake', 'refresh_token' => 'fake-refresh', 'password' => 'secret', 'provisionne_le' => now()]);
        $lignes = [['Matricule', 'Nom complet', 'Sexe']];
        for ($i = 1; $i <= 61; $i++) {
            $lignes[] = ['E'.$i, 'Eleve '.$i, 'F'];
        }
        $resultat = $this->postJson('/api/v1/eleves/import/preparer', ['file' => $this->fichier($lignes)])->assertOk()->json('data');
        $this->dossiers[] = storage_path('app/private/imports-eleves/'.$resultat['token']);
        $this->assertSame(0, SyncOutbox::count());
        for ($i = 0; $i < 2; $i++) {
            $this->postJson('/api/v1/eleves/import/traiter/'.$resultat['token'], ['index' => $i])->assertOk();
        }
        $action = ActionAnnulable::sole();
        $operations = SyncOutbox::enAttente()->get();
        $this->assertCount(2, $operations);
        foreach ($operations as $operation) {
            $this->assertSame($action->uuid, $operation->corps['__historique_action']);
            $this->assertTrue($operation->corps['file']['__sync_fichier__']);
            $this->assertNotEmpty(base64_decode($operation->corps['file']['contenu_base64'], true));
        }
        $this->assertFalse($operations[0]->corps['__import_dernier']);
        $this->assertTrue($operations[1]->corps['__import_dernier']);
        $this->executer($action)->assertOk();
        $this->assertSame(1, SyncOutbox::enAttente()->get()->last()->corps['revision']);
    }

    public function test_le_rejeu_serveur_des_lots_desktop_et_leur_annulation_sans_dossier_temporaire(): void
    {
        $uuid = (string) Str::uuid();
        $token = (string) Str::uuid();
        for ($i = 0; $i < 2; $i++) {
            $fichier = $this->fichier([['Matricule', 'Nom complet', 'Sexe'], ['E'.$i, 'Eleve '.$i, 'F']]);
            $operation = ['id' => (string) Str::uuid(), 'methode' => 'POST', 'chemin' => 'eleves/import/traiter/'.$token,
                'school_id' => $this->school->id, 'corps' => ['index' => $i, '__historique_action' => $uuid, '__import_dernier' => $i === 1,
                    'file' => ['__sync_fichier__' => true, 'nom' => 'lot.xlsx', 'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'contenu_base64' => base64_encode($fichier->get())]],
            ];
            $this->postJson('/api/v1/sync', ['operations' => [$operation]])->assertOk()->assertJsonPath('data.resultats.0.statut', 200);
        }
        $action = ActionAnnulable::sole();
        $this->assertSame($uuid, $action->uuid);
        $this->assertSame(1, $action->revision);
        $this->assertSame(2, Eleve::count());
        $this->postJson('/api/v1/sync', ['operations' => [[
            'id' => (string) Str::uuid(), 'methode' => 'POST', 'chemin' => 'historique-actions/'.$uuid.'/annuler',
            'school_id' => $this->school->id, 'corps' => ['revision' => 1],
        ]]])->assertOk()->assertJsonPath('data.resultats.0.statut', 200);
        $this->assertSame(0, Eleve::count());
    }

    public function test_un_import_simple_de_banques_se_restaure_et_un_doublon_bloque_le_retablissement(): void
    {
        $banque = Banque::create(['nom' => 'Existante', 'code' => 'AVANT']);
        $this->postJson('/api/v1/banques/import', ['file' => $this->fichier([['Banque', 'Code'], ['Existante', 'APRES'], ['Nouvelle', 'N']])])->assertOk();
        $action = ActionAnnulable::sole();
        $this->executer($action)->assertOk();
        $this->assertSame('AVANT', $banque->fresh()->code);
        Banque::create(['nom' => 'Nouvelle', 'code' => 'AUTRE']);
        $this->executer($action, true)->assertStatus(409);
        $this->assertSame('AVANT', $banque->fresh()->code);
        $this->assertSame('AUTRE', Banque::where('nom', 'Nouvelle')->sole()->code);
    }

    public function test_un_import_sans_changement_ou_invalide_ne_cree_pas_d_action(): void
    {
        Banque::create(['nom' => 'Existante', 'code' => 'CODE']);
        $this->postJson('/api/v1/banques/import', ['file' => $this->fichier([['Banque', 'Code'], ['Existante', 'CODE']])])->assertOk();
        $this->assertSame(0, ActionAnnulable::count());
        $this->postJson('/api/v1/eleves/import', [])->assertUnprocessable();
        $this->assertSame(0, ActionAnnulable::count());
    }

    public function test_les_privileges_et_le_perimetre_multi_ecoles_sont_reverifies(): void
    {
        $autre = School::create(['name' => 'Primaire', 'code' => 'P', 'type' => 'primaire', 'is_active' => true]);
        $this->flushHeaders();
        $action = $this->importerEleves([['Matricule', 'Nom complet', 'Sexe', 'Categorie ecole'], ['E1', 'Un', 'F', 'Secondaire'], ['E2', 'Deux', 'F', 'Primaire']]);
        $this->assertCount(2, $action->contexte['school_ids']);
        $this->withHeader('X-School-Id', $action->school_id);
        $this->executer($action)->assertForbidden();
        $this->assertSame(2, Eleve::count());
        $this->flushHeaders();
        $this->executer($action)->assertOk();
        $this->assertSame(0, Eleve::count());
        $this->withHeader('X-School-Id', $this->school->id);
        $actionLocale = $this->importerEleves([['Matricule', 'Nom complet', 'Sexe'], ['E3', 'Trois', 'F']]);
        $this->executer($actionLocale)->assertOk();
        Permission::firstOrCreate(['name' => 'eleves.import', 'guard_name' => 'web']);
        $this->user->removeRole('super_admin');
        $this->actingAs($this->user->fresh(), 'sanctum')->withHeader('X-School-Id', $this->school->id);
        $this->executer($actionLocale, true)->assertForbidden();
    }

    public static function referentiels(): array
    {
        return [
            'classes' => ['classes', Classe::class, [['Nom'], ['Classe importee']]],
            'niveaux' => ['niveaux', Models\Niveau::class, [['Code', 'Nom (FR)', 'Nom (EN)'], ['NIMP', 'Niveau importe', 'Imported level']]],
            'departements' => ['departements', Departement::class, [['Nom'], ['Departement importe']]],
            'fonctions' => ['fonctions-referentiel', FonctionReferentiel::class, [['Fonction'], ['Fonction importee']]],
            'banques' => ['banques', Banque::class, [['Banque', 'Code'], ['Banque importee', 'IMP']]],
            'sous-systemes' => ['sous-systemes', Models\SousSysteme::class, [['Code', 'Nom'], ['IMP', 'Systeme importe']]],
            'appreciations' => ['appreciations', Models\Appreciation::class, [['Appreciation'], ['Appreciation importee']]],
            'inventaire' => ['inventaire', Models\InventaireArticle::class, [['Article', 'Quantite'], ['Article importe', 5]]],
            'infrastructures' => ['infrastructures', Models\Infrastructure::class, [['Type', 'Libelle'], ['bloc_administratif', 'Batiment importe']]],
            'equipements' => ['infrastructures/equipements', Models\EquipementMobilier::class, [['Nature', 'Quantite'], ['Table importee', 5]]],
            'vehicules' => ['bus/vehicules', Models\BusVehicule::class, [['Immatriculation', 'Capacite'], ['IMP123', 10]]],
            'tranches' => ['tranches-scolarite', Models\TrancheScolarite::class, [['Tranche', 'Pourcentage', 'Date echeance', 'Ordre'], ['Tranche importee', 50, '2026-12-01', 1]]],
            'grille-frais' => ['tarifs/grille-frais', Models\GrilleFrais::class, [['Classe', 'Montant'], ['6e', 1000]]],
            'frais-annexes' => ['tarifs/frais-annexes', Models\FraisAnnexe::class, [['Libelle', 'Montant'], ['Frais importes', 500]]],
        ];
    }

    #[DataProvider('referentiels')]
    public function test_chaque_import_de_referentiel_supporte_annulation_et_retablissement(string $route, string $modele, array $lignes): void
    {
        $this->notes();
        $nombreAvant = $modele::count();
        $this->postJson('/api/v1/'.$route.'/import', ['file' => $this->fichier($lignes)])->assertOk();
        $action = ActionAnnulable::sole();
        $this->assertSame('appliquee', $action->etat);
        $this->assertSame($nombreAvant + 1, $modele::count());
        $this->executer($action)->assertOk();
        $this->assertSame($nombreAvant, $modele::count());
        $this->executer($action, true)->assertOk();
        $this->assertSame($nombreAvant + 1, $modele::count());
        $this->executer($action)->assertOk();
    }

    public function test_les_matieres_importees_et_leurs_affectations_s_annulent_ensemble(): void
    {
        [$sequence, $attribution] = $this->notes();
        $this->postJson('/api/v1/matieres/import', ['cycle' => 'secondaire', 'file' => $this->fichier([
            ['Matiere', 'Departement', 'Classes', 'Coefficient'], ['Physique', 'Sciences', '6e', 3],
        ])])->assertOk();
        $action = ActionAnnulable::sole();
        $this->assertCount(3, $action->changements);
        $this->executer($action)->assertOk();
        $this->assertSame(1, Matiere::count());
        $this->assertSame(1, ClasseMatiere::count());
        $this->assertSame(0, Departement::count());
        $this->executer($action, true)->assertOk();
        $matiere = Matiere::where('nom', 'Physique')->sole();
        $this->assertSame(3.0, (float) ClasseMatiere::where('matiere_id', $matiere->id)->sole()->coefficient);
        $this->executer($action)->assertOk();
    }

    public function test_les_competences_et_matieres_installees_par_import_se_restaurent(): void
    {
        $this->school->update(['type' => 'primaire']);
        [$sequence, $attribution] = $this->notes();
        $competence = Models\Competence::create(['school_id' => $this->school->id, 'label_fr' => 'Calcul']);
        $matiere = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Arithmetique', 'competence_id' => $competence->id, 'statut' => 'actif']);
        $this->postJson('/api/v1/competences/import', ['file' => $this->fichier([
            ['Competence', 'Classe', 'Notation', 'Oral', 'Ecrit'], ['Calcul', '6e', 20, 10, 10],
        ])])->assertOk();
        $action = ActionAnnulable::sole();
        $this->assertCount(2, $action->changements);
        $this->executer($action)->assertOk();
        $this->assertSame(0, Models\ClasseCompetence::count());
        $this->assertSame(1, ClasseMatiere::count());
        $this->executer($action, true)->assertOk();
        $this->assertSame(1, Models\ClasseCompetence::count());
        $this->assertSame(2, ClasseMatiere::count());
        $this->executer($action)->assertOk();
    }

    public function test_un_import_exclu_ne_propose_pas_l_annulation_d_une_action_plus_ancienne(): void
    {
        $this->importerEleves([['Matricule', 'Nom complet', 'Sexe'], ['E1', 'Un', 'F']]);
        $this->postJson('/api/v1/depenses/import', ['file' => $this->fichier([['Libelle', 'Montant'], ['Depense', 100]])])->assertOk();
        $this->getJson('/api/v1/historique-actions')->assertOk()->assertJsonPath('data.annuler', null);
        $this->assertSame('indisponible', ActionAnnulable::latest('id')->first()->etat);
        $this->assertSame(1, Eleve::count());
    }

    public function test_un_conflit_entre_deux_lots_n_est_pas_incorpore_a_l_historique(): void
    {
        $lignes = [['Matricule', 'Nom complet', 'Sexe']];
        for ($i = 1; $i <= 61; $i++) {
            $lignes[] = ['E'.$i, 'Eleve '.$i, 'F'];
        }
        $resultat = $this->postJson('/api/v1/eleves/import/preparer', ['file' => $this->fichier($lignes)])->assertOk()->json('data');
        $dossier = storage_path('app/private/imports-eleves/'.$resultat['token']);
        $this->dossiers[] = $dossier;
        $this->postJson('/api/v1/eleves/import/traiter/'.$resultat['token'], ['index' => 0])->assertOk();
        Eleve::where('matricule', 'E1')->firstOrFail()->update(['adresse' => 'Autre saisie']);
        $this->postJson('/api/v1/eleves/import/traiter/'.$resultat['token'], ['index' => 1])->assertStatus(409);
        $this->assertSame(60, Eleve::count());
        $this->assertSame(0, ActionAnnulable::sole()->revision);
        $this->assertFileExists($dossier.'/1.xlsx');
        $this->executer(ActionAnnulable::sole())->assertStatus(409);
    }

    public function test_une_panne_outbox_laisse_le_lot_disponible_et_annule_ses_ecritures(): void
    {
        config(['sync.local_replica' => true]);
        $resultat = $this->postJson('/api/v1/eleves/import/preparer', ['file' => $this->fichier([['Matricule', 'Nom complet', 'Sexe'], ['E1', 'Un', 'F']])])->assertOk()->json('data');
        $dossier = storage_path('app/private/imports-eleves/'.$resultat['token']);
        $this->dossiers[] = $dossier;
        SyncOutbox::creating(fn () => throw new \RuntimeException('Outbox indisponible'));
        $this->postJson('/api/v1/eleves/import/traiter/'.$resultat['token'], ['index' => 0])->assertServerError();
        $this->assertSame(0, Eleve::count());
        $this->assertSame(0, ActionAnnulable::count());
        $this->assertFileExists($dossier.'/0.xlsx');
    }

    public function test_un_lot_sans_changement_garde_le_uuid_sans_proposer_une_annulation_vide(): void
    {
        $eleve = Eleve::create(['school_id' => $this->school->id, 'matricule' => 'E1', 'nom_complet' => 'Un', 'sexe' => 'F', 'statut' => 'actif']);
        $resultat = $this->postJson('/api/v1/eleves/import/preparer', ['file' => $this->fichier([['Matricule', 'Nom complet', 'Sexe'], ['E1', 'Un', 'F']])])->assertOk()->json('data');
        $this->dossiers[] = storage_path('app/private/imports-eleves/'.$resultat['token']);
        $this->postJson('/api/v1/eleves/import/traiter/'.$resultat['token'], ['index' => 0])->assertOk();
        $this->assertSame('vide', ActionAnnulable::sole()->etat);
        $this->getJson('/api/v1/historique-actions')->assertOk()->assertJsonPath('data.annuler', null);
        $this->assertSame('Un', $eleve->fresh()->nom_complet);
    }

    public function test_le_retablissement_refuse_une_classe_transferee_hors_du_perimetre(): void
    {
        $classe = Classe::create(['school_id' => $this->school->id, 'nom' => '6e']);
        $action = $this->importerEleves([['Matricule', 'Nom complet', 'Sexe', 'Classe'], ['E1', 'Un', 'F', '6e']]);
        $this->executer($action)->assertOk();
        $autre = School::create(['name' => 'Autre', 'code' => 'A', 'type' => 'secondaire', 'is_active' => true]);
        $classe->update(['school_id' => $autre->id]);
        $this->executer($action, true)->assertForbidden();
        $this->assertSame(0, Eleve::count());
        $this->assertSame('annulee', $action->fresh()->etat);
    }

    public function test_un_import_agrege_portant_sur_une_seule_autre_ecole_est_visible_dans_cette_ecole(): void
    {
        $autre = School::create(['name' => 'Primaire', 'code' => 'P', 'type' => 'primaire', 'is_active' => true]);
        $this->flushHeaders();
        $action = $this->importerEleves([['Matricule', 'Nom complet', 'Sexe', 'Categorie ecole'], ['E1', 'Un', 'F', 'Primaire']]);
        $this->assertSame($autre->id, $action->school_id);
        $this->withHeader('X-School-Id', $autre->id)->getJson('/api/v1/historique-actions')
            ->assertOk()->assertJsonPath('data.annuler.id', $action->uuid);
        $this->executer($action)->assertOk();
    }

    public function test_le_rejeu_multi_ecoles_retablit_le_perimetre_original_et_annule_tout_l_import(): void
    {
        $autre = School::create(['name' => 'Primaire', 'code' => 'P', 'type' => 'primaire', 'is_active' => true]);
        $uuid = (string) Str::uuid();
        $ecoles = [$this->school->id, $autre->id];
        $fichier = $this->fichier([['Matricule', 'Nom complet', 'Sexe', 'Categorie ecole'], ['E1', 'Un', 'F', 'Secondaire'], ['E2', 'Deux', 'F', 'Primaire']]);
        $this->postJson('/api/v1/sync', ['operations' => [[
            'id' => (string) Str::uuid(), 'methode' => 'POST', 'chemin' => 'eleves/import', 'school_id' => $this->school->id,
            'corps' => ['__historique_action' => $uuid, '__historique_ecoles' => $ecoles,
                'file' => ['__sync_fichier__' => true, 'nom' => 'lot.xlsx', 'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'contenu_base64' => base64_encode($fichier->get())]],
        ]]])->assertOk()->assertJsonPath('data.resultats.0.statut', 200);
        $this->assertSame(2, Eleve::count());
        $this->postJson('/api/v1/sync', ['operations' => [[
            'id' => (string) Str::uuid(), 'methode' => 'POST', 'chemin' => 'historique-actions/'.$uuid.'/annuler', 'school_id' => $this->school->id,
            'corps' => ['revision' => 0, '__historique_ecoles' => $ecoles],
        ]]])->assertOk()->assertJsonPath('data.resultats.0.statut', 200);
        $this->assertSame(0, Eleve::count());
    }
}
