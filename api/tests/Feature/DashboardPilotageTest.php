<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\EmploiDuTemps;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\ProgressionItem;
use App\Models\Seance;
use App\Models\School;
use App\Models\User;
use App\Support\CataloguePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Le pilotage temps réel du tableau de bord (cours en cours, classes sans
 * enseignant, couverture du programme) parcourt l'emploi du temps du jour et
 * tout le programme de l'établissement : ce test fige l'heure pour vérifier
 * que le créneau fabriqué tombe bien dans « en cours », et que les manques
 * (matière sans enseignant, classe sans titulaire) et le taux de couverture
 * sont correctement agrégés sur l'ensemble du périmètre (mode agrégé, comme
 * pour l'attestation employeur).
 */
class DashboardPilotageTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_pilotage_expose_les_creneaux_du_jour_les_manques_et_la_couverture(): void
    {
        // Lundi 08:30 : le créneau fabriqué (08:00-09:00) doit tomber « en cours ».
        Carbon::setTestNow(Carbon::parse('2026-08-24 08:30:00')); // un lundi

        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $ecoleSecondaire = School::create(['name' => 'Elites Secondaire', 'code' => 'ES', 'type' => 'secondaire', 'is_active' => true]);
        $ecolePrimaire = School::create(['name' => 'Elites Primaire', 'code' => 'EP', 'type' => 'primaire', 'is_active' => true]);

        $superAdmin = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $ecoleSecondaire->id, 'is_active' => true,
        ]);
        $superAdmin->assignRole('super_admin');

        $annee = AnneeScolaire::create([
            'school_id' => $ecoleSecondaire->id, 'libelle' => '2025-2026',
            'date_debut' => '2025-09-01', 'date_fin' => '2026-06-30', 'is_active' => true,
        ]);
        $niveau = Niveau::create(['code' => '6E', 'name_fr' => '6ème', 'name_en' => 'Form 1', 'school_id' => $ecoleSecondaire->id]);

        $classeSecondaire = Classe::create([
            'school_id' => $ecoleSecondaire->id, 'niveau_id' => $niveau->id,
            'annee_scolaire_id' => $annee->id, 'nom' => '6ème A',
        ]);

        $matiere = Matiere::create(['school_id' => $ecoleSecondaire->id, 'nom' => 'Mathématiques', 'statut' => 'actif']);

        // Matière affectée à la classe mais sans enseignant : doit remonter
        // dans « classes sans enseignant » et dans le créneau « en cours ».
        $classeMatiere = ClasseMatiere::create([
            'classe_id' => $classeSecondaire->id, 'matiere_id' => $matiere->id,
            'personnel_id' => null, 'statut' => 'actif',
        ]);

        EmploiDuTemps::create([
            'school_id' => $ecoleSecondaire->id, 'classe_id' => $classeSecondaire->id,
            'classe_matiere_id' => $classeMatiere->id, 'jour' => 1,
            'heure_debut' => '08:00:00', 'heure_fin' => '09:00:00', 'salle' => 'Salle 1',
        ]);

        // Un second créneau, annulé pour aujourd'hui : ne doit apparaître
        // dans aucune des trois listes.
        $creneauAnnule = EmploiDuTemps::create([
            'school_id' => $ecoleSecondaire->id, 'classe_id' => $classeSecondaire->id,
            'classe_matiere_id' => $classeMatiere->id, 'jour' => 1,
            'heure_debut' => '07:00:00', 'heure_fin' => '07:30:00', 'salle' => 'Salle 1',
        ]);
        Seance::create([
            'school_id' => $ecoleSecondaire->id, 'classe_id' => $classeSecondaire->id,
            'classe_matiere_id' => $classeMatiere->id, 'emploi_du_temps_id' => $creneauAnnule->id,
            'date_seance' => '2026-08-24', 'heure_debut' => '07:00:00', 'heure_fin' => '07:30:00',
            'statut' => 'annulee',
        ]);

        // Programme : une leçon traitée (rattachée à une séance), une autre
        // non traitée — le taux de couverture doit tomber à 50 %.
        $lecon1 = ProgressionItem::create([
            'classe_matiere_id' => $classeMatiere->id, 'type' => 'lecon', 'titre' => 'Fractions', 'ordre' => 1,
        ]);
        ProgressionItem::create([
            'classe_matiere_id' => $classeMatiere->id, 'type' => 'lecon', 'titre' => 'Décimaux', 'ordre' => 2,
        ]);
        $seanceCouverte = Seance::create([
            'school_id' => $ecoleSecondaire->id, 'classe_id' => $classeSecondaire->id,
            'classe_matiere_id' => $classeMatiere->id, 'date_seance' => '2026-08-20',
            'heure_debut' => '08:00:00', 'heure_fin' => '09:00:00', 'statut' => 'effectuee',
        ]);
        $seanceCouverte->lecons()->attach($lecon1->id);

        // École primaire : une classe sans titulaire, doit remonter aussi.
        $niveauPrimaire = Niveau::create(['code' => 'CP', 'name_fr' => 'CP', 'name_en' => 'CP', 'school_id' => $ecolePrimaire->id]);
        $anneePrimaire = AnneeScolaire::create([
            'school_id' => $ecolePrimaire->id, 'libelle' => '2025-2026',
            'date_debut' => '2025-09-01', 'date_fin' => '2026-06-30', 'is_active' => true,
        ]);
        Classe::create([
            'school_id' => $ecolePrimaire->id, 'niveau_id' => $niveauPrimaire->id,
            'annee_scolaire_id' => $anneePrimaire->id, 'nom' => 'CP', 'titulaire_id' => null,
        ]);

        $reponse = $this->actingAs($superAdmin, 'sanctum')
            ->getJson('/api/v1/dashboard/pilotage')
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $reponse['cours_en_cours']);
        $this->assertSame('6ème A', $reponse['cours_en_cours'][0]['classe']);
        $this->assertSame('Mathématiques', $reponse['cours_en_cours'][0]['matiere']);
        $this->assertNull($reponse['cours_en_cours'][0]['enseignant']);

        $this->assertCount(0, $reponse['cours_a_venir']);
        $this->assertCount(0, $reponse['appels_en_retard']);

        $this->assertCount(2, $reponse['classes_sans_enseignant']);
        $libelles = array_map(fn ($c) => $c['classe'], $reponse['classes_sans_enseignant']);
        $this->assertContains('6ème A', $libelles);
        $this->assertContains('CP', $libelles);

        $this->assertSame(2, $reponse['couverture']['lecons']);
        $this->assertSame(1, $reponse['couverture']['traitees']);
        $this->assertEquals(50.0, $reponse['couverture']['taux']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
