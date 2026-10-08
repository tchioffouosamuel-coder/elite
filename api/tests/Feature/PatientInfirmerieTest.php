<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\Eleve;
use App\Models\FonctionReferentiel;
use App\Models\Personnel;
use App\Models\School;
use App\Models\Tuteur;
use App\Models\User;
use App\Models\VisiteInfirmerie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PatientInfirmerieTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private Eleve $patient;

    private User $infirmier;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['infirmerie.view', 'infirmerie.create'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $this->school = School::create(['name' => 'Elites Test', 'code' => 'ET', 'type' => 'primaire', 'is_active' => true]);
        $classe = Classe::create(['school_id' => $this->school->id, 'nom' => 'CLASS 5-B']);
        $this->patient = Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $classe->id,
            'nom_complet' => 'Alice Ngono', 'matricule' => 'ET001', 'sexe' => 'F', 'statut' => 'actif',
            'groupe_sanguin' => 'O+', 'allergies' => 'Penicilline', 'situation_sanitaire' => 'Asthme',
        ]);
        $this->infirmier = User::factory()->create(['school_id' => $this->school->id]);
        $this->infirmier->givePermissionTo(['infirmerie.view', 'infirmerie.create']);
    }

    public function test_le_dossier_contient_la_fiche_sanitaire_les_contacts_et_uniquement_les_visites_du_patient(): void
    {
        $tuteur = Tuteur::create(['school_id' => $this->school->id, 'nom_complet' => 'Parent Alice', 'telephone' => '699000001']);
        $this->patient->tuteurs()->attach($tuteur->id, ['is_principal' => true, 'lien_parente' => 'Mere']);
        $ancienne = $this->visite($this->patient, '2026-09-01 10:00:00');
        $recente = $this->visite($this->patient, '2026-10-08 10:00:00');
        $autrePatient = Eleve::create([
            'school_id' => $this->school->id, 'nom_complet' => 'Autre patient', 'sexe' => 'M', 'statut' => 'actif',
        ]);
        $this->visite($autrePatient, '2026-10-08 11:00:00');

        $this->actingAs($this->infirmier, 'sanctum')
            ->getJson("/api/v1/infirmerie/patients/{$this->patient->id}")
            ->assertOk()
            ->assertJsonPath('data.patient.nom_complet', 'Alice Ngono')
            ->assertJsonPath('data.patient.allergies', 'Penicilline')
            ->assertJsonPath('data.patient.groupe_sanguin', 'O+')
            ->assertJsonPath('data.patient.classe.nom', 'CLASS 5-B')
            ->assertJsonPath('data.patient.tuteurs.0.telephone', '699000001')
            ->assertJsonPath('data.patient.tuteurs.0.is_principal', true)
            ->assertJsonCount(2, 'data.visites')
            ->assertJsonPath('data.visites.0.id', $recente->id)
            ->assertJsonPath('data.visites.1.id', $ancienne->id)
            ->assertJsonPath('data.visites.0.cout_total', 700);
    }

    public function test_un_patient_sans_visite_a_un_historique_vide(): void
    {
        $this->actingAs($this->infirmier, 'sanctum')
            ->getJson("/api/v1/infirmerie/patients/{$this->patient->id}")
            ->assertOk()
            ->assertJsonCount(0, 'data.visites');
    }

    public function test_un_patient_dune_autre_ecole_reste_inaccessible(): void
    {
        $autreEcole = School::create(['name' => 'Autre ecole', 'code' => 'AE', 'type' => 'primaire', 'is_active' => true]);
        $this->patient->update(['school_id' => $autreEcole->id]);
        $this->actingAs($this->infirmier, 'sanctum')
            ->getJson("/api/v1/infirmerie/patients/{$this->patient->id}")
            ->assertNotFound();
    }

    public function test_le_dossier_exige_la_permission_de_consulter_linfirmerie(): void
    {
        $user = User::factory()->create(['school_id' => $this->school->id]);
        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/infirmerie/patients/{$this->patient->id}")
            ->assertForbidden();
    }

    public function test_la_lecture_seule_reste_limitee_aux_eleves_des_classes_confiees(): void
    {
        $fonction = FonctionReferentiel::create(['school_id' => $this->school->id, 'label_fr' => 'Enseignant']);
        $enseignant = User::factory()->create(['school_id' => $this->school->id]);
        $enseignant->givePermissionTo('infirmerie.view');
        $personnel = Personnel::create([
            'school_id' => $this->school->id, 'user_id' => $enseignant->id,
            'fonction_id' => $fonction->id, 'nom_complet' => 'Enseignant test', 'sexe' => 'M', 'statut' => 'actif',
        ]);
        $this->patient->classe->update(['titulaire_id' => $personnel->id]);
        $autreClasse = Classe::create(['school_id' => $this->school->id, 'nom' => 'CLASS 6-A']);
        $autrePatient = Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $autreClasse->id,
            'nom_complet' => 'Autre patient', 'sexe' => 'M', 'statut' => 'actif',
        ]);

        $this->actingAs($enseignant, 'sanctum')
            ->getJson("/api/v1/infirmerie/patients/{$this->patient->id}")
            ->assertOk();
        $this->getJson("/api/v1/infirmerie/patients/{$autrePatient->id}")
            ->assertNotFound();
    }

    private function visite(Eleve $patient, string $date): VisiteInfirmerie
    {
        return VisiteInfirmerie::create([
            'eleve_id' => $patient->id, 'classe_id' => $patient->classe_id, 'date_visite' => $date,
            'raison' => 'Maux de tete', 'soins_prodiges' => 'Repos', 'type_traitement' => 'interne',
            'cout_soins' => 500, 'cout_materiels' => 200, 'cout_total' => 700, 'observations' => 'Parent informe',
        ]);
    }
}
