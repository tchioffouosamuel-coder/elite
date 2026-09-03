<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\ArchiveClasseAnnee;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\ConseilClasse;
use App\Models\Eleve;
use App\Models\HistoriqueScolariteEleve;
use App\Models\Matiere;
use App\Models\Note;
use App\Models\School;
use App\Models\Sequence;
use App\Models\Trimestre;
use App\Models\User;
use App\Services\ConseilClasseService;
use App\Support\CataloguePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Conseil de classe de fin d'année : décisions par défaut (moyenne annuelle
 * vs seuil), ajustements (exclusion, grâce, seuil motivé), validation
 * (mutations sur les élèves + historique + archivage).
 */
class ConseilClasseTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private Classe $classe;

    private Classe $destination;

    private AnneeScolaire $annee;

    private Sequence $sequence;

    private ClasseMatiere $affectation;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->school = School::create(['name' => 'Elites Test', 'code' => 'ET', 'type' => 'secondaire', 'is_active' => true]);

        $this->annee = AnneeScolaire::create([
            'school_id' => $this->school->id, 'libelle' => '2025-2026',
            'date_debut' => '2025-09-01', 'date_fin' => '2026-07-31', 'is_active' => true,
        ]);

        $trimestre = Trimestre::create([
            'annee_scolaire_id' => $this->annee->id, 'libelle' => 'Trimestre 1', 'ordre' => 1,
            'date_debut' => '2025-09-01', 'date_fin' => '2025-12-15', 'is_active' => true,
        ]);
        $sequence = Sequence::create(['trimestre_id' => $trimestre->id, 'libelle' => 'Séquence 1', 'ordre' => 1]);

        $this->classe = Classe::create(['school_id' => $this->school->id, 'nom' => '3ème A']);
        $this->destination = Classe::create(['school_id' => $this->school->id, 'nom' => '2nde A']);

        $matiere = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Mathématiques']);
        $this->affectation = ClasseMatiere::create(['classe_id' => $this->classe->id, 'matiere_id' => $matiere->id, 'coefficient' => 1]);

        $this->admin = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $this->admin->assignRole('super_admin');

        $this->sequence = $sequence;
    }

    private function eleve(string $nom, ?float $note): Eleve
    {
        $eleve = Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $this->classe->id,
            'nom_complet' => $nom, 'sexe' => 'M', 'statut' => 'actif',
        ]);

        if ($note !== null) {
            Note::create([
                'eleve_id' => $eleve->id, 'classe_matiere_id' => $this->affectation->id,
                'sequence_id' => $this->sequence->id, 'composante' => 'unique', 'valeur' => $note,
            ]);
        }

        return $eleve;
    }

    private function service(): ConseilClasseService
    {
        return app(ConseilClasseService::class);
    }

    public function test_prepare_calcule_les_decisions_par_defaut_selon_le_seuil(): void
    {
        $admis = $this->eleve('Admis Par Defaut', 15.0);
        $redouble = $this->eleve('Redouble Par Defaut', 5.0);

        $conseil = $this->service()->preparer($this->classe, $this->annee);

        $this->assertSame('admis', $conseil->decisions->firstWhere('eleve_id', $admis->id)->decision_defaut);
        $this->assertSame('redouble', $conseil->decisions->firstWhere('eleve_id', $redouble->id)->decision_defaut);
    }

    public function test_prepare_est_idempotent(): void
    {
        $this->eleve('Un Eleve', 12.0);

        $conseil1 = $this->service()->preparer($this->classe, $this->annee);
        $conseil2 = $this->service()->preparer($this->classe, $this->annee);

        $this->assertSame($conseil1->id, $conseil2->id);
        $this->assertSame(1, ConseilClasse::count());
    }

    public function test_definir_seuil_sans_motif_echoue_si_different_du_defaut(): void
    {
        $conseil = $this->service()->preparer($this->classe, $this->annee);

        $this->expectException(RuntimeException::class);
        $this->service()->definirSeuil($conseil, 16.0, null);
    }

    public function test_definir_seuil_avec_motif_recalcule_les_decisions_non_ajustees(): void
    {
        $eleve = $this->eleve('Eleve 15', 15.0);
        $conseil = $this->service()->preparer($this->classe, $this->annee);

        $conseil = $this->service()->definirSeuil($conseil, 16.0, 'Programme renforcé cette année.');

        $decision = $conseil->decisions->firstWhere('eleve_id', $eleve->id);
        $this->assertSame('redouble', $decision->decision_finale);
        $this->assertSame(16.0, (float) $conseil->seuil_moyenne);
    }

    public function test_definir_seuil_necrase_pas_une_exclusion_deja_posee(): void
    {
        $eleve = $this->eleve('Eleve Exclu', 15.0);
        $conseil = $this->service()->preparer($this->classe, $this->annee);
        $decision = $conseil->decisions->firstWhere('eleve_id', $eleve->id);

        $this->service()->exclure($decision, 'Fraude aux examens.');
        $conseil = $this->service()->definirSeuil($conseil->fresh(), 5.0, 'Motif quelconque.');

        $this->assertSame('exclu', $conseil->decisions->firstWhere('eleve_id', $eleve->id)->decision_finale);
    }

    public function test_gracier_echoue_si_admis_par_defaut(): void
    {
        $eleve = $this->eleve('Deja Admis', 15.0);
        $conseil = $this->service()->preparer($this->classe, $this->annee);
        $decision = $conseil->decisions->firstWhere('eleve_id', $eleve->id);

        $this->expectException(RuntimeException::class);
        $this->service()->gracier($decision, 'Motif quelconque.');
    }

    public function test_gracier_un_redoublant_par_defaut(): void
    {
        $eleve = $this->eleve('A Gracier', 5.0);
        $conseil = $this->service()->preparer($this->classe, $this->annee);
        $decision = $conseil->decisions->firstWhere('eleve_id', $eleve->id);

        $decision = $this->service()->gracier($decision, 'Progrès notable en fin d\'année, situation familiale difficile.');

        $this->assertSame('admis', $decision->decision_finale);
        $this->assertTrue($decision->gracie);
    }

    public function test_annuler_ajustement_revient_au_defaut(): void
    {
        $eleve = $this->eleve('Ajustement Annule', 15.0);
        $conseil = $this->service()->preparer($this->classe, $this->annee);
        $decision = $conseil->decisions->firstWhere('eleve_id', $eleve->id);

        $decision = $this->service()->exclure($decision, 'Motif provisoire.');
        $decision = $this->service()->annulerAjustement($decision->fresh());

        $this->assertSame('admis', $decision->decision_finale);
        $this->assertFalse($decision->gracie);
        $this->assertNull($decision->motif);
    }

    public function test_valider_applique_toutes_les_decisions(): void
    {
        $admis = $this->eleve('Sera Admis', 15.0);
        $redouble = $this->eleve('Va Redoubler', 5.0);
        $exclu = $this->eleve('Sera Exclu', 15.0);
        $gracie = $this->eleve('Sera Gracie', 5.0);

        $conseil = $this->service()->preparer($this->classe, $this->annee);
        $conseil = $this->service()->definirClasseDestination($conseil, $this->destination->id);

        $decisions = $conseil->decisions;
        $this->service()->exclure($decisions->firstWhere('eleve_id', $exclu->id), 'Comportement grave.');
        $this->service()->gracier($decisions->firstWhere('eleve_id', $gracie->id), 'Cas exceptionnel.');

        $conseil = $this->service()->valider($conseil->fresh(), $this->admin);

        $this->assertSame('valide', $conseil->statut);

        $this->assertSame($this->destination->id, $admis->fresh()->classe_id);
        $this->assertFalse((bool) $admis->fresh()->redoublant);
        $this->assertSame('actif', $admis->fresh()->statut);

        $this->assertSame($this->classe->id, $redouble->fresh()->classe_id);
        $this->assertTrue((bool) $redouble->fresh()->redoublant);

        $this->assertSame('exclu', $exclu->fresh()->statut);
        $this->assertSame($this->classe->id, $exclu->fresh()->classe_id);

        $this->assertSame($this->destination->id, $gracie->fresh()->classe_id);
        $this->assertFalse((bool) $gracie->fresh()->redoublant);

        // Historique de parcours : une ligne par élève, décision + moyenne conservées.
        $this->assertSame(4, HistoriqueScolariteEleve::where('annee_scolaire_id', $this->annee->id)->count());
        $histoGracie = HistoriqueScolariteEleve::where('eleve_id', $gracie->id)->first();
        $this->assertSame('admis', $histoGracie->decision);
        $this->assertTrue($histoGracie->gracie);

        // Archive de la classe.
        $archive = ArchiveClasseAnnee::where('annee_scolaire_id', $this->annee->id)->where('classe_id', $this->classe->id)->first();
        $this->assertNotNull($archive);
        $this->assertSame(4, $archive->effectif);
    }

    public function test_valider_diplome_sans_classe_destination(): void
    {
        $admis = $this->eleve('Termine Le Cycle', 15.0);
        $conseil = $this->service()->preparer($this->classe, $this->annee);
        // Pas de classe_destination_id : fin de cycle.

        $this->service()->valider($conseil->fresh(), $this->admin);

        $admis->refresh();
        $this->assertSame('diplome', $admis->statut);
        $this->assertNull($admis->classe_id);

        $histo = HistoriqueScolariteEleve::where('eleve_id', $admis->id)->first();
        $this->assertSame('diplome', $histo->decision);
    }

    public function test_conseil_valide_est_immuable(): void
    {
        $this->eleve('Un Eleve', 15.0);
        $conseil = $this->service()->preparer($this->classe, $this->annee);
        $conseil = $this->service()->valider($conseil, $this->admin);

        $this->expectException(RuntimeException::class);
        $this->service()->definirSeuil($conseil, 5.0, 'Trop tard.');
    }

    public function test_valider_exige_un_motif_pour_toute_exclusion_ou_grace(): void
    {
        $eleve = $this->eleve('Sans Motif', 5.0);
        $conseil = $this->service()->preparer($this->classe, $this->annee);
        $decision = $conseil->decisions->firstWhere('eleve_id', $eleve->id);

        // Contourne le service pour simuler une décision incohérente (exclue sans motif) :
        // le garde-fou de valider() doit la rattraper même si exclure() l'empêche en usage normal.
        $decision->update(['decision_finale' => 'exclu', 'motif' => null]);

        $this->expectException(RuntimeException::class);
        $this->service()->valider($conseil->fresh(), $this->admin);
    }

    // --------------------------------------------------------------- HTTP

    public function test_route_definir_seuil_sans_motif_renvoie_422(): void
    {
        $this->eleve('Un Eleve', 15.0);
        $conseil = $this->service()->preparer($this->classe, $this->annee);

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/conseils-classe/{$conseil->id}/seuil", ['seuil_moyenne' => 18])
            ->assertStatus(422);
    }

    public function test_route_valider_applique_les_mutations(): void
    {
        $admis = $this->eleve('Http Admis', 15.0);
        $conseil = $this->service()->preparer($this->classe, $this->annee);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/conseils-classe/{$conseil->id}/destination", ['classe_destination_id' => $this->destination->id])
            ->assertOk();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/conseils-classe/{$conseil->id}/valider")
            ->assertOk()
            ->assertJsonPath('data.statut', 'valide');

        $this->assertSame($this->destination->id, $admis->fresh()->classe_id);
    }

    public function test_route_pv_renvoie_un_pdf(): void
    {
        $this->eleve('Un Eleve', 15.0);
        $conseil = $this->service()->preparer($this->classe, $this->annee);
        $conseil = $this->service()->valider($conseil, $this->admin);

        $reponse = $this->actingAs($this->admin, 'sanctum')
            ->get("/api/v1/conseils-classe/{$conseil->id}/pv")
            ->assertOk();

        $this->assertStringStartsWith('%PDF', $reponse->getContent());
    }

    public function test_un_compte_sans_permission_est_refuse(): void
    {
        $employe = User::create([
            'name' => 'Employe', 'email' => 'employe@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);

        $this->actingAs($employe, 'sanctum')
            ->getJson("/api/v1/classes/{$this->classe->id}/conseil")
            ->assertForbidden();
    }
}
