<?php

namespace Tests\Feature;

use App\Imports\PreinscriptionImport;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\DossierScolarite;
use App\Models\Eleve;
use App\Models\NotificationInterne;
use App\Models\Preinscription;
use App\Models\School;
use App\Models\Tuteur;
use App\Models\User;
use App\Models\Versement;
use App\Services\PreinscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PreinscriptionAdminTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private Tuteur $tuteur;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'parent', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'eleves.manage', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'eleves.view', 'guard_name' => 'web']);

        $this->school = School::create(['name' => 'Elites Tech', 'code' => 'ET', 'type' => 'secondaire', 'is_active' => true]);
        $this->tuteur = Tuteur::create([
            'school_id' => $this->school->id, 'nom_complet' => 'Mballa Jean', 'telephone' => '699000000',
        ]);
    }

    private function admin(): User
    {
        $admin = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $admin->assignRole('super_admin');

        return $admin;
    }

    /** Compte parent branché sur `$this->tuteur`. */
    private function parentUser(): User
    {
        $user = User::create([
            'name' => 'Mballa Jean', 'email' => 'mballa@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $user->assignRole('parent');
        $user->givePermissionTo('eleves.view');
        $this->tuteur->update(['user_id' => $user->id]);

        return $user;
    }

    private function soumettrePreinscriptionNouvel(): Preinscription
    {
        return app(PreinscriptionService::class)->soumettre($this->tuteur, [
            'type' => 'nouveau',
            'school_id' => $this->school->id,
            'donnees_eleve' => ['nom_complet' => 'Mballa Junior', 'sexe' => 'M', 'date_naissance' => '2015-05-01'],
            'donnees_tuteurs' => [['nom_complet' => 'Mballa Jean', 'telephone' => '699000000', 'lien_parente' => 'père', 'is_principal' => true]],
        ]);
    }

    public function test_admin_peut_corriger_puis_valider_une_preinscription(): void
    {
        $preinscription = $this->soumettrePreinscriptionNouvel();
        $admin = $this->admin();

        // Le nom porte une coquille ("Mballa Juniorr") : l'admin la corrige
        // avant de valider, sans avoir à rejeter puis attendre un nouveau dépôt.
        $reponse = $this->actingAs($admin, 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->putJson("/api/v1/preinscriptions/{$preinscription->id}", [
                'donnees_eleve' => ['nom_complet' => 'Mballa Junior', 'sexe' => 'M', 'date_naissance' => '2015-05-02'],
                'donnees_tuteurs' => [['nom_complet' => 'Mballa Jean', 'telephone' => '699000000']],
            ]);

        $reponse->assertOk()->assertJsonPath('data.donnees_eleve.date_naissance', '2015-05-02');

        $validation = $this->actingAs($admin, 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->postJson("/api/v1/preinscriptions/{$preinscription->id}/valider");

        $validation->assertOk();

        $eleve = Eleve::where('nom_complet', 'Mballa Junior')->firstOrFail();
        $this->assertSame('2015-05-02', $eleve->date_naissance->format('Y-m-d'));
    }

    public function test_corriger_une_preinscription_deja_traitee_est_refuse(): void
    {
        $preinscription = $this->soumettrePreinscriptionNouvel();
        app(PreinscriptionService::class)->valider($preinscription);
        $admin = $this->admin();

        $reponse = $this->actingAs($admin, 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->putJson("/api/v1/preinscriptions/{$preinscription->id}", [
                'donnees_eleve' => ['nom_complet' => 'Autre nom', 'sexe' => 'M', 'date_naissance' => '2015-05-02'],
                'donnees_tuteurs' => [['nom_complet' => 'Mballa Jean']],
            ]);

        $reponse->assertStatus(422);
    }

    /** Régression : sans `lien`, la notification créée à la soumission ne menait nulle part côté admin. */
    public function test_la_soumission_notifie_avec_un_lien_exploitable(): void
    {
        $destinataire = User::create([
            'name' => 'Censeur', 'email' => 'censeur@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $destinataire->givePermissionTo('eleves.manage');

        $preinscription = $this->soumettrePreinscriptionNouvel();

        $notification = NotificationInterne::where('user_id', $destinataire->id)->where('type', 'preinscription')->firstOrFail();
        $this->assertSame("/preinscriptions/{$preinscription->id}", $notification->lien);
    }

    /** Régression : un enfant déjà préinscrit ne doit pas pouvoir être préinscrit une seconde fois tant que la demande n'est pas traitée. */
    public function test_le_parent_ne_peut_pas_repreinscrire_un_eleve_deja_en_attente(): void
    {
        $eleve = Eleve::create([
            'school_id' => $this->school->id, 'matricule' => '26SEC1', 'nom_complet' => 'Mballa Aline',
            'sexe' => 'F', 'statut' => 'actif',
        ]);
        $eleve->tuteurs()->attach($this->tuteur->id, ['is_principal' => true]);
        $user = $this->parentUser();

        $donnees = [
            'type' => 'existant',
            'eleve_id' => $eleve->id,
            'donnees_eleve' => ['nom_complet' => 'Mballa Aline', 'sexe' => 'F', 'date_naissance' => '2016-01-01'],
            'donnees_tuteurs' => [['nom_complet' => 'Mballa Jean', 'telephone' => '699000000']],
        ];

        app(PreinscriptionService::class)->soumettre($this->tuteur, $donnees);

        $reponse = $this->actingAs($user, 'sanctum')->postJson('/api/v1/parent/preinscriptions', $donnees);

        $reponse->assertStatus(422);
        $this->assertSame(1, Preinscription::where('eleve_id', $eleve->id)->count());
    }

    /** Même garde-fou pour un enfant pas encore scolarisé, rapproché sur nom + date de naissance faute d'identifiant. */
    public function test_le_parent_ne_peut_pas_redeposer_un_nouvel_enfant_deja_en_attente(): void
    {
        $user = $this->parentUser();

        $donnees = [
            'type' => 'nouveau',
            'school_id' => $this->school->id,
            'donnees_eleve' => ['nom_complet' => 'Mballa Junior', 'sexe' => 'M', 'date_naissance' => '2017-03-04'],
            'donnees_tuteurs' => [['nom_complet' => 'Mballa Jean', 'telephone' => '699000000']],
        ];

        app(PreinscriptionService::class)->soumettre($this->tuteur, $donnees);

        $reponse = $this->actingAs($user, 'sanctum')->postJson('/api/v1/parent/preinscriptions', $donnees);

        $reponse->assertStatus(422);
        $this->assertSame(1, Preinscription::where('tuteur_id', $this->tuteur->id)->count());
    }

    /** Le parent corrige sa propre demande en attente plutôt que d'en redéposer une. */
    public function test_le_parent_peut_modifier_sa_preinscription_en_attente(): void
    {
        $preinscription = $this->soumettrePreinscriptionNouvel();
        $user = $this->parentUser();

        $reponse = $this->actingAs($user, 'sanctum')->putJson("/api/v1/parent/preinscriptions/{$preinscription->id}", [
            'donnees_eleve' => ['nom_complet' => 'Mballa Junior', 'sexe' => 'M', 'date_naissance' => '2015-05-02'],
            'donnees_tuteurs' => [['nom_complet' => 'Mballa Jean', 'telephone' => '699000000']],
            'montant_verser' => 50000,
        ]);

        $reponse->assertOk()->assertJsonPath('data.statut', 'en_attente');
        $this->assertSame('2015-05-02', $preinscription->fresh()->donnees_eleve['date_naissance']);
        $this->assertSame(50000, $preinscription->fresh()->montant_verser);
    }

    /** Un parent ne peut ni voir ni modifier la préinscription d'un autre compte. */
    public function test_un_parent_ne_peut_pas_modifier_la_preinscription_dun_autre(): void
    {
        $preinscription = $this->soumettrePreinscriptionNouvel();

        $autreTuteur = Tuteur::create(['school_id' => $this->school->id, 'nom_complet' => 'Autre Parent', 'telephone' => '699999999']);
        $autreUser = User::create([
            'name' => 'Autre Parent', 'email' => 'autre@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $autreUser->assignRole('parent');
        $autreTuteur->update(['user_id' => $autreUser->id]);

        $reponse = $this->actingAs($autreUser, 'sanctum')->putJson("/api/v1/parent/preinscriptions/{$preinscription->id}", [
            'donnees_eleve' => ['nom_complet' => 'Piraté', 'sexe' => 'M', 'date_naissance' => '2015-05-02'],
            'donnees_tuteurs' => [['nom_complet' => 'Autre Parent']],
        ]);

        $reponse->assertStatus(404);
    }

    /** Les champs statistiques (origine, redoublement, handicap) soumis par le parent atteignent bien l'élève créé. */
    public function test_les_champs_statistiques_soumis_par_le_parent_sont_repris_a_la_validation(): void
    {
        $user = $this->parentUser();
        $classe = Classe::create(['school_id' => $this->school->id, 'nom' => 'CM2']);

        $donnees = [
            'type' => 'nouveau',
            'school_id' => $this->school->id,
            'donnees_eleve' => [
                'nom_complet' => 'Mballa Junior', 'sexe' => 'M', 'date_naissance' => '2017-03-04',
                'classe_id' => $classe->id,
                'nationalite' => 'Camerounaise', 'deplace_interne' => 'Oui', 'bororo' => 'Non', 'baka' => 'Non',
                'region_origine' => 'Extrême-Nord', 'departement_origine' => 'Diamaré',
                'redoublant' => true, 'ecole_precedente' => 'École Publique de Maroua',
                'handicap' => 'Oui', 'type_handicap' => 'Moteur',
            ],
            'donnees_tuteurs' => [['nom_complet' => 'Mballa Jean', 'telephone' => '699000000']],
        ];

        $reponse = $this->actingAs($user, 'sanctum')->postJson('/api/v1/parent/preinscriptions', $donnees);
        $reponse->assertCreated();

        $preinscription = Preinscription::where('tuteur_id', $this->tuteur->id)->firstOrFail();
        app(PreinscriptionService::class)->valider($preinscription);

        $eleve = Eleve::where('nom_complet', 'Mballa Junior')->firstOrFail();
        $this->assertSame('Camerounaise', $eleve->nationalite);
        $this->assertSame('Oui', $eleve->deplace_interne);
        $this->assertSame('Non', $eleve->bororo);
        $this->assertSame('Extrême-Nord', $eleve->region_origine);
        $this->assertSame('Diamaré', $eleve->departement_origine);
        $this->assertTrue($eleve->redoublant);
        $this->assertSame('École Publique de Maroua', $eleve->ecole_precedente);
        $this->assertSame('Oui', $eleve->handicap);
        $this->assertSame('Moteur', $eleve->type_handicap);
    }

    // --------------------------------------------------- Création par l'admin

    /** Réinscription au guichet : l'admin choisit un élève déjà connu, saisie et validation en un seul appel. */
    public function test_admin_cree_et_valide_une_preinscription_pour_un_eleve_existant(): void
    {
        AnneeScolaire::create([
            'school_id' => $this->school->id, 'libelle' => '2026-2027',
            'date_debut' => '2026-09-01', 'date_fin' => '2027-07-15', 'is_active' => true,
        ]);

        $eleve = Eleve::create([
            'school_id' => $this->school->id, 'matricule' => '26SEC2', 'nom_complet' => 'Mballa Aline',
            'sexe' => 'F', 'date_naissance' => '2016-01-01', 'statut' => 'actif',
        ]);
        $eleve->tuteurs()->attach($this->tuteur->id, ['is_principal' => true]);

        $reponse = $this->actingAs($this->admin(), 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->postJson('/api/v1/preinscriptions', [
                'eleve_id' => $eleve->id,
                'donnees_eleve' => [
                    'nom_complet' => 'Mballa Aline', 'sexe' => 'F', 'date_naissance' => '2016-01-01',
                    'adresse' => 'Nouvelle adresse',
                ],
                'donnees_tuteurs' => [['nom_complet' => 'Mballa Jean', 'telephone' => '699000000', 'is_principal' => true]],
            ]);

        $reponse->assertCreated()->assertJsonPath('data.statut', 'validee');

        $preinscription = Preinscription::where('eleve_id', $eleve->id)->firstOrFail();
        $this->assertSame('validee', $preinscription->statut);
        $this->assertNotNull($preinscription->traite_le);
        $this->assertSame('Nouvelle adresse', $eleve->fresh()->adresse);
    }

    /** Un versement annoncé à la réinscription est encaissé immédiatement et le reçu est référencé dans la réponse. */
    public function test_admin_encaisse_immediatement_si_un_montant_est_indique(): void
    {
        AnneeScolaire::create([
            'school_id' => $this->school->id, 'libelle' => '2026-2027',
            'date_debut' => '2026-09-01', 'date_fin' => '2027-07-15', 'is_active' => true,
        ]);

        $eleve = Eleve::create([
            'school_id' => $this->school->id, 'matricule' => '26SEC3', 'nom_complet' => 'Mballa Aline',
            'sexe' => 'F', 'date_naissance' => '2016-01-01', 'statut' => 'actif',
        ]);
        $eleve->tuteurs()->attach($this->tuteur->id, ['is_principal' => true]);

        $reponse = $this->actingAs($this->admin(), 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->postJson('/api/v1/preinscriptions', [
                'eleve_id' => $eleve->id,
                'donnees_eleve' => ['nom_complet' => 'Mballa Aline', 'sexe' => 'F', 'date_naissance' => '2016-01-01'],
                'donnees_tuteurs' => [['nom_complet' => 'Mballa Jean', 'telephone' => '699000000']],
                'montant_verser' => 25000,
                'mode_versement' => 'especes',
            ]);

        $reponse->assertCreated();
        $versementId = $reponse->json('data.versement_id');

        $this->assertNotNull($versementId);
        $this->assertSame(25000, Versement::findOrFail($versementId)->montant);
    }

    /** Sans tuteur au dossier, il n'y a personne à qui rattacher la demande : refusé plutôt que planté sur une contrainte de base. */
    public function test_admin_ne_peut_pas_reinscrire_un_eleve_sans_tuteur(): void
    {
        $eleve = Eleve::create([
            'school_id' => $this->school->id, 'matricule' => '26SEC4', 'nom_complet' => 'Sans Tuteur',
            'sexe' => 'M', 'date_naissance' => '2016-01-01', 'statut' => 'actif',
        ]);

        $reponse = $this->actingAs($this->admin(), 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->postJson('/api/v1/preinscriptions', [
                'eleve_id' => $eleve->id,
                'donnees_eleve' => ['nom_complet' => 'Sans Tuteur', 'sexe' => 'M', 'date_naissance' => '2016-01-01'],
                'donnees_tuteurs' => [['nom_complet' => 'Un parent']],
            ]);

        $reponse->assertStatus(422);
        $this->assertSame(0, Preinscription::where('eleve_id', $eleve->id)->count());
    }

    // ----------------------------------------- Confirmation de présence (année)

    private function anneeActive(): AnneeScolaire
    {
        return AnneeScolaire::create([
            'school_id' => $this->school->id, 'libelle' => '2026-2027',
            'date_debut' => '2026-09-01', 'date_fin' => '2027-07-15', 'is_active' => true,
        ]);
    }

    public function test_la_preinscription_est_stampee_de_lannee_scolaire_active(): void
    {
        $annee = $this->anneeActive();
        $classe = Classe::create(['school_id' => $this->school->id, 'nom' => 'CM2']);
        $user = $this->parentUser();

        $reponse = $this->actingAs($user, 'sanctum')->postJson('/api/v1/parent/preinscriptions', [
            'type' => 'nouveau',
            'school_id' => $this->school->id,
            'donnees_eleve' => ['nom_complet' => 'Mballa Junior', 'sexe' => 'M', 'date_naissance' => '2017-03-04', 'classe_id' => $classe->id],
            'donnees_tuteurs' => [['nom_complet' => 'Mballa Jean', 'telephone' => '699000000']],
        ]);
        $reponse->assertCreated();

        $preinscription = Preinscription::where('tuteur_id', $this->tuteur->id)->firstOrFail();
        $this->assertSame($annee->id, $preinscription->annee_scolaire_id);
    }

    public function test_valider_un_ancien_eleve_convertit_le_solde_impaye_en_frais_annexe(): void
    {
        $anneePrecedente = AnneeScolaire::create([
            'school_id' => $this->school->id, 'libelle' => '2025-2026',
            'date_debut' => '2025-09-01', 'date_fin' => '2026-07-15', 'is_active' => false,
        ]);
        $annee = $this->anneeActive();

        $eleve = Eleve::create([
            'school_id' => $this->school->id, 'matricule' => '26SEC9', 'nom_complet' => 'Mballa Aline',
            'sexe' => 'F', 'date_naissance' => '2016-01-01', 'statut' => 'actif',
        ]);
        $eleve->tuteurs()->attach($this->tuteur->id, ['is_principal' => true]);

        // Dossier de l'an dernier, jamais réglé : 50 000 restent dus.
        DossierScolarite::create([
            'school_id' => $this->school->id, 'annee_scolaire_id' => $anneePrecedente->id, 'eleve_id' => $eleve->id,
            'montant_scolarite' => 50000, 'remise' => 0, 'report_dette' => 0,
        ]);

        $admin = $this->admin();
        app(PreinscriptionService::class)->creerEtValiderParAdmin($eleve, [
            'donnees_eleve' => ['nom_complet' => 'Mballa Aline', 'sexe' => 'F', 'date_naissance' => '2016-01-01'],
            'donnees_tuteurs' => [],
        ], $admin->id);

        $dossierAnneeActive = DossierScolarite::where('eleve_id', $eleve->id)->where('annee_scolaire_id', $annee->id)->firstOrFail();
        $this->assertSame(0, $dossierAnneeActive->report_dette);
        $ligneDette = $dossierAnneeActive->fraisAnnexes()->where('libelle', 'Dette antérieure')->first();
        $this->assertNotNull($ligneDette);
        $this->assertSame(50000, $ligneDette->montant);
    }

    public function test_liste_anciens_non_reinscrits_exclut_les_deja_reinscrits(): void
    {
        $annee = $this->anneeActive();

        $reinscrit = Eleve::create([
            'school_id' => $this->school->id, 'matricule' => '26SECA', 'nom_complet' => 'Déjà réinscrit',
            'sexe' => 'M', 'date_naissance' => '2016-01-01', 'statut' => 'actif',
        ]);
        $reinscrit->forceFill(['created_at' => '2025-01-01'])->saveQuietly();
        $reinscrit->tuteurs()->attach($this->tuteur->id, ['is_principal' => true]);
        $nonReinscrit = Eleve::create([
            'school_id' => $this->school->id, 'matricule' => '26SECB', 'nom_complet' => 'Pas encore réinscrit',
            'sexe' => 'M', 'date_naissance' => '2016-01-01', 'statut' => 'actif',
        ]);
        $nonReinscrit->forceFill(['created_at' => '2025-01-01'])->saveQuietly();

        app(PreinscriptionService::class)->creerEtValiderParAdmin($reinscrit, [
            'donnees_eleve' => ['nom_complet' => 'Déjà réinscrit', 'sexe' => 'M', 'date_naissance' => '2016-01-01'],
            'donnees_tuteurs' => [],
        ], $this->admin()->id);

        $nonReinscrits = app(PreinscriptionService::class)->listeAnciensNonReinscrits($this->school->id);

        $this->assertTrue($nonReinscrits->contains('id', $nonReinscrit->id));
        $this->assertFalse($nonReinscrits->contains('id', $reinscrit->id));
    }

    // --------------------------------------------------------- Import massif

    public function test_import_xlsx_traite_ancien_et_nouvel_eleve_et_rapporte_les_erreurs(): void
    {
        Excel::fake();
        $this->anneeActive();
        $classe = Classe::create(['school_id' => $this->school->id, 'nom' => 'CM2']);

        $ancien = Eleve::create([
            'school_id' => $this->school->id, 'matricule' => 'IMP1', 'nom_complet' => 'Ancien Un',
            'sexe' => 'M', 'date_naissance' => '2015-01-01', 'statut' => 'actif',
        ]);
        $ancien->tuteurs()->attach($this->tuteur->id, ['is_principal' => true]);

        $import = new PreinscriptionImport($this->school->id, app(PreinscriptionService::class), $this->admin()->id);

        // Ligne 1 : ancien élève rapproché par matricule (IDEleves). Ligne 2 :
        // nouvel élève avec un contact père. Ligne 3 : ni matricule ni date de
        // naissance exploitable, doit remonter en erreur sans empêcher les
        // deux autres lignes de s'importer. Clés déjà en forme « slug » —
        // celle que produit réellement l'en-tête normalisé de maatwebsite
        // (« IDEleves » → « ideleves »), ce test appelant collection()
        // directement sans passer par la lecture du fichier.
        $import->collection(collect([
            collect(['ideleves' => 'IMP1', 'nom_eleves' => 'Ancien Un', 'nom_classe' => 'CM2']),
            collect(['nom_eleves' => 'Nouvel Eleve', 'sexe_eleves' => 'M', 'ddn_eleves' => '2018-01-01', 'nom_classe' => 'CM2', 'nom_parents' => 'Un Tuteur', 'tel_pere' => '698000000']),
            collect(['ideleves' => 'INCONNU', 'nom_eleves' => 'Fantome']),
        ]));

        $this->assertSame(2, $import->importees);
        $this->assertCount(1, $import->erreurs);
        // +1 pour l'en-tête (absent de ce tableau construit à la main), +1 pour repasser en base 1 : la 3e ligne de données est la ligne 4 d'un vrai fichier.
        $this->assertSame(4, $import->erreurs[0]['ligne']);

        $this->assertSame($classe->id, $ancien->fresh()->classe_id);
        $this->assertNotNull(Eleve::where('nom_complet', 'Nouvel Eleve')->first());
    }

    public function test_endpoint_import_preinscriptions_accepte_un_fichier(): void
    {
        $this->anneeActive();
        Classe::create(['school_id' => $this->school->id, 'nom' => 'CM2']);

        $fichier = UploadedFile::fake()->createWithContent(
            'import.csv',
            "nom_eleves,sexe_eleves,ddn_eleves,Nom_classe,nom_parents,tel_pere\nNouvel CSV,M,2018-01-01,CM2,Tuteur CSV,698111111\n",
        );

        $reponse = $this->actingAs($this->admin(), 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->post('/api/v1/preinscriptions/import', ['file' => $fichier]);

        $reponse->assertOk();
        $this->assertSame(1, $reponse->json('data.imported'));
        $this->assertNotNull(Eleve::where('nom_complet', 'Nouvel CSV')->first());
    }

    /** Le cœur de la demande : jamais une colonne « ancien/nouveau », toujours une comparaison — ici sans aucun matricule. */
    public function test_import_rapproche_un_ancien_eleve_sans_matricule_par_nom_et_date_naissance(): void
    {
        $this->anneeActive();
        $classe = Classe::create(['school_id' => $this->school->id, 'nom' => 'CM2']);
        $eleve = Eleve::create([
            'school_id' => $this->school->id, 'matricule' => 'SANSID', 'nom_complet' => 'Nkomo Alice',
            'sexe' => 'F', 'date_naissance' => '2014-05-02', 'statut' => 'actif',
        ]);
        $eleve->tuteurs()->attach($this->tuteur->id, ['is_principal' => true]);

        $import = new PreinscriptionImport($this->school->id, app(PreinscriptionService::class), $this->admin()->id);
        $import->collection(collect([
            // Pas de colonne IDEleves du tout : seule la comparaison nom + date de naissance rapproche cette ligne de l'élève existant.
            collect(['nom_eleves' => 'Nkomo Alice', 'ddn_eleves' => '2014-05-02', 'nom_classe' => 'CM2']),
        ]));

        $this->assertSame(1, $import->importees);
        $this->assertCount(0, $import->erreurs);
        $this->assertSame(1, Eleve::where('nom_complet', 'Nkomo Alice')->count());
        $this->assertSame($classe->id, $eleve->fresh()->classe_id);
    }

    /** La dette déclarée dans le fichier de situation (frais - réglé - remise) doit ressortir en ligne « Dette antérieure », pas en report_dette. */
    public function test_import_reprend_la_dette_du_fichier_de_situation_en_frais_annexe(): void
    {
        $this->anneeActive();
        Classe::create(['school_id' => $this->school->id, 'nom' => 'CM2']);
        $eleve = Eleve::create([
            'school_id' => $this->school->id, 'matricule' => 'DET1', 'nom_complet' => 'Ancien Endette',
            'sexe' => 'M', 'date_naissance' => '2014-01-01', 'statut' => 'actif',
        ]);
        $eleve->tuteurs()->attach($this->tuteur->id, ['is_principal' => true]);

        $import = new PreinscriptionImport($this->school->id, app(PreinscriptionService::class), $this->admin()->id);
        $import->collection(collect([
            collect([
                'ideleves' => 'DET1', 'nom_eleves' => 'Ancien Endette', 'nom_classe' => 'CM2',
                'frais_scolarite' => '90000', 'montant_scolarite' => '30000', 'remise_scol' => '10000',
                'annee_scol' => '2025-2026',
            ]),
        ]));

        $this->assertSame(1, $import->importees);

        $dossier = DossierScolarite::where('eleve_id', $eleve->id)->firstOrFail();
        $this->assertSame(0, $dossier->report_dette);
        $ligneDette = $dossier->fraisAnnexes()->where('libelle', 'Dette antérieure')->first();
        $this->assertNotNull($ligneDette);
        $this->assertSame(50000, $ligneDette->montant); // 90000 - 30000 - 10000
    }
}
