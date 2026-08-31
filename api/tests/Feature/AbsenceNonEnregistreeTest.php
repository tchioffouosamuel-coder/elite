<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\Eleve;
use App\Models\Matiere;
use App\Models\NotificationInterne;
use App\Models\Presence;
use App\Models\School;
use App\Models\Seance;
use App\Models\Tuteur;
use App\Models\User;
use App\Services\AbsenceNonEnregistreeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Un élève sans le moindre pointage (ni présent, ni marqué absent) sur 5
 * jours de cours consécutifs déclenche une alerte administration + SMS
 * famille + blocage du compte parent — distinct d'une absence, même non
 * justifiée, qui laisse au moins une trace de pointage.
 */
class AbsenceNonEnregistreeTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private Classe $classe;

    private ClasseMatiere $classeMatiere;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'discipline.manage', 'guard_name' => 'web']);

        $this->school = School::create(['name' => 'Elites Test', 'code' => 'ET', 'type' => 'secondaire', 'is_active' => true]);
        $this->classe = Classe::create(['school_id' => $this->school->id, 'nom' => 'Terminale D']);

        $censeur = User::create([
            'school_id' => $this->school->id, 'name' => 'Censeur', 'email' => 'censeur@elites.test',
            'password' => Hash::make('secret'), 'is_active' => true,
        ]);
        $censeur->givePermissionTo('discipline.manage');

        $matiere = Matiere::create(['school_id' => $this->school->id, 'nom' => 'Mathématiques']);
        $this->classeMatiere = ClasseMatiere::create([
            'classe_id' => $this->classe->id, 'matiere_id' => $matiere->id, 'coefficient' => 3,
        ]);
    }

    private function eleve(string $nom): Eleve
    {
        return Eleve::create([
            'school_id' => $this->school->id, 'classe_id' => $this->classe->id,
            'nom_complet' => $nom, 'sexe' => 'M', 'statut' => 'actif',
        ]);
    }

    private function tuteurAvecCompte(Eleve $eleve): Tuteur
    {
        $user = User::create([
            'school_id' => $this->school->id, 'name' => 'Parent de '.$eleve->nom_complet,
            'phone' => '677'.random_int(100000, 999999), 'password' => Hash::make('secret'), 'is_active' => true,
        ]);

        $tuteur = Tuteur::create([
            'school_id' => $this->school->id, 'user_id' => $user->id,
            'nom_complet' => 'Parent de '.$eleve->nom_complet, 'telephone' => $user->phone,
        ]);
        $tuteur->eleves()->attach($eleve->id, ['is_principal' => true]);

        return $tuteur;
    }

    /** Crée une séance tenue à la date donnée, avec sa feuille de présence pour les élèves listés. */
    private function seance(string $date, array $presences = []): Seance
    {
        $seance = Seance::create([
            'school_id' => $this->school->id, 'classe_id' => $this->classe->id, 'classe_matiere_id' => $this->classeMatiere->id,
            'date_seance' => $date, 'heure_debut' => '08:00', 'heure_fin' => '09:00', 'statut' => 'effectuee',
        ]);

        foreach ($presences as $eleveId => $statut) {
            Presence::create(['seance_id' => $seance->id, 'eleve_id' => $eleveId, 'statut' => $statut]);
        }

        return $seance;
    }

    private function service(): AbsenceNonEnregistreeService
    {
        return app(AbsenceNonEnregistreeService::class);
    }

    public function test_alerte_apres_5_jours_de_cours_sans_le_moindre_pointage(): void
    {
        $absent = $this->eleve('DISPARU JEAN');
        $present = $this->eleve('REGULIER PAUL');
        $tuteur = $this->tuteurAvecCompte($absent);

        foreach (['2026-08-24', '2026-08-25', '2026-08-26', '2026-08-27', '2026-08-28'] as $date) {
            $this->seance($date, [$present->id => 'present']);
        }

        $this->travelTo('2026-08-31');
        $signales = $this->service()->detecterEtAlerter($this->school);

        $this->assertSame(1, $signales);

        $absent->refresh();
        $this->assertNotNull($absent->alerte_absence_declenchee_le);

        $tuteur->refresh();
        $this->assertFalse($tuteur->user->fresh()->is_active);
        $this->assertSame(0, $tuteur->user->tokens()->count());

        $this->assertTrue(
            NotificationInterne::where('school_id', $this->school->id)
                ->where('type', 'absence_non_enregistree')
                ->where('lien', "eleve:{$absent->id}")
                ->exists()
        );
    }

    public function test_une_absence_marquee_meme_non_justifiee_n_est_pas_une_absence_sans_pointage(): void
    {
        $eleve = $this->eleve('ABSENT CONNU');
        $tuteur = $this->tuteurAvecCompte($eleve);

        foreach (['2026-08-24', '2026-08-25', '2026-08-26', '2026-08-27', '2026-08-28'] as $date) {
            $this->seance($date, [$eleve->id => 'absent']);
        }

        $this->travelTo('2026-08-31');
        $signales = $this->service()->detecterEtAlerter($this->school);

        $this->assertSame(0, $signales);
        $this->assertTrue($tuteur->user->fresh()->is_active);
    }

    public function test_pas_encore_assez_de_jours_de_cours_ne_declenche_rien(): void
    {
        $eleve = $this->eleve('NOUVEAU ARRIVANT');
        $this->tuteurAvecCompte($eleve);

        foreach (['2026-08-26', '2026-08-27', '2026-08-28'] as $date) {
            $this->seance($date);
        }

        $this->travelTo('2026-08-31');
        $this->assertSame(0, $this->service()->detecterEtAlerter($this->school));
    }

    public function test_un_pointage_reapparu_referme_la_serie_et_permet_une_nouvelle_alerte(): void
    {
        $eleve = $this->eleve('DISPARU PUIS REVENU');
        $tuteur = $this->tuteurAvecCompte($eleve);

        foreach (['2026-08-17', '2026-08-18', '2026-08-19', '2026-08-20', '2026-08-21'] as $date) {
            $this->seance($date);
        }

        $this->travelTo('2026-08-24');
        $this->assertSame(1, $this->service()->detecterEtAlerter($this->school));
        $eleve->refresh();
        $this->assertNotNull($eleve->alerte_absence_declenchee_le);

        // L'élève reparaît, l'administrateur réactive le parent à la main.
        $this->seance('2026-08-24', [$eleve->id => 'present']);
        $tuteur->user->update(['is_active' => true]);

        // La fenêtre des 5 derniers jours de cours ne doit plus contenir le
        // 24 (jour du retour) : il faut 5 nouveaux jours sans pointage après lui.
        foreach (['2026-08-25', '2026-08-26', '2026-08-27', '2026-08-28', '2026-08-31'] as $date) {
            $this->seance($date);
        }

        $this->travelTo('2026-09-01');
        $this->assertSame(1, $this->service()->detecterEtAlerter($this->school));
        $this->assertFalse($tuteur->user->fresh()->is_active);
    }
}
