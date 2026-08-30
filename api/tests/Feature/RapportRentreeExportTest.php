<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Personnel;
use App\Models\School;
use App\Models\Setting;
use App\Services\RapportRentreeService;
use App\Support\Pdf\RapportRentreeGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Assemblage du rapport de rentrée complet — vérifie que l'agrégateur
 * recompose fidèlement ce que chaque module (élèves, personnel,
 * infrastructures…) expose séparément, et que le document PDF se génère
 * sans erreur même quand la plupart des rubriques sont vides.
 */
class RapportRentreeExportTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private AnneeScolaire $annee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create(['name' => 'Elites Primaire', 'code' => 'EPP', 'type' => 'primaire', 'is_active' => true]);
        $this->annee = AnneeScolaire::create([
            'school_id' => $this->school->id, 'libelle' => '2025-2026',
            'date_debut' => '2025-09-01', 'date_fin' => '2026-06-30', 'is_active' => true,
        ]);
    }

    private function service(): RapportRentreeService
    {
        return app(RapportRentreeService::class);
    }

    public function test_agrege_les_effectifs_par_classe_avec_les_bons_totaux(): void
    {
        $classe = Classe::create(['school_id' => $this->school->id, 'nom' => 'CM2-A']);

        Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $classe->id, 'nom_complet' => 'Jean Dupont',
            'sexe' => 'M', 'nationalite' => 'Camerounaise', 'refugie' => 'Non', 'redoublant' => false, 'statut' => 'actif',
            'numero_acte_naissance' => '2015/1234',
        ]);
        Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $classe->id, 'nom_complet' => 'Awa Koné',
            'sexe' => 'F', 'nationalite' => null, 'refugie' => 'Oui', 'redoublant' => true, 'statut' => 'actif',
            'numero_acte_naissance' => null,
        ]);

        $rapport = $this->service()->generer($this->school->id, $this->annee->id);

        $ligne = collect($rapport['effectifs_par_classe'])->firstWhere('classe.nom', 'CM2-A');
        $this->assertSame(2, $ligne['total']['total']);
        $this->assertSame(1, $ligne['total']['camerounais']);
        $this->assertSame(1, $ligne['total']['non_camerounais']);
        $this->assertSame(1, $ligne['total']['refugies']);
        $this->assertSame(1, $ligne['total']['redoublants']);
        $this->assertSame(1, $ligne['total']['sans_acte_naissance']);
    }

    public function test_le_rapport_complet_reprend_lidentite_saisie_dans_les_parametres(): void
    {
        Setting::set($this->school->id, 'arrondissement', 'Bertoua 2ème');
        Setting::set($this->school->id, 'fondateur_nom', 'Elvice FOMESSO');

        $rapport = $this->service()->generer($this->school->id, $this->annee->id);

        $this->assertSame('Bertoua 2ème', $rapport['identite']['arrondissement']);
        $this->assertSame('Elvice FOMESSO', $rapport['identite']['fondateur_nom']);
        $this->assertNull($rapport['identite']['secteur']);
    }

    public function test_le_pdf_se_genere_meme_avec_un_rapport_presque_vide(): void
    {
        Personnel::create(['school_id' => $this->school->id, 'nom_complet' => 'MBARGA PAUL', 'sexe' => 'M', 'statut' => 'actif']);

        $rapport = $this->service()->generer($this->school->id, $this->annee->id);
        $pdf = (new RapportRentreeGenerator)->build($rapport);

        $this->assertStringStartsWith('%PDF', $pdf);
    }
}
