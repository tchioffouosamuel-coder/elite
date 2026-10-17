<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\Eleve;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Point 19 de la punch-list Elites-tech : listes de classe numérotées. */
class EleveNumeroOrdreTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private Classe $classe;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'eleves.update', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'eleves.numero_ordre', 'guard_name' => 'web']);

        $this->school = School::create([
            'name' => 'Elites Tech',
            'code' => 'ET',
            'type' => 'secondaire',
            'is_active' => true,
        ]);

        $this->classe = Classe::create([
            'school_id' => $this->school->id,
            'nom' => '6e A',
        ]);
    }

    public function test_un_nouvel_eleve_recoit_le_prochain_numero_libre(): void
    {
        $service = app(\App\Services\EleveService::class);

        $premier = $service->create($this->school->id, [
            'nom_complet' => 'Premier Eleve',
            'sexe' => 'M',
            'classe_id' => $this->classe->id,
        ]);
        $second = $service->create($this->school->id, [
            'nom_complet' => 'Second Eleve',
            'sexe' => 'F',
            'classe_id' => $this->classe->id,
        ]);

        $this->assertSame(1, $premier->numero_ordre);
        $this->assertSame(2, $second->numero_ordre);
    }

    public function test_ajouter_un_eleve_ne_renumerote_pas_lexistant(): void
    {
        $service = app(\App\Services\EleveService::class);

        $existant = $service->create($this->school->id, [
            'nom_complet' => 'Ancien', 'sexe' => 'M', 'classe_id' => $this->classe->id,
        ]);
        $service->definirNumeroOrdre($existant, 5);

        $nouveau = $service->create($this->school->id, [
            'nom_complet' => 'Nouveau', 'sexe' => 'F', 'classe_id' => $this->classe->id,
        ]);

        $this->assertSame(5, $existant->refresh()->numero_ordre);
        $this->assertSame(6, $nouveau->numero_ordre);
    }

    public function test_un_enseignant_peut_corriger_le_numero_sans_eleves_update(): void
    {
        $eleve = Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $this->classe->id,
            'nom_complet' => 'Elève X', 'sexe' => 'M', 'statut' => 'actif', 'numero_ordre' => 1,
        ]);

        $enseignant = User::create([
            'name' => 'Prof', 'email' => 'prof@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $enseignant->givePermissionTo('eleves.numero_ordre');

        $this->actingAs($enseignant, 'sanctum')
            ->patch("/api/v1/eleves/{$eleve->id}/numero-ordre", ['numero_ordre' => 7])
            ->assertOk()
            ->assertJsonPath('data.numero_ordre', 7);
    }

    public function test_refuse_un_numero_deja_pris_dans_la_meme_classe(): void
    {
        $service = app(\App\Services\EleveService::class);

        $a = Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $this->classe->id,
            'nom_complet' => 'A', 'sexe' => 'M', 'statut' => 'actif', 'numero_ordre' => 1,
        ]);
        $b = Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $this->classe->id,
            'nom_complet' => 'B', 'sexe' => 'F', 'statut' => 'actif', 'numero_ordre' => 2,
        ]);

        $this->expectException(\RuntimeException::class);
        $service->definirNumeroOrdre($b, 1);
    }

    public function test_un_transfert_de_classe_attribue_un_nouveau_numero(): void
    {
        $service = app(\App\Services\EleveService::class);
        $autreClasse = Classe::create(['school_id' => $this->school->id, 'nom' => '6e B']);

        $eleve = Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $this->classe->id,
            'nom_complet' => 'Transfert', 'sexe' => 'M', 'statut' => 'actif', 'numero_ordre' => 9,
        ]);

        $service->transferer($eleve, $autreClasse->fresh());

        $this->assertSame(1, $eleve->refresh()->numero_ordre);
    }
}
