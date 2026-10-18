<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\DetteAnterieure;
use App\Models\Eleve;
use App\Models\GrilleFrais;
use App\Models\School;
use App\Services\ScolariteService;
use Database\Seeders\PlanComptableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Demande du prof (caisse) : corriger un reçu ou une dette antérieure
 * directement depuis la caisse, sans annulation manuelle suivie d'une
 * nouvelle saisie séparée.
 */
class CaisseCorrectionsTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private AnneeScolaire $annee;

    private Classe $classe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanComptableSeeder::class);

        $this->school = School::create(['name' => 'Elites Tech', 'code' => 'EBT', 'type' => 'secondaire', 'is_active' => true]);
        $this->annee = AnneeScolaire::create([
            'school_id' => $this->school->id, 'libelle' => '2026-2027',
            'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31', 'is_active' => true,
        ]);
        $this->classe = Classe::create(['school_id' => $this->school->id, 'nom' => 'ACCOUNTING 1-A']);
        GrilleFrais::create([
            'school_id' => $this->school->id, 'annee_scolaire_id' => $this->annee->id,
            'classe_id' => $this->classe->id, 'montant' => 359000,
        ]);
    }

    private function eleve(): Eleve
    {
        return Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $this->classe->id,
            'matricule' => '23PRIM2', 'nom_complet' => 'FOMESSO LIMA MARK JOEL', 'sexe' => 'M', 'statut' => 'actif',
        ]);
    }

    private function service(): ScolariteService
    {
        return app(ScolariteService::class);
    }

    public function test_corriger_un_recu_annule_lancien_et_encaisse_le_bon_montant(): void
    {
        $eleve = $this->eleve();
        $dossier = $this->service()->dossier($eleve, $this->annee);
        $versement = $this->service()->encaisser($dossier, ['montant' => 50000]);

        $corrige = $this->service()->corriger($versement, ['montant' => 75000]);

        $this->assertTrue($versement->fresh()->estAnnule());
        $this->assertNotSame($versement->id, $corrige->id);
        $this->assertSame(75000, $corrige->montant);
        $this->assertSame(75000, $dossier->fresh()->total_paye);
    }

    public function test_corriger_un_recu_deja_annule_echoue(): void
    {
        $eleve = $this->eleve();
        $dossier = $this->service()->dossier($eleve, $this->annee);
        $versement = $this->service()->encaisser($dossier, ['montant' => 50000]);
        $this->service()->annuler($versement, 'Erreur de saisie');

        $this->expectExceptionMessage('déjà annulé');
        $this->service()->corriger($versement->fresh(), ['montant' => 75000]);
    }

    public function test_modifier_une_dette_anterieure_imputee_ajuste_le_report_dette(): void
    {
        $eleve = $this->eleve();
        $dossier = $this->service()->dossier($eleve, $this->annee);
        $dette = $this->service()->enregistrerDetteAnterieure($eleve, 20000, 'Reliquat 2025', null);

        $this->service()->modifierDetteAnterieure($dette, 35000, 'Reliquat corrigé');

        $this->assertSame(35000, $dette->fresh()->montant);
        $this->assertSame(35000, $dossier->fresh()->report_dette);
    }

    public function test_reduire_une_dette_sous_ce_qui_est_deja_regle_echoue(): void
    {
        $eleve = $this->eleve();
        $dossier = $this->service()->dossier($eleve, $this->annee);
        $dette = $this->service()->enregistrerDetteAnterieure($eleve, 50000, null, null);
        $this->service()->encaisser($dossier->fresh(), [
            'montant' => 30000,
            'lignes' => [['affectation' => 'report_dette', 'montant' => 30000]],
        ]);

        $this->expectExceptionMessage('déjà été réglé');
        $this->service()->modifierDetteAnterieure($dette->fresh(), 20000, null);
    }

    public function test_modifier_une_dette_non_imputee_ne_touche_aucun_dossier(): void
    {
        $eleve = $this->eleve();
        $dette = DetteAnterieure::create([
            'school_id' => $this->school->id, 'eleve_id' => $eleve->id, 'montant' => 10000,
        ]);

        $corrigee = $this->service()->modifierDetteAnterieure($dette, 15000, 'Précision du motif');

        $this->assertSame(15000, $corrigee->montant);
        $this->assertSame('Précision du motif', $corrigee->motif);
    }
}
