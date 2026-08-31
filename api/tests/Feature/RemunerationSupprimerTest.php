<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\V1\RemunerationController;
use App\Models\Personnel;
use App\Models\Remuneration;
use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

/**
 * Suppression d'une rémunération : un agent sans rémunération plus récente
 * redevient « Non défini », exactement comme s'il n'avait jamais été fixé.
 */
class RemunerationSupprimerTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private Personnel $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create(['name' => 'Elites Test', 'code' => 'ET', 'type' => 'primaire', 'is_active' => true]);
        $this->agent = Personnel::create([
            'school_id' => $this->school->id, 'nom_complet' => 'MBALLA PIERRE', 'sexe' => 'M', 'statut' => 'actif',
        ]);

        // `Tenant::schoolId()/schoolIds()` sont normalement résolus par le
        // middleware `ScopeEtablissement` : on les fixe ici directement, comme
        // le fait la requête HTTP une fois authentifiée.
        App::instance('tenant.school_id', $this->school->id);
        App::instance('tenant.school_ids', [$this->school->id]);
        App::instance('tenant.is_aggregate', false);
    }

    private function controller(): RemunerationController
    {
        return app(RemunerationController::class);
    }

    public function test_supprimer_retire_la_remuneration_et_l_agent_redevient_non_defini(): void
    {
        $remuneration = Remuneration::create([
            'school_id' => $this->school->id, 'personnel_id' => $this->agent->id, 'date_effet' => '2026-01-01',
            'salaire_base' => 50000,
        ]);

        $reponse = $this->controller()->supprimer($remuneration->id);

        $this->assertInstanceOf(JsonResponse::class, $reponse);
        $this->assertSame(200, $reponse->getStatusCode());
        $this->assertSame(0, Remuneration::count());

        $index = $this->controller()->index(request());
        $donnees = $index->getData(true)['data'];
        $ligne = collect($donnees['personnels'])->firstWhere('id', $this->agent->id);

        $this->assertNull($ligne['remuneration']);
    }

    public function test_supprimer_une_entree_ancienne_laisse_la_plus_recente_en_vigueur(): void
    {
        Remuneration::create([
            'school_id' => $this->school->id, 'personnel_id' => $this->agent->id, 'date_effet' => '2025-01-01',
            'salaire_base' => 40000,
        ]);
        $recente = Remuneration::create([
            'school_id' => $this->school->id, 'personnel_id' => $this->agent->id, 'date_effet' => '2026-01-01',
            'salaire_base' => 50000,
        ]);

        $ancienne = Remuneration::where('date_effet', '2025-01-01')->firstOrFail();
        $this->controller()->supprimer($ancienne->id);

        $this->assertSame(1, Remuneration::count());
        $this->assertTrue(Remuneration::whereKey($recente->id)->exists());
    }

    public function test_une_remuneration_hors_perimetre_n_est_pas_supprimable(): void
    {
        $autreEcole = School::create(['name' => 'Autre école', 'code' => 'AU', 'type' => 'primaire', 'is_active' => true]);
        $autreAgent = Personnel::create([
            'school_id' => $autreEcole->id, 'nom_complet' => 'AUTRE AGENT', 'sexe' => 'M', 'statut' => 'actif',
        ]);
        $remuneration = Remuneration::create([
            'school_id' => $autreEcole->id, 'personnel_id' => $autreAgent->id, 'date_effet' => '2026-01-01',
            'salaire_base' => 50000,
        ]);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->controller()->supprimer($remuneration->id);
    }
}
