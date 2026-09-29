<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\Eleve;
use App\Models\Preinscription;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * La liste des élèves du mobile, alimentée par `/sync`, n'affiche que les
 * élèves dont la préinscription est validée pour l'année active : le pull
 * doit porter l'indicateur, et un changement de préinscription (ou
 * d'année active) doit faire redescendre l'élève dans le delta suivant.
 */
class SyncInscritAnneeActiveTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $admin;

    private AnneeScolaire $annee;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->school = School::create(['name' => 'Elites Primaire', 'code' => 'EP', 'type' => 'primaire', 'is_active' => true]);
        $this->annee = AnneeScolaire::create([
            'school_id' => $this->school->id, 'libelle' => '2026-2027',
            'date_debut' => '2026-09-01', 'date_fin' => '2027-06-30', 'is_active' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $this->admin->assignRole('super_admin');
    }

    private function eleve(string $nom): Eleve
    {
        return Eleve::create(['school_id' => $this->school->id, 'nom_complet' => $nom, 'sexe' => 'M', 'statut' => 'actif']);
    }

    private function preinscrire(Eleve $eleve, string $statut, ?AnneeScolaire $annee = null): Preinscription
    {
        return Preinscription::create([
            'school_id' => $this->school->id, 'annee_scolaire_id' => ($annee ?? $this->annee)->id, 'eleve_id' => $eleve->id,
            'type' => 'existant', 'statut' => $statut, 'donnees_eleve' => [], 'donnees_tuteurs' => [],
        ]);
    }

    /** @return array<int, bool> id élève => indicateur reçu */
    private function pull(?string $depuis = null): array
    {
        $reponse = $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-School-Id', $this->school->id)
            ->getJson('/api/v1/sync?entites=eleves'.($depuis ? '&depuis='.urlencode($depuis) : ''));

        $reponse->assertOk();

        return collect($reponse->json('data.donnees.eleves') ?? $reponse->json('data.eleves') ?? [])
            ->mapWithKeys(fn ($l) => [$l['id'] => $l['inscrit_annee_active']])
            ->all();
    }

    public function test_le_pull_indique_les_eleves_inscrits_pour_lannee_active(): void
    {
        $valide = $this->eleve('Valide');
        $enAttente = $this->eleve('En Attente');
        $ancienneAnnee = $this->eleve('Ancienne Annee');
        $sansPreinscription = $this->eleve('Sans');

        $this->preinscrire($valide, 'validee');
        $this->preinscrire($enAttente, 'en_attente');
        $passee = AnneeScolaire::create([
            'school_id' => $this->school->id, 'libelle' => '2025-2026',
            'date_debut' => '2025-09-01', 'date_fin' => '2026-06-30', 'is_active' => false,
        ]);
        $this->preinscrire($ancienneAnnee, 'validee', $passee);

        $indicateurs = $this->pull();

        $this->assertTrue($indicateurs[$valide->id]);
        $this->assertFalse($indicateurs[$enAttente->id]);
        $this->assertFalse($indicateurs[$ancienneAnnee->id]);
        $this->assertFalse($indicateurs[$sansPreinscription->id]);
    }

    public function test_valider_une_preinscription_fait_redescendre_leleve(): void
    {
        $eleve = $this->eleve('A Valider');
        $preinscription = $this->preinscrire($eleve, 'en_attente');

        Carbon::setTestNow(now()->addMinutes(5));
        $curseur = now()->toIso8601String();
        Carbon::setTestNow(now()->addMinutes(5));

        $preinscription->update(['statut' => 'validee']);

        $this->assertTrue($this->pull($curseur)[$eleve->id] ?? false);
        Carbon::setTestNow();
    }

    public function test_activer_une_annee_touche_les_eleves_de_lecole(): void
    {
        $eleve = $this->eleve('Rentree');
        $avant = $eleve->fresh()->updated_at;

        Carbon::setTestNow(now()->addMinutes(5));
        $suivante = AnneeScolaire::create([
            'school_id' => $this->school->id, 'libelle' => '2027-2028',
            'date_debut' => '2027-09-01', 'date_fin' => '2028-06-30', 'is_active' => false,
        ]);
        AnneeScolaire::where('school_id', $this->school->id)->update(['is_active' => false]);
        $suivante->update(['is_active' => true]);

        $this->assertTrue($eleve->fresh()->updated_at->gt($avant));
        Carbon::setTestNow();
    }
}
