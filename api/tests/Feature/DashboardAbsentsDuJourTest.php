<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\Eleve;
use App\Models\Matiere;
use App\Models\Presence;
use App\Models\School;
use App\Models\Seance;
use App\Models\User;
use App\Support\CataloguePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Compteur d'absents du tableau de bord : deux chiffres, parce qu'ils ne
 * disent pas la même chose — le volume d'absences de la journée, et les
 * élèves qui n'ont pas paru de la journée.
 */
class DashboardAbsentsDuJourTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $admin;

    private Classe $classe;

    /** @var array<string, ClasseMatiere> */
    private array $affectations = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->school = School::create(['name' => 'Elites Test', 'code' => 'EAB', 'type' => 'secondaire', 'is_active' => true]);
        AnneeScolaire::create([
            'school_id' => $this->school->id, 'libelle' => '2026-2027',
            'date_debut' => '2026-09-01', 'date_fin' => '2027-07-15', 'is_active' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $this->admin->assignRole('super_admin');

        $this->classe = Classe::create(['school_id' => $this->school->id, 'nom' => '6e A']);

        foreach (['Maths' => '08:00', 'SVT' => '10:00'] as $nom => $heure) {
            $matiere = Matiere::create(['school_id' => $this->school->id, 'nom' => $nom, 'statut' => 'actif']);
            $this->affectations[$nom] = ClasseMatiere::create([
                'classe_id' => $this->classe->id, 'matiere_id' => $matiere->id, 'statut' => 'actif',
            ]);
        }
    }

    private function eleve(string $nom, string $matricule): Eleve
    {
        return Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $this->classe->id, 'matricule' => $matricule,
            'nom_complet' => $nom, 'sexe' => 'F', 'statut' => 'actif',
        ]);
    }

    private function seance(string $matiere, string $heure, string $date, string $statut = 'effectuee'): Seance
    {
        return Seance::create([
            'school_id' => $this->school->id, 'classe_id' => $this->classe->id,
            'classe_matiere_id' => $this->affectations[$matiere]->id,
            'date_seance' => $date, 'heure_debut' => $heure, 'heure_fin' => '09:00', 'statut' => $statut,
        ]);
    }

    private function pointer(Seance $seance, Eleve $eleve, string $statut, ?string $motif = null): void
    {
        Presence::create([
            'seance_id' => $seance->id, 'eleve_id' => $eleve->id,
            'statut' => $statut, 'motif' => $motif,
        ]);
    }

    private function absents(?string $date = null)
    {
        return $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/dashboard/absents-du-jour'.($date ? "?date={$date}" : ''));
    }

    public function test_distingue_les_absences_du_jour_des_journees_entieres(): void
    {
        $aujourdhui = now()->toDateString();
        $maths = $this->seance('Maths', '08:00', $aujourdhui);
        $svt = $this->seance('SVT', '10:00', $aujourdhui);

        $toujoursLa = $this->eleve('ATANGANA Grace', 'A1');
        $uneHeure = $this->eleve('BELLA Ines', 'A2');
        $touteLaJournee = $this->eleve('NGONO Alice', 'A3');

        foreach ([$maths, $svt] as $seance) {
            $this->pointer($seance, $toujoursLa, 'present');
            $this->pointer($seance, $touteLaJournee, 'absent', 'maladie');
        }
        $this->pointer($maths, $uneHeure, 'present');
        $this->pointer($svt, $uneHeure, 'absent', 'inconnu');

        $reponse = $this->absents()->assertOk();

        $reponse->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.journee_complete', 1)
            // L'absence de la journée entière vient en tête.
            ->assertJsonPath('data.eleves.0.nom_complet', 'NGONO Alice')
            ->assertJsonPath('data.eleves.0.journee_complete', true)
            ->assertJsonPath('data.eleves.0.absences', 2)
            ->assertJsonPath('data.eleves.1.nom_complet', 'BELLA Ines')
            ->assertJsonPath('data.eleves.1.journee_complete', false)
            ->assertJsonPath('data.eleves.1.absences', 1)
            ->assertJsonPath('data.eleves.1.creneaux.0.heure', '10:00')
            ->assertJsonPath('data.eleves.1.creneaux.0.matiere', 'SVT');
    }

    /** Un retard reste une présence : l'élève était en cours. */
    public function test_un_retard_ne_compte_pas_comme_une_absence(): void
    {
        $maths = $this->seance('Maths', '08:00', now()->toDateString());
        $this->pointer($maths, $this->eleve('MBALLA Eve', 'A4'), 'retard');

        $this->absents()->assertOk()->assertJsonPath('data.total', 0);
    }

    /** Une séance pas encore déclarée n'a pas eu d'appel : personne n'y est absent. */
    public function test_une_seance_non_declaree_ne_produit_pas_d_absents(): void
    {
        $prevue = $this->seance('Maths', '08:00', now()->toDateString(), 'prevue');
        $this->pointer($prevue, $this->eleve('OWONA Sara', 'A5'), 'absent');

        $this->absents()->assertOk()->assertJsonPath('data.total', 0);
    }

    public function test_le_calendrier_permet_de_consulter_un_autre_jour(): void
    {
        $hier = now()->subDay()->toDateString();
        $seance = $this->seance('Maths', '08:00', $hier);
        $this->pointer($seance, $this->eleve('KAMGA Lea', 'A6'), 'absent');

        $this->absents()->assertOk()->assertJsonPath('data.total', 0);

        $this->absents($hier)->assertOk()
            ->assertJsonPath('data.date', $hier)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.eleves.0.nom_complet', 'KAMGA Lea');
    }

    public function test_une_date_mal_formee_est_refusee(): void
    {
        $this->absents('31-12-2026')->assertStatus(422);
    }
}
