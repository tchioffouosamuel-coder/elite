<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\EmploiDuTemps;
use App\Models\Matiere;
use App\Models\Personnel;
use App\Models\School;
use App\Services\EmploiDuTempsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Copie de créneaux vers une autre classe, sans reprendre l'enseignant. */
class EmploiDuTempsCopieTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private Classe $source;

    private Classe $cible;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create(['name' => 'Elites Tech', 'code' => 'ECP', 'type' => 'secondaire', 'is_active' => true]);
        AnneeScolaire::create([
            'school_id' => $this->school->id, 'libelle' => '2026-2027',
            'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31', 'is_active' => true,
        ]);

        $this->source = Classe::create(['school_id' => $this->school->id, 'nom' => 'F3-A']);
        $this->cible = Classe::create(['school_id' => $this->school->id, 'nom' => 'F3-B']);
    }

    private function service(): EmploiDuTempsService
    {
        return app(EmploiDuTempsService::class);
    }

    private function creneauSource(): EmploiDuTemps
    {
        $enseignant = Personnel::create([
            'school_id' => $this->school->id, 'nom_complet' => 'FOKO PIERRE', 'sexe' => 'M', 'statut' => 'actif',
        ]);
        $matiere = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Mathématiques']);
        $classeMatiere = ClasseMatiere::create([
            'classe_id' => $this->source->id, 'matiere_id' => $matiere->id, 'personnel_id' => $enseignant->id, 'coefficient' => 3,
        ]);

        return EmploiDuTemps::create([
            'school_id' => $this->school->id,
            'classe_id' => $this->source->id,
            'classe_matiere_id' => $classeMatiere->id,
            'jour' => 1,
            'heure_debut' => '08:00',
            'heure_fin' => '10:00',
            'salle' => 'B12',
        ]);
    }

    public function test_le_creneau_copie_reprend_le_jour_et_les_horaires(): void
    {
        $creneau = $this->creneauSource();

        [$copies, $ignores] = $this->service()->copierVers(collect([$creneau]), $this->cible);

        $this->assertSame(1, $copies);
        $this->assertSame(0, $ignores);

        $copie = EmploiDuTemps::where('classe_id', $this->cible->id)->firstOrFail();
        $this->assertSame(1, $copie->jour);
        $this->assertSame('08:00', substr((string) $copie->heure_debut, 0, 5));
        $this->assertSame('B12', $copie->salle);
    }

    public function test_la_copie_ne_reprend_pas_l_enseignant(): void
    {
        $creneau = $this->creneauSource();

        $this->service()->copierVers(collect([$creneau]), $this->cible);

        $copie = EmploiDuTemps::where('classe_id', $this->cible->id)->with('classeMatiere')->firstOrFail();
        $this->assertNull($copie->classeMatiere->personnel_id);
    }

    public function test_la_matiere_est_affectee_a_la_classe_cible_si_elle_ne_l_etait_pas(): void
    {
        $creneau = $this->creneauSource();
        $matiereId = $creneau->classeMatiere->matiere_id;

        $this->assertFalse(ClasseMatiere::where('classe_id', $this->cible->id)->where('matiere_id', $matiereId)->exists());

        $this->service()->copierVers(collect([$creneau]), $this->cible);

        $this->assertTrue(ClasseMatiere::where('classe_id', $this->cible->id)->where('matiere_id', $matiereId)->exists());
    }

    public function test_un_creneau_qui_chevaucherait_la_cible_est_ignore(): void
    {
        $creneau = $this->creneauSource();

        $autreMatiere = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Physique']);
        $autreClasseMatiere = ClasseMatiere::create([
            'classe_id' => $this->cible->id, 'matiere_id' => $autreMatiere->id, 'coefficient' => 2,
        ]);
        EmploiDuTemps::create([
            'school_id' => $this->school->id,
            'classe_id' => $this->cible->id,
            'classe_matiere_id' => $autreClasseMatiere->id,
            'jour' => 1,
            'heure_debut' => '09:00',
            'heure_fin' => '11:00',
        ]);

        [$copies, $ignores] = $this->service()->copierVers(collect([$creneau]), $this->cible);

        $this->assertSame(0, $copies);
        $this->assertSame(1, $ignores);
    }
}
