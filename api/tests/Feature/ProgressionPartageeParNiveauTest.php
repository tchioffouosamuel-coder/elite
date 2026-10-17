<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\FonctionReferentiel;
use App\Models\Matiere;
use App\Models\Personnel;
use App\Models\ProgressionItem;
use App\Models\School;
use App\Models\User;
use App\Support\Progression\ResolveurFicheProgression;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Point 1 (bug) de la punch-list : un enseignant de tronc commun qui donne la
 * même matière, au même niveau, dans plusieurs classes ne remplit sa fiche de
 * progression qu'une seule fois — elle s'applique ensuite à toutes ses
 * classes de ce niveau. Les fiches déjà existantes par classe (historiques)
 * doivent continuer à fonctionner sans fusion automatique.
 */
class ProgressionPartageeParNiveauTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $prof;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create(['name' => 'Elites Test', 'code' => 'ET', 'type' => 'secondaire', 'is_active' => true]);

        $fonction = FonctionReferentiel::firstOrCreate(['school_id' => $this->school->id, 'label_fr' => 'Enseignant']);
        $this->prof = User::create([
            'name' => 'Prof Tronc Commun', 'email' => 'prof.tc@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        Personnel::create([
            'school_id' => $this->school->id, 'user_id' => $this->prof->id, 'fonction_id' => $fonction->id,
            'nom_complet' => 'Prof Tronc Commun', 'sexe' => 'M', 'statut' => 'actif',
        ]);
    }

    private function classeMatiere(string $nomClasse, string $niveau): ClasseMatiere
    {
        $classe = Classe::create(['school_id' => $this->school->id, 'nom' => $nomClasse, 'niveau_classe' => $niveau]);
        $matiere = Matiere::firstOrCreate(['school_id' => $this->school->id, 'nom' => 'Anglais'], ['statut' => 'actif']);

        return ClasseMatiere::create([
            'classe_id' => $classe->id, 'matiere_id' => $matiere->id,
            'personnel_id' => $this->prof->personnel->id, 'statut' => 'actif',
        ]);
    }

    public function test_une_lecon_ajoutee_pour_une_classe_apparait_dans_lautre_classe_du_meme_niveau(): void
    {
        $sixiemeA = $this->classeMatiere('6e A', '6ème');
        $sixiemeB = $this->classeMatiere('6e B', '6ème');

        $porteur = ResolveurFicheProgression::attributsCreation($sixiemeA, ProgressionItem::class);
        ProgressionItem::create([...$porteur, 'type' => 'lecon', 'titre' => 'Present simple', 'ordre' => 1]);

        $this->assertTrue(ResolveurFicheProgression::estPartagee($sixiemeA));
        $this->assertTrue(ResolveurFicheProgression::estPartagee($sixiemeB));

        $leconsClasseB = ResolveurFicheProgression::itemsScope($sixiemeB)->get();
        $this->assertCount(1, $leconsClasseB);
        $this->assertSame('Present simple', $leconsClasseB->first()->titre);
    }

    public function test_un_autre_niveau_ne_voit_pas_la_fiche_partagee(): void
    {
        $sixieme = $this->classeMatiere('6e C', '6ème');
        $cinquieme = $this->classeMatiere('5e A', '5ème');

        $porteur = ResolveurFicheProgression::attributsCreation($sixieme, ProgressionItem::class);
        ProgressionItem::create([...$porteur, 'type' => 'lecon', 'titre' => 'Present simple', 'ordre' => 1]);

        $this->assertCount(0, ResolveurFicheProgression::itemsScope($cinquieme)->get());
    }

    public function test_une_fiche_historique_existante_continue_a_fonctionner_sans_fusion(): void
    {
        $sixiemeA = $this->classeMatiere('6e Hist A', '6ème');
        $sixiemeB = $this->classeMatiere('6e Hist B', '6ème');

        // Donnée historique : une leçon déjà rattachée directement à
        // `classe_matiere_id`, comme avant le correctif.
        ProgressionItem::create([
            'classe_matiere_id' => $sixiemeA->id, 'type' => 'lecon', 'titre' => 'Leçon historique', 'ordre' => 1,
        ]);

        $this->assertFalse(ResolveurFicheProgression::estPartagee($sixiemeA));
        $this->assertCount(1, ResolveurFicheProgression::itemsScope($sixiemeA)->get());
        // La classe B, elle, n'a pas de legacy : une nouvelle leçon pour elle
        // part directement en mode partagé, sans jamais voir la leçon
        // historique de la classe A.
        $this->assertTrue(ResolveurFicheProgression::estPartagee($sixiemeB));
        $this->assertCount(0, ResolveurFicheProgression::itemsScope($sixiemeB)->get());
    }
}
