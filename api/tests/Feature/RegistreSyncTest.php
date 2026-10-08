<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\Eleve;
use App\Models\EmploiDuTemps;
use App\Models\AuditLog;
use App\Models\Banque;
use App\Models\BanqueMouvement;
use App\Models\AnneeScolaire;
use App\Models\CalendrierScolaire;
use App\Models\FonctionReferentiel;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\Personnel;
use App\Models\RegleValidationSeance;
use App\Models\Sequence;
use App\Models\School;
use App\Models\Seance;
use App\Models\Setting;
use App\Models\Trimestre;
use App\Models\User;
use App\Support\CataloguePermissions;
use App\Support\Sync\RegistreSync;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Garde-fou structurel : chaque entité du registre doit interroger un
 * modèle existant, avec des colonnes et des relations réellement présentes
 * en base — sans quoi `SyncController::pull()` échouerait en silence
 * (ou en 500) au premier appel réel sur cette entité.
 *
 * Ne teste pas le contenu métier des filtres (couvert par `DesktopSyncTest`
 * et les tests de périmètre existants) : seulement que chaque définition du
 * catalogue est exécutable de bout en bout.
 */
class RegistreSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_chaque_entite_du_registre_est_interrogeable(): void
    {
        $user = User::factory()->create();

        foreach (RegistreSync::entites($user) as $cle => $definition) {
            $requete = $definition['modele']::query()
                ->select(array_values(array_unique([
                    ...$definition['colonnes'],
                    $definition['horodatage'] ?? 'updated_at',
                ])));

            ($definition['portee'])($requete, $user->school_id ?? 1);

            try {
                $requete->limit(1)->get();
            } catch (\Throwable $e) {
                $this->fail("Entité « {$cle} » : {$e->getMessage()}");
            }
        }

        $this->assertTrue(true);
    }

    public function test_sync_renvoie_le_profil_de_lecole_et_ses_parametres(): void
    {
        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $school = School::create([
            'name' => 'École distante',
            'code' => 'ED',
            'type' => 'secondaire',
            'national_school_code' => 'NS-42',
            'logo_path' => 'ecoles/1/logo.png',
            'address' => 'Adresse de test',
            'is_active' => true,
        ]);
        $fonction = FonctionReferentiel::create([
            'school_id' => $school->id,
            'label_fr' => 'Enseignant',
            'label_en' => 'Teacher',
        ]);
        $fonction->synchroniserPermissions(['eleves.view', 'notes.view']);
        $annee = AnneeScolaire::create([
            'school_id' => $school->id,
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-06-30',
            'is_active' => true,
        ]);
        CalendrierScolaire::create([
            'annee_scolaire_id' => $annee->id,
            'date' => '2026-12-25',
            'est_ouvert' => false,
            'motif' => 'Fête de Noël',
        ]);
        $trimestre = Trimestre::create([
            'annee_scolaire_id' => $annee->id,
            'libelle' => 'Trimestre 1',
            'ordre' => 1,
            'date_debut' => '2026-09-01',
            'date_fin' => '2026-12-31',
            'is_active' => true,
        ]);
        Sequence::create([
            'trimestre_id' => $trimestre->id,
            'ordre' => 1,
            'libelle' => 'Séquence 1',
            'saisie_ouverte' => false,
        ]);
        Setting::set($school->id, 'num_sequences', 3);
        $user = User::factory()->create(['school_id' => $school->id]);
        AuditLog::create([
            'created_at' => now()->subMinute(),
            'school_id' => $school->id,
            'user_nom' => 'Super administrateur',
            'action' => 'consultation',
            'methode' => 'GET',
            'url' => '/api/v1/eleves',
            'statut_http' => 200,
        ]);
        ActivityLog::create([
            'created_at' => now()->subMinute(),
            'school_id' => $school->id,
            'user_id' => $user->id,
            'causer_nom' => 'Super administrateur',
            'causer_role' => 'Super administrateur',
            'action' => 'connexion',
            'description' => 'Connexion à l’application.',
        ]);
        $banque = Banque::create([
            'nom' => 'Banque de test',
            'code' => 'BT',
            'numero_compte_ecole' => '001234',
            'solde' => 125000,
        ]);
        BanqueMouvement::create([
            'banque_id' => $banque->id,
            'type' => 'depot',
            'montant' => 25000,
            'date_mouvement' => '2026-10-01',
            'libelle' => 'Dépôt de test',
        ]);
        RegleValidationSeance::create([
            'school_id' => $school->id,
            'methode_validation' => 'code',
            'delai_valeur' => 2,
            'delai_unite' => 'jours',
        ]);

        $user->assignRole('super_admin');

        $reponse = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/sync?entites=schools,settings,audit_logs,activity_logs,banques,banque_mouvements,regles_validation_seances,annee_scolaires,calendrier_scolaires,trimestres,sequences,fonction_referentiel')
            ->assertOk();

        $this->assertSame('École distante', $reponse->json('data.donnees.schools.0.name'));
        $this->assertSame('ecoles/1/logo.png', $reponse->json('data.donnees.schools.0.logo_path'));
        $this->assertSame('num_sequences', $reponse->json('data.donnees.settings.0.key'));
        $this->assertSame('3', $reponse->json('data.donnees.settings.0.value'));
        $this->assertSame('/api/v1/eleves', $reponse->json('data.donnees.audit_logs.0.url'));
        $this->assertSame('Connexion à l’application.', $reponse->json('data.donnees.activity_logs.0.description'));
        $this->assertSame('Banque de test', $reponse->json('data.donnees.banques.0.nom'));
        $this->assertSame(125000, $reponse->json('data.donnees.banques.0.solde'));
        $this->assertSame('Dépôt de test', $reponse->json('data.donnees.banque_mouvements.0.libelle'));
        $this->assertSame('code', $reponse->json('data.donnees.regles_validation_seances.0.methode_validation'));
        $this->assertSame('2026-2027', $reponse->json('data.donnees.annee_scolaires.0.libelle'));
        $this->assertSame('Fête de Noël', $reponse->json('data.donnees.calendrier_scolaires.0.motif'));
        $this->assertFalse($reponse->json('data.donnees.sequences.0.saisie_ouverte'));
        $this->assertEqualsCanonicalizing(
            ['eleves.view', 'notes.view'],
            $reponse->json('data.donnees.fonction_referentiel.0.permissions'),
        );
    }

    public function test_changer_les_privileges_dune_fonction_actualise_son_horodatage_sync(): void
    {
        $school = School::create(['name' => 'X', 'code' => 'X', 'type' => 'secondaire', 'is_active' => true]);
        $fonction = FonctionReferentiel::create([
            'school_id' => $school->id,
            'label_fr' => 'Enseignant',
            'label_en' => 'Teacher',
        ]);
        \DB::table('fonction_referentiel')->where('id', $fonction->id)->update([
            'updated_at' => now()->subDays(2),
        ]);
        $fonction->refresh();

        $fonction->synchroniserPermissions(['eleves.view']);

        $this->assertTrue($fonction->fresh()->updated_at->greaterThan(now()->subMinute()));
    }

    /** Chaque modèle du registre existe réellement et sait dire sous quelle école ranger sa pierre tombale (ou explicitement aucune). */
    public function test_ecole_de_ne_plante_sur_aucune_entite(): void
    {
        foreach (RegistreSync::cles() as $cle) {
            $modele = RegistreSync::entites()[$cle]['modele'];
            $instance = new $modele();

            try {
                RegistreSync::ecoleDe($cle, $instance);
            } catch (\Throwable $e) {
                $this->fail("ecoleDe(« {$cle} ») : {$e->getMessage()}");
            }
        }

        $this->assertTrue(true);
    }

    /**
     * Les pauses et activités de l'emploi du temps n'ont pas de matière :
     * sans `type`/`libelle` dans la synchronisation, le mobile ne pouvait ni
     * les afficher ni les distinguer d'un cours incomplet.
     */
    public function test_une_pause_part_a_la_synchronisation_avec_son_type_et_son_libelle(): void
    {
        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $school = School::create(['name' => 'X', 'code' => 'X', 'type' => 'secondaire', 'is_active' => true]);
        $niveau = Niveau::create(['code' => 'college', 'name_fr' => 'Collège', 'name_en' => 'College', 'ordre' => 1]);
        $classe = Classe::create(['school_id' => $school->id, 'niveau_id' => $niveau->id, 'nom' => '6ème A']);

        EmploiDuTemps::create([
            'school_id' => $school->id, 'classe_id' => $classe->id, 'classe_matiere_id' => null,
            'type' => 'pause', 'libelle' => 'Récréation',
            'jour' => 1, 'heure_debut' => '10:00:00', 'heure_fin' => '10:30:00',
        ]);

        $user = User::factory()->create(['school_id' => $school->id]);
        $user->assignRole('super_admin');

        $reponse = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/sync?entites=emplois_du_temps')
            ->assertOk();

        $this->assertSame('pause', $reponse->json('data.donnees.emplois_du_temps.0.type'));
        $this->assertSame('Récréation', $reponse->json('data.donnees.emplois_du_temps.0.libelle'));
    }

    /**
     * Non-régression : une colonne castée `date` (`date_seance`) ne doit
     * jamais dériver d'un jour selon le fuseau du serveur une fois passée
     * par `/sync`. `config('app.timezone')` vaut `Africa/Douala` (UTC+1) :
     * un `Carbon` calé à minuit local pour ce jour-là se sérialise par
     * défaut en JSON en UTC (`CarbonInterface::jsonSerialize()`), soit 23h
     * la VEILLE — le mobile, qui tronque la chaîne reçue à 10 caractères,
     * stockait alors la mauvaise date (observé : les cours du lundi
     * ressortaient datés du dimanche sur l'écran Absences).
     */
    public function test_date_seance_ne_derive_pas_dun_jour_avec_le_fuseau_local(): void
    {
        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $school = School::create(['name' => 'X', 'code' => 'X', 'type' => 'secondaire', 'is_active' => true]);
        $niveau = Niveau::create(['code' => 'college', 'name_fr' => 'Collège', 'name_en' => 'College', 'ordre' => 1]);
        $classe = Classe::create(['school_id' => $school->id, 'niveau_id' => $niveau->id, 'nom' => '6ème A']);
        $matiere = Matiere::create(['school_id' => $school->id, 'nom' => 'Mathématiques']);
        $classeMatiere = ClasseMatiere::create(['classe_id' => $classe->id, 'matiere_id' => $matiere->id, 'statut' => 'actif']);

        Seance::create([
            'school_id' => $school->id, 'classe_id' => $classe->id, 'classe_matiere_id' => $classeMatiere->id,
            'date_seance' => '2026-09-07', 'heure_debut' => '08:00', 'heure_fin' => '10:00', 'statut' => 'prevue',
        ]);

        $user = User::factory()->create(['school_id' => $school->id]);
        $user->assignRole('super_admin');

        $reponse = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/sync?entites=seances')
            ->assertOk();

        $this->assertSame('2026-09-07', $reponse->json('data.donnees.seances.0.date_seance'));
    }

    /**
     * Non-régression : `GET /classes` en ligne filtre déjà par périmètre
     * (`ClasseRepository::forSchool()` → `Classe::scopeDansPerimetre()`).
     * L'entité `classes` du registre doit appliquer exactement le même
     * filtre, sinon un compte borné réplique localement des classes hors de
     * son périmètre — visibles dans la liste, mais dont `seances`/`notes`
     * resteront vides puisque ces entités-là sont, elles, bien bornées.
     */
    public function test_lentite_classes_est_bornee_au_perimetre_comme_seances(): void
    {
        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $school = School::create(['name' => 'X', 'code' => 'X', 'type' => 'secondaire', 'is_active' => true]);
        $niveau = Niveau::create(['code' => 'college', 'name_fr' => 'Collège', 'name_en' => 'Secondary']);

        $classeEnseignee = Classe::create(['school_id' => $school->id, 'niveau_id' => $niveau->id, 'nom' => '6e A']);
        $classeHorsPerimetre = Classe::create(['school_id' => $school->id, 'niveau_id' => $niveau->id, 'nom' => '6e B']);

        $fonction = FonctionReferentiel::firstOrCreate([
            'school_id' => $school->id,
            'label_fr' => 'Enseignant',
        ]);
        $fonction->synchroniserPermissions(RolePermissionSeeder::permissionsDuRole('enseignant'));

        $user = User::create([
            'name' => 'Enseignant', 'email' => 'prof.registre@test.local', 'password' => 'password',
            'school_id' => $school->id, 'is_active' => true,
        ]);
        Personnel::create([
            'school_id' => $school->id, 'user_id' => $user->id, 'fonction_id' => $fonction->id,
            'nom_complet' => 'Enseignant de test', 'sexe' => 'M', 'statut' => 'actif',
        ]);
        $user = $user->fresh();

        $matiere = Matiere::create(['school_id' => $school->id, 'nom' => 'Mathématiques', 'statut' => 'actif']);
        ClasseMatiere::create([
            'classe_id' => $classeEnseignee->id, 'matiere_id' => $matiere->id,
            'personnel_id' => $user->personnel->id, 'statut' => 'actif',
        ]);

        $definition = RegistreSync::entites($user)['classes'];
        $requete = $definition['modele']::query()->select('id');
        ($definition['portee'])($requete, $school->id);
        $ids = $requete->pluck('id')->all();

        $this->assertContains($classeEnseignee->id, $ids);
        $this->assertNotContains($classeHorsPerimetre->id, $ids);
    }

    /** Les écrans élèves ont besoin du référentiel classes dès que le compte peut consulter les élèves. */
    public function test_lentite_classes_est_synchronisee_avec_eleves_view(): void
    {
        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }

        $school = School::create(['name' => 'Maternelle', 'code' => 'MAT', 'type' => 'maternelle', 'is_active' => true]);
        $classe = Classe::create(['school_id' => $school->id, 'nom' => 'Petite section']);
        Eleve::create([
            'school_id' => $school->id,
            'classe_id' => $classe->id,
            'matricule' => 'MAT-001',
            'nom_complet' => 'Élève Maternelle',
            'statut' => 'actif',
        ]);

        $fonction = FonctionReferentiel::create([
            'school_id' => $school->id,
            'label_fr' => 'Secrétaire',
            'label_en' => 'Secretary',
        ]);
        $fonction->synchroniserPermissions(['eleves.view']);

        $user = User::create([
            'name' => 'Secrétaire',
            'email' => 'secretaire.registre@test.local',
            'password' => 'password',
            'school_id' => $school->id,
            'is_active' => true,
        ]);
        Personnel::create([
            'school_id' => $school->id,
            'user_id' => $user->id,
            'fonction_id' => $fonction->id,
            'nom_complet' => 'Secrétaire de test',
            'sexe' => 'F',
            'statut' => 'actif',
        ]);

        $reponse = $this->actingAs($user->fresh(), 'sanctum')
            ->getJson('/api/v1/sync?entites=classes,eleves')
            ->assertOk();

        $this->assertSame($classe->id, $reponse->json('data.donnees.classes.0.id'));
        $this->assertSame('Petite section', $reponse->json('data.donnees.classes.0.nom'));
        $this->assertSame($classe->id, $reponse->json('data.donnees.eleves.0.classe_id'));
    }
}
