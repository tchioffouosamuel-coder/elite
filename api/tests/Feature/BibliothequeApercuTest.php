<?php

namespace Tests\Feature;

use App\Models\BibliothequeDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

/**
 * Aperçu PDF des documents Word de la bibliothèque : la visionneuse de l'app
 * mobile n'affiche que du PDF, un .docx y restait illisible.
 */
class BibliothequeApercuTest extends TestCase
{
    use RefreshDatabase;

    private function documentWord(): BibliothequeDocument
    {
        Storage::fake('public');
        Storage::fake('local');

        $word = new PhpWord;
        $word->addSection()->addText('Scheme of work — Food and nutrition');
        $chemin = 'bibliotheque/scheme.docx';
        Storage::disk('public')->makeDirectory('bibliotheque');
        IOFactory::createWriter($word, 'Word2007')->save(Storage::disk('public')->path($chemin));

        return BibliothequeDocument::create([
            'titre' => 'Scheme of work', 'fichier_path' => $chemin,
            'fichier_nom_original' => 'scheme.docx', 'taille' => 1024,
            'type_mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }

    public function test_un_document_word_est_servi_en_pdf_via_son_lien_signe(): void
    {
        $document = $this->documentWord();

        $this->assertNotNull($document->apercu_url);

        $reponse = $this->get($document->apercu_url);

        $reponse->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', file_get_contents($reponse->baseResponse->getFile()->getPathname()));
    }

    public function test_sans_signature_l_apercu_est_refuse(): void
    {
        $document = $this->documentWord();

        $this->get("/api/v1/bibliotheque/{$document->id}/apercu")->assertForbidden();
    }

    public function test_un_pdf_n_a_pas_d_apercu(): void
    {
        $document = BibliothequeDocument::create([
            'titre' => 'Règlement', 'fichier_path' => 'bibliotheque/reglement.pdf',
            'fichier_nom_original' => 'reglement.pdf', 'taille' => 1024, 'type_mime' => 'application/pdf',
        ]);

        $this->assertNull($document->apercu_url);
    }
}
