<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\FonctionReferentiel;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\Personnel;
use App\Models\School;
use App\Models\Seance;
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
                ->select(array_values(array_unique([...$definition['colonnes'], 'updated_at'])));

            ($definition['portee'])($requete, $user->school_id ?? 1);

            try {
                $requete->limit(1)->get();
            } catch (\Throwable $e) {
                $this->fail("Entité « {$cle} » : {$e->getMessage()}");
            }
        }

        $this->assertTrue(true);
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
        $fonction->synchroniserPermissions(RolePermissionSeeder::ROLE_PERMISSIONS['enseignant']);

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
}
