<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\EleveTuteur;
use App\Models\Niveau;
use App\Models\School;
use App\Models\SyncTombstone;
use App\Models\Tuteur;
use App\Models\User;
use App\Support\CataloguePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Retrait du lien parent-enfant depuis l'écran des comptes parents : le
 * parent ne voit plus l'enfant, mais un élève ne reste jamais sans tuteur, et
 * les appareils hors ligne apprennent la suppression.
 */
class TuteurDetacherEnfantTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private Eleve $eleve;

    private Tuteur $pere;

    private Tuteur $mere;

    private User $secretariat;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }

        $this->school = School::create(['name' => 'Elites Secondaire', 'code' => 'ES', 'type' => 'secondaire', 'is_active' => true]);
        $annee = AnneeScolaire::create([
            'school_id' => $this->school->id, 'libelle' => '2026-2027',
            'date_debut' => '2026-09-01', 'date_fin' => '2027-06-30', 'is_active' => true,
        ]);
        $niveau = Niveau::create(['code' => '6E', 'name_fr' => '6ème', 'name_en' => 'Form 1', 'school_id' => $this->school->id]);
        $classe = Classe::create([
            'school_id' => $this->school->id, 'niveau_id' => $niveau->id,
            'annee_scolaire_id' => $annee->id, 'nom' => '6ème A',
        ]);
        $this->eleve = Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $classe->id,
            'nom_complet' => 'Lina Essomba', 'sexe' => 'F', 'statut' => 'actif',
        ]);

        $this->pere = Tuteur::create(['school_id' => $this->school->id, 'nom_complet' => 'Paul Essomba', 'telephone' => '690000001']);
        $this->mere = Tuteur::create(['school_id' => $this->school->id, 'nom_complet' => 'Rose Essomba', 'telephone' => '690000002']);
        $this->pere->eleves()->attach($this->eleve->id, ['is_principal' => true]);

        $this->secretariat = User::create([
            'name' => 'Secrétariat', 'email' => 'secretariat@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $this->secretariat->givePermissionTo(['tuteurs.view', 'tuteurs.update']);
    }

    private function detacher(Tuteur $tuteur, ?User $user = null)
    {
        return $this->actingAs($user ?? $this->secretariat, 'sanctum')
            ->deleteJson("/api/v1/tuteurs/{$tuteur->id}/enfants/{$this->eleve->id}");
    }

    public function test_le_seul_tuteur_d_un_eleve_ne_peut_pas_etre_detache(): void
    {
        $this->detacher($this->pere)->assertStatus(422);

        $this->assertTrue($this->pere->eleves()->whereKey($this->eleve->id)->exists());
    }

    public function test_detacher_retire_le_lien_et_transmet_le_role_de_principal(): void
    {
        $this->mere->eleves()->attach($this->eleve->id, ['is_principal' => false]);
        $lienPere = EleveTuteur::where('tuteur_id', $this->pere->id)->first();

        $this->detacher($this->pere)->assertOk();

        $this->assertFalse($this->pere->eleves()->whereKey($this->eleve->id)->exists());
        $this->assertTrue(
            (bool) EleveTuteur::where('tuteur_id', $this->mere->id)->value('is_principal'),
            "La mère devient tutrice principale à la place du père.",
        );
        // Les fiches sont conservées.
        $this->assertNotNull(Tuteur::find($this->pere->id));
        $this->assertNotNull(Eleve::find($this->eleve->id));
        // Pierre tombale pour la synchronisation hors ligne.
        $this->assertTrue(
            SyncTombstone::where('entite', 'eleve_tuteurs')->where('entite_id', $lienPere->id)->exists(),
        );
    }

    public function test_un_enfant_non_rattache_est_refuse(): void
    {
        $this->detacher($this->mere)->assertStatus(422);
    }

    public function test_la_route_exige_le_droit_de_modifier_les_tuteurs(): void
    {
        $this->mere->eleves()->attach($this->eleve->id, ['is_principal' => false]);
        $lecteur = User::create([
            'name' => 'Lecteur', 'email' => 'lecteur@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $lecteur->givePermissionTo('tuteurs.view');

        $this->detacher($this->pere, $lecteur)->assertForbidden();
    }
}
