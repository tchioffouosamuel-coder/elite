<?php

namespace Tests\Feature;

use App\Models\Annonce;
use App\Models\FonctionReferentiel;
use App\Models\Personnel;
use App\Models\School;
use App\Models\User;
use App\Support\CataloguePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Une annonce ciblée (sur une fonction du personnel, ou sur des comptes
 * précis) ne doit être lue que par ses destinataires — en ligne comme dans la
 * copie locale du téléphone. Seul celui qui publie les voit toutes.
 */
class AnnonceCiblageTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $enseignant;

    private User $infirmiere;

    private User $parent;

    private User $direction;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CataloguePermissions::codes() as $code) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
        }

        $this->school = School::create(['name' => 'Elites Secondaire', 'code' => 'ES', 'type' => 'secondaire', 'is_active' => true]);
        $fonctionEnseignant = FonctionReferentiel::create(['school_id' => $this->school->id, 'label_fr' => 'Enseignant']);
        $fonctionInfirmier = FonctionReferentiel::create(['school_id' => $this->school->id, 'label_fr' => 'Infirmier']);

        $this->enseignant = $this->agent('prof@test.local', $fonctionEnseignant);
        $this->infirmiere = $this->agent('infirmiere@test.local', $fonctionInfirmier);

        $this->parent = User::create([
            'name' => 'Parent', 'email' => 'parent@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $this->parent->givePermissionTo('annonces.view');

        $this->direction = User::create([
            'name' => 'Direction', 'email' => 'direction@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $this->direction->givePermissionTo(['annonces.view', 'annonces.publish']);

        $this->annonce('Rentrée', 'tous', null);
        $this->annonce('Conseil pédagogique', 'fonction', [$fonctionEnseignant->id]);
        $this->annonce('Stock de pharmacie', 'utilisateurs', [$this->infirmiere->id]);
    }

    private function agent(string $email, FonctionReferentiel $fonction): User
    {
        $user = User::create([
            'name' => $email, 'email' => $email, 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $user->givePermissionTo('annonces.view');
        Personnel::create([
            'school_id' => $this->school->id, 'user_id' => $user->id, 'fonction_id' => $fonction->id,
            'nom_complet' => $email, 'sexe' => 'F', 'statut' => 'actif',
        ]);

        return $user->fresh();
    }

    /** @param  list<int>|null  $cible */
    private function annonce(string $titre, string $cibleType, ?array $cible): void
    {
        Annonce::create([
            'school_id' => $this->school->id, 'titre' => $titre, 'contenu' => $titre,
            'publiee_le' => now(), 'cible_type' => $cibleType, 'cible_data' => $cible,
        ]);
    }

    /** @return list<string> */
    private function titresListe(User $user): array
    {
        $titres = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/annonces')
            ->assertOk()
            ->json('data.*.titre');
        sort($titres);

        return $titres;
    }

    /** @return list<string> */
    private function titresSynchronises(User $user): array
    {
        $titres = collect($this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/sync?entites=annonces')
            ->assertOk()
            ->json('data.donnees.annonces'))
            ->pluck('titre')
            ->sort()
            ->values()
            ->all();

        return $titres;
    }

    public function test_un_parent_ne_lit_que_les_annonces_pour_tous(): void
    {
        $this->assertSame(['Rentrée'], $this->titresListe($this->parent));
        $this->assertSame(['Rentrée'], $this->titresSynchronises($this->parent));
    }

    public function test_une_annonce_ciblee_sur_une_fonction_atteint_cette_fonction_seulement(): void
    {
        $this->assertSame(['Conseil pédagogique', 'Rentrée'], $this->titresListe($this->enseignant));
        $this->assertSame(['Conseil pédagogique', 'Rentrée'], $this->titresSynchronises($this->enseignant));
    }

    public function test_une_annonce_ciblee_sur_un_compte_atteint_ce_compte_seulement(): void
    {
        $this->assertSame(['Rentrée', 'Stock de pharmacie'], $this->titresListe($this->infirmiere));
        $this->assertSame(['Rentrée', 'Stock de pharmacie'], $this->titresSynchronises($this->infirmiere));
    }

    public function test_celui_qui_publie_voit_toutes_les_annonces(): void
    {
        $this->assertSame(
            ['Conseil pédagogique', 'Rentrée', 'Stock de pharmacie'],
            $this->titresListe($this->direction),
        );
    }
}
