<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\BusAffectation;
use App\Models\BusArret;
use App\Models\BusTrajet;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\Eleve;
use App\Models\FonctionReferentiel;
use App\Models\Matiere;
use App\Models\Personnel;
use App\Models\Preinscription;
use App\Models\School;
use App\Models\VisiteInfirmerie;
use App\Models\User;
use App\Support\CataloguePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Sur la fiche d'un élève, l'enseignant consulte sa situation financière et
 * son transport en lecture seule (`eleves.situation`) — pour les seuls élèves
 * de ses classes, et sans accéder pour autant à la caisse. Son tableau de
 * bord détaille aussi ses indicateurs pédagogiques matière par matière.
 */
class EnseignantFicheEleveTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $prof;

    private Eleve $eleveAMoi;

    private Eleve $elevePasAMoi;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->school = School::create([
            'name' => 'Elites College', 'code' => 'EBTC', 'type' => 'secondaire', 'is_active' => true,
        ]);
        AnneeScolaire::create([
            'school_id' => $this->school->id, 'libelle' => '2025-2026',
            'date_debut' => '2025-09-01', 'date_fin' => '2026-07-31', 'is_active' => true,
        ]);

        $fonction = FonctionReferentiel::firstOrCreate(
            ['school_id' => $this->school->id, 'label_fr' => 'Enseignant'],
        );
        $fonction->synchroniserPermissions(['eleves.view', 'dashboard.view', 'eleves.situation']);

        $this->prof = User::create([
            'name' => 'Prof', 'email' => 'prof@test.local',
            'password' => 'password', 'school_id' => $this->school->id, 'is_active' => true,
        ]);
        Personnel::create([
            'school_id' => $this->school->id, 'user_id' => $this->prof->id, 'fonction_id' => $fonction->id,
            'nom_complet' => 'Prof', 'sexe' => 'M', 'statut' => 'actif',
        ]);
        $this->prof = $this->prof->fresh();

        $classeAMoi = Classe::create(['school_id' => $this->school->id, 'nom' => '6ème A']);
        $classePasAMoi = Classe::create(['school_id' => $this->school->id, 'nom' => '6ème B']);
        $matiere = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Mathématiques']);
        ClasseMatiere::create([
            'classe_id' => $classeAMoi->id, 'matiere_id' => $matiere->id,
            'personnel_id' => $this->prof->personnel->id, 'coefficient' => 1, 'statut' => 'actif',
        ]);

        $this->eleveAMoi = Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $classeAMoi->id,
            'matricule' => 'A1', 'nom_complet' => 'ELEVE A', 'sexe' => 'M', 'statut' => 'actif',
        ]);
        $this->elevePasAMoi = Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $classePasAMoi->id,
            'matricule' => 'B1', 'nom_complet' => 'ELEVE B', 'sexe' => 'F', 'statut' => 'actif',
        ]);
    }

    public function test_l_enseignant_voit_la_situation_financiere_et_le_transport_de_ses_eleves(): void
    {
        $this->actingAs($this->prof, 'sanctum')
            ->getJson("/api/v1/eleves/{$this->eleveAMoi->id}/scolarite")
            ->assertOk();

        $this->actingAs($this->prof, 'sanctum')
            ->getJson("/api/v1/eleves/{$this->eleveAMoi->id}/transport")
            ->assertOk()
            ->assertJsonPath('data.id', $this->eleveAMoi->id);
    }

    public function test_l_enseignant_ne_voit_pas_les_eleves_hors_de_ses_classes(): void
    {
        $this->actingAs($this->prof, 'sanctum')
            ->getJson("/api/v1/eleves/{$this->elevePasAMoi->id}/scolarite")
            ->assertNotFound();

        $this->actingAs($this->prof, 'sanctum')
            ->getJson("/api/v1/eleves/{$this->elevePasAMoi->id}/transport")
            ->assertNotFound();
    }

    public function test_la_situation_de_l_eleve_n_ouvre_pas_la_caisse(): void
    {
        $this->actingAs($this->prof, 'sanctum')
            ->getJson('/api/v1/finance/insolvables')
            ->assertForbidden();

        $this->actingAs($this->prof, 'sanctum')
            ->getJson('/api/v1/bus/eleves')
            ->assertForbidden();
    }

    public function test_l_enseignant_voit_les_eleves_transportes_de_ses_classes_sans_montant(): void
    {
        $annee = AnneeScolaire::where('school_id', $this->school->id)->firstOrFail();
        $trajet = BusTrajet::create(['school_id' => $this->school->id, 'nom' => 'Ligne Nord']);
        $arret = BusArret::create(['trajet_id' => $trajet->id, 'nom' => 'Carrefour', 'ordre' => 1]);
        foreach ([$this->eleveAMoi, $this->elevePasAMoi] as $eleve) {
            BusAffectation::create([
                'eleve_id' => $eleve->id, 'trajet_id' => $trajet->id, 'arret_id' => $arret->id,
                'annee_scolaire_id' => $annee->id, 'tarif_mensuel' => 10000, 'statut' => 'actif',
            ]);
        }

        $reponse = $this->actingAs($this->prof, 'sanctum')->getJson('/api/v1/enseignant/bus');

        $reponse->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->eleveAMoi->id)
            ->assertJsonPath('data.0.trajet', 'Ligne Nord')
            ->assertJsonPath('data.0.arret', 'Carrefour');
        $this->assertSame(['id', 'matricule', 'nom_complet', 'classe', 'trajet', 'arret'], array_keys($reponse->json('data.0')));
    }

    public function test_la_liste_des_insolvables_de_l_enseignant_ne_porte_aucun_montant(): void
    {
        $reponse = $this->actingAs($this->prof, 'sanctum')->getJson('/api/v1/enseignant/insolvables');

        $reponse->assertOk();
        foreach ($reponse->json('data') as $ligne) {
            $this->assertSame(['id', 'matricule', 'nom_complet', 'classe'], array_keys($ligne));
        }
    }

    public function test_hors_infirmier_l_infirmerie_se_limite_aux_eleves_de_ses_classes_en_lecture(): void
    {
        FonctionReferentiel::where('school_id', $this->school->id)->where('label_fr', 'Enseignant')->firstOrFail()->synchroniserPermissions(['eleves.view', 'dashboard.view', 'eleves.situation', 'infirmerie.view']);
        $prof = $this->prof->fresh();
        foreach ([$this->eleveAMoi, $this->elevePasAMoi] as $eleve) {
            VisiteInfirmerie::create([
                'eleve_id' => $eleve->id, 'classe_id' => $eleve->classe_id,
                'date_visite' => now(), 'raison' => 'Maux de tête', 'soins_prodiges' => 'Repos',
            ]);
        }

        $this->actingAs($prof, 'sanctum')
            ->getJson('/api/v1/infirmerie/visites')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($prof, 'sanctum')
            ->getJson('/api/v1/infirmerie/visites/export')
            ->assertForbidden();

        $this->actingAs($prof, 'sanctum')
            ->postJson('/api/v1/infirmerie/visites', ['eleve_id' => $this->eleveAMoi->id, 'raison' => 'Test'])
            ->assertForbidden();
    }

    public function test_le_tableau_de_bord_enseignant_ne_compte_que_les_preinscrits_valides(): void
    {
        $annee = AnneeScolaire::where('school_id', $this->school->id)->firstOrFail();
        $classe = $this->eleveAMoi->classe_id;
        $valide = Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $classe,
            'matricule' => 'A2', 'nom_complet' => 'ELEVE VALIDE', 'sexe' => 'F', 'statut' => 'actif',
        ]);
        $enAttente = Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $classe,
            'matricule' => 'A3', 'nom_complet' => 'ELEVE EN ATTENTE', 'sexe' => 'F', 'statut' => 'actif',
        ]);
        foreach ([[$valide, 'validee'], [$enAttente, 'en_attente']] as [$eleve, $statut]) {
            Preinscription::create([
                'school_id' => $this->school->id, 'annee_scolaire_id' => $annee->id, 'eleve_id' => $eleve->id,
                'type' => 'existant', 'statut' => $statut, 'donnees_eleve' => [], 'donnees_tuteurs' => [],
            ]);
        }

        // eleveAMoi (sans préinscription) et l'élève en attente ne comptent pas.
        $this->actingAs($this->prof, 'sanctum')
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.scope', 'classe')
            ->assertJsonPath('data.effectifs.eleves', 1)
            ->assertJsonPath('data.repartition_genre.filles', 1)
            ->assertJsonPath('data.repartition_genre.garcons', 0);
    }

    public function test_le_detail_des_indicateurs_pedagogiques_liste_les_matieres_de_l_enseignant(): void
    {
        $this->actingAs($this->prof, 'sanctum')
            ->getJson('/api/v1/dashboard/indicateurs-pedagogiques')
            ->assertOk()
            ->assertJsonCount(1, 'data.matieres')
            ->assertJsonPath('data.matieres.0.classe', '6ème A')
            ->assertJsonPath('data.matieres.0.matiere', 'Mathématiques')
            ->assertJsonPath('data.matieres.0.taux_progression', 0);
    }
}
