<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\School;
use App\Support\SignatureEmploiDuTemps;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le PDF authentique derrière un QR scanné : servi sans compte (la personne
 * qui vérifie est souvent extérieure à l'école), mais uniquement pour un
 * chemin dont la signature est valide.
 */
class VerificationDocumentTest extends TestCase
{
    use RefreshDatabase;

    private Classe $classe;

    private AnneeScolaire $annee;

    protected function setUp(): void
    {
        parent::setUp();

        $school = School::create([
            'name' => 'Elites Tech', 'code' => 'ETC', 'type' => 'secondaire', 'is_active' => true,
        ]);
        $this->annee = AnneeScolaire::create([
            'school_id' => $school->id, 'libelle' => '2026-2027',
            'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31', 'is_active' => true,
        ]);
        $this->classe = Classe::create(['school_id' => $school->id, 'nom' => 'ACCOUNTING 1']);
    }

    public function test_le_document_authentique_est_servi_sans_authentification(): void
    {
        $signature = SignatureEmploiDuTemps::signer($this->classe->id, $this->annee->id);

        $reponse = $this->get("/api/v1/verify-document/verification-emploi-du-temps/{$this->classe->id}/{$this->annee->id}/{$signature}");

        $reponse->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $reponse->getContent());
    }

    public function test_une_signature_invalide_ne_livre_aucun_document(): void
    {
        $this->getJson("/api/v1/verify-document/verification-emploi-du-temps/{$this->classe->id}/{$this->annee->id}/faussesignature")
            ->assertStatus(422);
    }

    public function test_un_type_de_document_inconnu_est_introuvable(): void
    {
        $this->getJson('/api/v1/verify-document/verification-inconnue/1/2/abc')
            ->assertNotFound();
    }
}
