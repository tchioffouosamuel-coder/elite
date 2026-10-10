<?php

namespace Tests\Feature;

use App\Exports\DepenseExport;
use App\Models\CompteComptable;
use App\Models\Depense;
use App\Models\School;
use App\Services\DepenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Export Excel des dépenses : il rend la période filtrée, pas seulement la
 * page affichée, et traduit les énumérations (source, mode, statut) en
 * libellés lisibles plutôt qu'en codes de base.
 */
class DepenseExportTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private DepenseService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create(['name' => 'Elites Test', 'code' => 'ET', 'type' => 'primaire', 'is_active' => true]);
        $this->service = app(DepenseService::class);

        $compte = CompteComptable::where('code', '614')->firstOrFail();

        $this->service->enregistrer($this->school->id, [
            'libelle' => 'Achat de craies',
            'montant' => 15000,
            'date_depense' => '2026-09-05',
            'compte_comptable_id' => $compte->id,
            'mode' => 'especes',
            'beneficiaire' => 'Papeterie du coin',
            'reference_facture' => 'FAC-001',
            'responsable' => 'Econome',
            'source' => 'caisse',
            'statut' => 'payee',
        ]);

        $this->service->enregistrer($this->school->id, [
            'libelle' => 'Facture electricite',
            'montant' => 5000,
            'date_depense' => '2026-10-02',
            'compte_comptable_id' => $compte->id,
            'mode' => 'mobile_money',
            'source' => 'revenu_personnel',
            'statut' => 'engagee',
        ]);
    }

    /** @return array<int, array<int, mixed>> */
    private function lignes(array $filtres): array
    {
        $bilan = $this->service->bilan($this->school->id, $filtres, null);
        $export = new DepenseExport($bilan['depenses']);

        return $bilan['depenses']->map(fn(Depense $d) => $export->map($d))->all();
    }

    public function test_export_traduit_les_enumerations_et_sort_un_montant_sommable(): void
    {
        $lignes = $this->lignes([]);

        $this->assertCount(2, $lignes);

        // Tri du bilan : la plus récente d'abord.
        [$electricite, $craies] = $lignes;

        $this->assertSame('2026-09-05', $craies[0]);
        $this->assertSame('Achat de craies', $craies[1]);
        $this->assertSame(15000, $craies[2]);
        $this->assertSame('614', $craies[3]);
        $this->assertSame('Caisse', $craies[5]);
        $this->assertSame('Espèces', $craies[6]);
        $this->assertSame('Payée', $craies[7]);
        $this->assertSame('FAC-001', $craies[9]);

        $this->assertSame('Revenu personnel', $electricite[5]);
        $this->assertSame('Mobile Money', $electricite[6]);
        $this->assertSame('Engagée', $electricite[7]);
    }

    public function test_export_respecte_les_filtres_de_la_liste(): void
    {
        $this->assertSame(
            ['Facture electricite'],
            array_column($this->lignes(['du' => '2026-10-01']), 1),
        );

        $this->assertSame(
            ['Achat de craies'],
            array_column($this->lignes(['statut' => 'payee']), 1),
        );

        $this->assertSame(
            ['Achat de craies'],
            array_column($this->lignes(['q' => 'craies']), 1),
        );
    }

    /** Non paginé : la pagination de l'écran ne doit pas tronquer le fichier. */
    public function test_export_ignore_la_pagination_de_l_ecran(): void
    {
        $this->assertCount(2, $this->lignes([]));
        // La liste, elle, se pagine — l'export passe `null` pour l'éviter.
        $this->assertSame(1, $this->service->bilan($this->school->id, [], 1)['depenses']->perPage());
    }

    public function test_les_entetes_sont_ceux_que_relit_l_import(): void
    {
        // Mêmes normalisation que DepenseImport::cle() : c'est sous cette
        // forme que l'import reconnaît une colonne.
        $slugs = array_map(
            fn(string $entete) => preg_replace('/[^a-z0-9]+/', '', mb_strtolower(\Illuminate\Support\Str::ascii($entete))),
            (new DepenseExport(collect()))->headings(),
        );

        foreach (['date', 'libelle', 'montant', 'mode', 'beneficiaire', 'referencefacture', 'responsable', 'compte', 'source', 'statut'] as $cle) {
            $this->assertContains($cle, $slugs, "L'import ne reconnaîtrait pas la colonne « {$cle} ».");
        }

        // Le libellé du compte ne doit pas être pris pour celui de la dépense.
        $this->assertSame(1, count(array_keys($slugs, 'libelle', true)));
    }
}
