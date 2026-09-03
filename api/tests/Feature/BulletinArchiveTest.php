<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\ArchiveClasseAnnee;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\Eleve;
use App\Models\Matiere;
use App\Models\Note;
use App\Models\School;
use App\Models\Sequence;
use App\Models\Trimestre;
use App\Models\User;
use App\Services\ArchivageService;
use App\Services\ConseilClasseService;
use App\Support\CataloguePermissions;
use App\Support\Pdf\BulletinArchiveGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Le point qui a motivé tout cet archivage : `ClasseMatiere` est un gabarit
 * permanent, jamais rattaché à une année — modifier un coefficient à la
 * rentrée suivante ne doit JAMAIS changer rétroactivement une moyenne déjà
 * publiée. Ce test le prouve en modifiant le coefficient après archivage et
 * en vérifiant que le bulletin archivé reste identique.
 */
class BulletinArchiveTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private Classe $classe;

    private AnneeScolaire $annee;

    private ClasseMatiere $affectation;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->school = School::create(['name' => 'Elites Test', 'code' => 'ET', 'type' => 'secondaire', 'is_active' => true]);

        $this->annee = AnneeScolaire::create([
            'school_id' => $this->school->id, 'libelle' => '2025-2026',
            'date_debut' => '2025-09-01', 'date_fin' => '2026-07-31', 'is_active' => true,
        ]);

        $trimestre = Trimestre::create([
            'annee_scolaire_id' => $this->annee->id, 'libelle' => 'Trimestre 1', 'ordre' => 1,
            'date_debut' => '2025-09-01', 'date_fin' => '2025-12-15', 'is_active' => true,
        ]);
        $sequence = Sequence::create(['trimestre_id' => $trimestre->id, 'libelle' => 'Séquence 1', 'ordre' => 1]);

        $this->classe = Classe::create(['school_id' => $this->school->id, 'nom' => '3ème A']);
        $matiere = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Mathématiques']);
        $this->affectation = ClasseMatiere::create(['classe_id' => $this->classe->id, 'matiere_id' => $matiere->id, 'coefficient' => 2]);

        $this->admin = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $this->admin->assignRole('super_admin');

        $this->sequence = $sequence;
    }

    private Sequence $sequence;

    public function test_le_bulletin_archive_ne_change_pas_apres_modification_du_coefficient(): void
    {
        $eleve = Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $this->classe->id,
            'nom_complet' => 'Eleve Archive', 'sexe' => 'M', 'statut' => 'actif',
        ]);
        Note::create([
            'eleve_id' => $eleve->id, 'classe_matiere_id' => $this->affectation->id,
            'sequence_id' => $this->sequence->id, 'composante' => 'unique', 'valeur' => 14.0,
        ]);

        $conseil = app(ConseilClasseService::class)->preparer($this->classe, $this->annee);
        app(ConseilClasseService::class)->valider($conseil, $this->admin);

        $archive = ArchiveClasseAnnee::where('annee_scolaire_id', $this->annee->id)->where('classe_id', $this->classe->id)->firstOrFail();
        $donneesAvant = app(ArchivageService::class)->donneesBulletinArchive($archive, $eleve->id);
        $moyenneAvant = $donneesAvant['trimestres'][0]['moyenne_generale'];

        $this->assertEquals(14.0, $moyenneAvant);

        // Le programme change à la rentrée suivante : le coefficient de la
        // même classe (gabarit permanent) est révisé.
        $this->affectation->update(['coefficient' => 5]);

        $donneesApres = app(ArchivageService::class)->donneesBulletinArchive($archive->fresh(), $eleve->id);
        $moyenneApres = $donneesApres['trimestres'][0]['moyenne_generale'];

        $this->assertEquals($moyenneAvant, $moyenneApres, 'Le bulletin archivé ne doit jamais recalculer avec le nouveau coefficient.');

        // Le générateur produit toujours un PDF valide à partir de ce même instantané.
        $pdf = (new BulletinArchiveGenerator)->build($donneesApres);
        $this->assertStringStartsWith('%PDF', $pdf);
    }

    public function test_le_bulletin_archive_porte_la_moyenne_et_le_rang_annuels(): void
    {
        $fort = Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $this->classe->id,
            'nom_complet' => 'Eleve Fort', 'sexe' => 'M', 'statut' => 'actif',
        ]);
        $faible = Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $this->classe->id,
            'nom_complet' => 'Eleve Faible', 'sexe' => 'M', 'statut' => 'actif',
        ]);
        Note::create(['eleve_id' => $fort->id, 'classe_matiere_id' => $this->affectation->id, 'sequence_id' => $this->sequence->id, 'composante' => 'unique', 'valeur' => 18.0]);
        Note::create(['eleve_id' => $faible->id, 'classe_matiere_id' => $this->affectation->id, 'sequence_id' => $this->sequence->id, 'composante' => 'unique', 'valeur' => 8.0]);

        $conseil = app(ConseilClasseService::class)->preparer($this->classe, $this->annee);
        app(ConseilClasseService::class)->valider($conseil, $this->admin);

        $archive = ArchiveClasseAnnee::where('annee_scolaire_id', $this->annee->id)->where('classe_id', $this->classe->id)->firstOrFail();

        $donneesFort = app(ArchivageService::class)->donneesBulletinArchive($archive, $fort->id);
        $donneesFaible = app(ArchivageService::class)->donneesBulletinArchive($archive, $faible->id);

        $this->assertEquals(18.0, $donneesFort['moyenne_annuelle']);
        $this->assertSame(1, $donneesFort['rang_annuel']);
        $this->assertEquals(8.0, $donneesFaible['moyenne_annuelle']);
        $this->assertSame(2, $donneesFaible['rang_annuel']);
    }

    public function test_les_routes_darchive_servent_le_pdf(): void
    {
        $eleve = Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $this->classe->id,
            'nom_complet' => 'Eleve Route', 'sexe' => 'F', 'statut' => 'actif',
        ]);
        Note::create(['eleve_id' => $eleve->id, 'classe_matiere_id' => $this->affectation->id, 'sequence_id' => $this->sequence->id, 'composante' => 'unique', 'valeur' => 12.0]);

        $conseil = app(ConseilClasseService::class)->preparer($this->classe, $this->annee);
        app(ConseilClasseService::class)->valider($conseil, $this->admin);

        $reponse = $this->actingAs($this->admin, 'sanctum')
            ->get("/api/v1/archives/annees/{$this->annee->id}/classes/{$this->classe->id}/bulletin/{$eleve->id}")
            ->assertOk();

        $this->assertStringStartsWith('%PDF', $reponse->getContent());

        $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/archives/annees/{$this->annee->id}/classes/{$this->classe->id}")
            ->assertOk()
            ->assertJsonPath('data.effectif', 1);
    }
}
