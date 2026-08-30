<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\School;
use App\Services\ActiviteRentreeService;
use App\Services\RapportRentreeTexteService;
use App\Services\VenteDenreeService;
use App\Services\VisiteAutoriteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Visites d'autorités, activités pédagogiques/EPS/FENASSCO, vente de
 * denrées et blocs de texte libre — tableaux 22 à 25, 28 et les rubriques
 * narratives du rapport de rentrée MINEDUB.
 */
class RentreeScolaireVieScolaireTest extends TestCase
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

    public function test_gere_les_visites_dautorites(): void
    {
        $service = app(VisiteAutoriteService::class);

        $visite = $service->create($this->school->id, [
            'annee_scolaire_id' => $this->annee->id,
            'date_visite' => '2025-09-08',
            'qualite_autorite' => 'Délégation départementale',
            'nature_visite' => 'Professionnelle',
            'objectifs' => 'Effectivités de la rentrée scolaire',
        ]);

        $this->assertCount(1, $service->list($this->school->id, $this->annee->id));

        $service->update($visite, ['observations' => 'satisfaisante']);
        $this->assertSame('satisfaisante', $visite->fresh()->observations);

        $service->delete($visite);
        $this->assertCount(0, $service->list($this->school->id, $this->annee->id));
    }

    public function test_le_taux_dexecution_dune_activite_se_calcule_depuis_prevu_fait_sauf_saisie_directe(): void
    {
        $service = app(ActiviteRentreeService::class);

        $pedagogique = $service->create($this->school->id, [
            'annee_scolaire_id' => $this->annee->id,
            'categorie' => 'pedagogique',
            'activite' => 'Visites de classe',
            'prevues' => 10,
            'faites' => 2,
        ]);
        $this->assertSame(20, $pedagogique->taux_affichage);

        $fenassco = $service->create($this->school->id, [
            'annee_scolaire_id' => $this->annee->id,
            'categorie' => 'fenassco',
            'activite' => 'FOOTBALL',
            'taux_realisation' => 10,
        ]);
        $this->assertSame(10, $fenassco->taux_affichage);

        $activitesPedagogiques = $service->list($this->school->id, $this->annee->id, 'pedagogique');
        $this->assertCount(1, $activitesPedagogiques);
        $this->assertCount(2, $service->list($this->school->id, $this->annee->id));
    }

    public function test_gere_la_vente_de_denrees(): void
    {
        $service = app(VenteDenreeService::class);

        $vente = $service->create($this->school->id, [
            'annee_scolaire_id' => $this->annee->id,
            'nature' => 'Pains',
            'vendeur_nom' => 'Bobga Solange',
            'frais_verses' => 0,
        ]);

        $this->assertSame('Pains', $vente->nature);
        $this->assertCount(1, $service->list($this->school->id, $this->annee->id));

        $service->delete($vente);
        $this->assertCount(0, $service->list($this->school->id, $this->annee->id));
    }

    public function test_les_rubriques_de_texte_libre_sont_toujours_toutes_presentes(): void
    {
        $service = app(RapportRentreeTexteService::class);

        $toutesVides = $service->all($this->school->id, $this->annee->id);
        $this->assertArrayHasKey('doleances', $toutesVides);
        $this->assertArrayHasKey('conclusion_generale', $toutesVides);
        $this->assertNull($toutesVides['doleances']);

        $service->definir($this->school->id, $this->annee->id, 'doleances', 'Mettre la scolarité à jour.');

        $apresSaisie = $service->all($this->school->id, $this->annee->id);
        $this->assertSame('Mettre la scolarité à jour.', $apresSaisie['doleances']);
        // Les autres rubriques restent présentes et vides.
        $this->assertNull($apresSaisie['irr']);
    }
}
