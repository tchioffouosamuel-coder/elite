<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\BusAffectation;
use App\Models\BusArret;
use App\Models\BusTrajet;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BusListePersonnaliseeTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private AnneeScolaire $annee;

    private BusTrajet $trajet;

    private BusArret $arret;

    private User $admin;

    private Classe $classeA;

    private Classe $classeB;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->school = School::create(['name' => 'Elites Tech', 'code' => 'EBT', 'type' => 'secondaire', 'is_active' => true]);

        $this->annee = AnneeScolaire::create([
            'school_id' => $this->school->id,
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-07-31',
            'is_active' => true,
        ]);

        $this->trajet = BusTrajet::create([
            'school_id' => $this->school->id,
            'nom' => 'Ligne Nord',
            'tarif_aller_retour' => 15000,
        ]);

        $this->arret = BusArret::create([
            'trajet_id' => $this->trajet->id,
            'nom' => 'Carrefour',
            'ordre' => 1,
        ]);

        $this->classeA = Classe::create(['school_id' => $this->school->id, 'nom' => 'CM2']);
        $this->classeB = Classe::create(['school_id' => $this->school->id, 'nom' => 'CE1']);

        $this->admin = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $this->admin->assignRole('super_admin');
    }

    private function eleve(string $matricule, Classe $classe, string $nomComplet): Eleve
    {
        return Eleve::create([
            'school_id' => $this->school->id,
            'classe_id' => $classe->id,
            'matricule' => $matricule,
            'nom_complet' => $nomComplet,
            'sexe' => 'M',
            'statut' => 'actif',
        ]);
    }

    private function affecter(Eleve $eleve, string $option = 'aller_retour', ?BusArret $arret = null): BusAffectation
    {
        return BusAffectation::create([
            'eleve_id' => $eleve->id,
            'trajet_id' => $this->trajet->id,
            'arret_id' => $arret?->id,
            'annee_scolaire_id' => $this->annee->id,
            'tarif_mensuel' => 15000,
            'option_trajet' => $option,
            'statut' => 'actif',
        ]);
    }

    public function test_filtre_par_classe(): void
    {
        $this->affecter($this->eleve('A1', $this->classeA, 'Diallo Awa'));
        $this->affecter($this->eleve('A2', $this->classeB, 'Kone Moussa'));

        $reponse = $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->getJson('/api/v1/bus/affectations/liste-personnalisee?classe_id=' . $this->classeA->id);

        $reponse->assertOk()->assertJsonPath('data.total', 1);
        $this->assertSame('Diallo Awa', $reponse->json('data.resultats.0.eleve.nom_complet'));
    }

    public function test_filtre_par_nom_de_famille(): void
    {
        $this->affecter($this->eleve('A1', $this->classeA, 'Diallo Awa'));
        $this->affecter($this->eleve('A2', $this->classeA, 'Diallo Ibrahim'));
        $this->affecter($this->eleve('A3', $this->classeB, 'Kone Moussa'));

        $reponse = $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->getJson('/api/v1/bus/affectations/liste-personnalisee?nom=Diallo');

        $reponse->assertOk()->assertJsonPath('data.total', 2);
    }

    public function test_filtre_par_sens_et_destination(): void
    {
        $this->affecter($this->eleve('A1', $this->classeA, 'Diallo Awa'), 'aller_simple', $this->arret);
        $this->affecter($this->eleve('A2', $this->classeA, 'Kone Moussa'), 'aller_retour');

        $reponse = $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->getJson('/api/v1/bus/affectations/liste-personnalisee?option_trajet=aller_simple&arret_id=' . $this->arret->id);

        $reponse->assertOk()->assertJsonPath('data.total', 1);
        $this->assertSame('Diallo Awa', $reponse->json('data.resultats.0.eleve.nom_complet'));
    }

    public function test_regroupe_par_classe(): void
    {
        $this->affecter($this->eleve('A1', $this->classeA, 'Diallo Awa'));
        $this->affecter($this->eleve('A2', $this->classeA, 'Kone Moussa'));
        $this->affecter($this->eleve('A3', $this->classeB, 'Traore Fatou'));

        $reponse = $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->getJson('/api/v1/bus/affectations/liste-personnalisee?group_by=classe');

        $reponse->assertOk()->assertJsonPath('data.group_by', 'classe');
        $resultats = $reponse->json('data.resultats');
        $this->assertCount(2, $resultats['CM2']);
        $this->assertCount(1, $resultats['CE1']);
    }

    public function test_export_pdf(): void
    {
        $this->affecter($this->eleve('A1', $this->classeA, 'Diallo Awa'));

        $reponse = $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->get('/api/v1/bus/affectations/liste-personnalisee/pdf?classe_id=' . $this->classeA->id);

        $reponse->assertOk();
        $this->assertSame('application/pdf', $reponse->headers->get('Content-Type'));
    }
}
