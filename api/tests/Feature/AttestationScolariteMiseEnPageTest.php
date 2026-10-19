<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\School;
use App\Services\AttestationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class AttestationScolariteMiseEnPageTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private string $logoPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create([
            'name' => 'Elites Test',
            'code' => 'ET',
            'type' => 'secondaire',
            'is_active' => true,
        ]);

        $image = imagecreatetruecolor(12, 12);
        imagefill($image, 0, 0, imagecolorallocate($image, 25, 75, 125));
        ob_start();
        imagepng($image);
        $logo = ob_get_clean();
        imagedestroy($image);

        $this->logoPath = 'schools/test-watermark.png';
        Storage::disk('public')->put($this->logoPath, $logo);
        $this->school->update(['logo_path' => $this->logoPath]);

        AnneeScolaire::create([
            'school_id' => $this->school->id,
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-07-31',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Storage::disk('public')->delete($this->logoPath);
        parent::tearDown();
    }

    public function test_le_certificat_centre_son_contenu_et_utilise_un_filigrane_translucide(): void
    {
        $classe = Classe::create(['school_id' => $this->school->id, 'nom' => 'Terminale A']);
        $eleve = Eleve::create([
            'school_id' => $this->school->id,
            'classe_id' => $classe->id,
            'nom_complet' => 'ELEVE TEST',
            'sexe' => 'M',
            'statut' => 'actif',
        ]);

        $chemin = app(AttestationService::class)->genererScolarite($eleve);
        $document = new ZipArchive;

        try {
            $this->assertTrue($document->open($chemin));
            $xml = $document->getFromName('word/document.xml');
            $this->assertIsString($xml);
            $this->assertGreaterThanOrEqual(6, substr_count($xml, '<w:jc w:val="center"/>'));

            $aTransparence = false;
            for ($i = 0; $i < $document->numFiles; $i++) {
                $nom = $document->getNameIndex($i);
                if (! str_starts_with($nom, 'word/media/') || ! str_ends_with($nom, '.png')) {
                    continue;
                }

                $contenu = $document->getFromIndex($i);
                $image = $contenu === false ? false : @imagecreatefromstring($contenu);
                if ($image === false) {
                    continue;
                }

                $aTransparence = imagecolorsforindex($image, imagecolorat($image, 0, 0))['alpha'] >= 100;
                imagedestroy($image);
                if ($aTransparence) {
                    break;
                }
            }

            $this->assertTrue($aTransparence, 'Le logo de filigrane doit être nettement translucide.');
        } finally {
            $document->close();
            if (is_file($chemin)) {
                unlink($chemin);
            }
        }
    }
}
