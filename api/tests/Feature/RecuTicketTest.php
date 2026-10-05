<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\BusAffectation;
use App\Models\BusTrajet;
use App\Models\BusVersement;
use App\Models\Classe;
use App\Models\DossierFraisAnnexe;
use App\Models\DossierScolarite;
use App\Models\Eleve;
use App\Models\School;
use App\Models\Versement;
use App\Models\VersementLigne;
use App\Support\Pdf\RecuVersementBusGenerator;
use App\Support\Pdf\RecuVersementGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reçus au format du ticket papier de l'établissement (cf.
 * GabaritRecuTicket) : tous les cas de données doivent produire un PDF.
 */
class RecuTicketTest extends TestCase
{
    use RefreshDatabase;

    private School $ecole;

    private Eleve $eleve;

    private AnneeScolaire $annee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ecole = School::create([
            'name' => 'Elites Tech', 'code' => 'ELITES-TECH', 'type' => 'secondaire', 'is_active' => true,
            'header_fr' => "<p><strong>REPUBLIQUE DU CAMEROUN</strong><br>**********<br><strong>COLLEGE TECHNIQUE D'INGENIERIE ET DE COMMERCE</strong></p>",
        ]);
        $this->annee = AnneeScolaire::create([
            'school_id' => $this->ecole->id, 'libelle' => '2026/2027',
            'date_debut' => '2026-09-01', 'date_fin' => '2027-07-15', 'is_active' => true,
        ]);
        $classe = Classe::create(['school_id' => $this->ecole->id, 'nom' => 'Marketing 2-A']);
        $this->eleve = Eleve::create([
            'school_id' => $this->ecole->id, 'classe_id' => $classe->id, 'matricule' => '25SEC21',
            'nom_complet' => 'Madougou Solange Jemima', 'sexe' => 'F', 'statut' => 'actif',
        ]);
    }

    public function test_recu_de_scolarite_avec_historique_et_accessoires(): void
    {
        $dossier = DossierScolarite::create(['school_id' => $this->ecole->id, 'annee_scolaire_id' => $this->annee->id, 'eleve_id' => $this->eleve->id, 'montant_scolarite' => 164500]);
        $tenue = DossierFraisAnnexe::create(['dossier_scolarite_id' => $dossier->id, 'libelle' => 'Tenue', 'montant' => 15000]);

        $premier = $this->versement($dossier, '15921', '2026-09-26', 50000);
        VersementLigne::create(['versement_id' => $premier->id, 'affectation' => 'frais_annexe', 'dossier_frais_annexe_id' => $tenue->id, 'libelle' => 'Tenue', 'montant' => 15000]);
        $second = $this->versement($dossier, '15940', '2026-09-30', 40000);

        $this->assertStringStartsWith('%PDF', (new RecuVersementGenerator)->build($second));
        // Réimpression du premier reçu après le second : reste calculé à sa date.
        $this->assertStringStartsWith('%PDF', (new RecuVersementGenerator)->build($premier->fresh()));
    }

    public function test_recu_annule(): void
    {
        $dossier = DossierScolarite::create(['school_id' => $this->ecole->id, 'annee_scolaire_id' => $this->annee->id, 'eleve_id' => $this->eleve->id, 'montant_scolarite' => 100000]);
        $versement = $this->versement($dossier, '1', '2026-09-26', 30000);
        $versement->update(['annule_le' => now(), 'motif_annulation' => 'Erreur de saisie']);

        $this->assertStringStartsWith('%PDF', (new RecuVersementGenerator)->build($versement->fresh()));
    }

    public function test_recu_de_transport(): void
    {
        $trajet = BusTrajet::create(['nom' => 'Ngaïkada']);
        $affectation = BusAffectation::create(['school_id' => $this->ecole->id, 'eleve_id' => $this->eleve->id, 'trajet_id' => $trajet->id, 'annee_scolaire_id' => $this->annee->id, 'statut' => 'actif']);
        $versement = BusVersement::create([
            'school_id' => $this->ecole->id, 'bus_affectation_id' => $affectation->id, 'mois' => '2026-10-01',
            'numero_recu' => 'B-1', 'date_versement' => '2026-10-02', 'montant' => 8000, 'remise' => 2000, 'mode' => 'mobile_money',
        ]);

        $this->assertStringStartsWith('%PDF', (new RecuVersementBusGenerator)->build($versement));
    }

    private function versement(DossierScolarite $dossier, string $numero, string $date, int $montant): Versement
    {
        $versement = Versement::create([
            'school_id' => $this->ecole->id, 'dossier_scolarite_id' => $dossier->id, 'numero_recu' => $numero,
            'date_versement' => $date, 'montant' => $montant, 'mode' => 'especes',
        ]);
        VersementLigne::create(['versement_id' => $versement->id, 'affectation' => 'scolarite', 'libelle' => 'Scolarité', 'montant' => $montant]);

        return $versement;
    }
}
