<?php

namespace Tests\Feature;

use App\Models\AbsenceTrimestre;
use App\Models\AnneeScolaire;
use App\Models\BusAffectation;
use App\Models\BusArret;
use App\Models\BusTrajet;
use App\Models\BusVehicule;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\DetteAnterieure;
use App\Models\DossierFraisAnnexe;
use App\Models\DossierScolarite;
use App\Models\Eleve;
use App\Models\Matiere;
use App\Models\Moratoire;
use App\Models\Note;
use App\Models\Remise;
use App\Models\School;
use App\Models\Sequence;
use App\Models\Trimestre;
use App\Models\Tuteur;
use App\Models\User;
use App\Models\Versement;
use App\Models\VisiteInfirmerie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * L'export élèves couvre désormais bien plus que l'identité : scolarité,
 * frais annexes, versements, dettes/remises/moratoires, infirmerie, résultats,
 * transport et absences, chacun sur sa propre feuille. Ce test peuple un
 * dossier complet et vérifie que le fichier se génère sans erreur (les
 * classes d'export ne sont sinon jamais exécutées par les tests unitaires
 * classiques, qui se contentent souvent de `Excel::fake()`).
 */
class EleveExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_genere_un_classeur_complet_sans_erreur(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $school = School::create(['name' => 'Elites Secondaire', 'code' => 'ES', 'type' => 'secondaire', 'is_active' => true]);

        $admin = User::create([
            'name' => 'Root', 'email' => 'root@test.local', 'password' => 'password',
            'school_id' => $school->id, 'is_active' => true,
        ]);
        $admin->assignRole('super_admin');

        $annee = AnneeScolaire::create([
            'school_id' => $school->id, 'libelle' => '2026-2027',
            'date_debut' => '2026-09-01', 'date_fin' => '2027-07-15', 'is_active' => true,
        ]);

        $classe = Classe::create(['school_id' => $school->id, 'nom' => '3ème A']);

        $eleve = Eleve::create([
            'school_id' => $school->id,
            'classe_id' => $classe->id,
            'matricule' => '26ELITES-0001',
            'nom_complet' => 'ATANGANA Paul',
            'sexe' => 'M',
            'date_naissance' => '2012-03-15',
            'lieu_naissance' => 'Yaoundé',
            'nationalite' => 'Camerounaise',
            'groupe_sanguin' => 'O+',
            'situation_sanitaire' => 'RAS',
            'allergies' => 'Aucune',
            'statut' => 'actif',
        ]);

        $tuteur = Tuteur::create([
            'school_id' => $school->id,
            'nom_complet' => 'ATANGANA Marie',
            'telephone' => '699000001',
            'email' => 'marie@example.com',
            'profession' => 'Enseignante',
        ]);
        $eleve->tuteurs()->attach($tuteur->id, ['lien_parente' => 'mère', 'is_principal' => true]);

        $dossier = DossierScolarite::create([
            'school_id' => $school->id,
            'annee_scolaire_id' => $annee->id,
            'eleve_id' => $eleve->id,
            'montant_scolarite' => 300000,
            'remise' => 10000,
            'report_dette' => 5000,
        ]);

        DossierFraisAnnexe::create([
            'dossier_scolarite_id' => $dossier->id,
            'libelle' => 'Frais d\'inscription',
            'montant' => 15000,
        ]);

        Versement::create([
            'school_id' => $school->id,
            'dossier_scolarite_id' => $dossier->id,
            'numero_recu' => 'R-0001',
            'date_versement' => '2026-09-10',
            'montant' => 100000,
            'mode' => 'especes',
            'encaisse_par' => $admin->id,
        ]);

        DetteAnterieure::create([
            'school_id' => $school->id, 'eleve_id' => $eleve->id, 'montant' => 20000, 'motif' => 'Reliquat 2025',
        ]);
        Remise::create([
            'school_id' => $school->id, 'eleve_id' => $eleve->id, 'annee_scolaire_id' => $annee->id,
            'montant' => 10000, 'motif' => 'Bourse mérite',
        ]);
        Moratoire::create([
            'school_id' => $school->id, 'eleve_id' => $eleve->id,
            'date_delivrance' => '2026-09-01', 'date_expiration' => '2027-01-01', 'motif' => 'Difficulté familiale',
        ]);

        VisiteInfirmerie::create([
            'eleve_id' => $eleve->id, 'classe_id' => $classe->id, 'date_visite' => '2026-10-05',
            'raison' => 'Mal de tête', 'soins_prodiges' => 'Paracétamol', 'cout_total' => 500,
        ]);

        $trimestre = Trimestre::create([
            'annee_scolaire_id' => $annee->id, 'libelle' => 'Trimestre 1', 'ordre' => 1,
            'date_debut' => '2026-09-01', 'date_fin' => '2026-12-15', 'is_active' => true,
        ]);
        $sequence = Sequence::create(['trimestre_id' => $trimestre->id, 'ordre' => 1, 'libelle' => 'Séquence 1']);

        $matiere = Matiere::create(['school_id' => $school->id, 'nom' => 'Mathématiques']);
        $classeMatiere = ClasseMatiere::create([
            'classe_id' => $classe->id, 'matiere_id' => $matiere->id, 'coefficient' => 4, 'statut' => 'actif',
        ]);
        Note::create([
            'eleve_id' => $eleve->id, 'classe_matiere_id' => $classeMatiere->id,
            'sequence_id' => $sequence->id, 'composante' => 'unique', 'valeur' => 15,
        ]);

        $vehicule = BusVehicule::create(['school_id' => $school->id, 'immatriculation' => 'CE-001-AB', 'capacite' => 30, 'statut' => 'actif']);
        $trajet = BusTrajet::create(['school_id' => $school->id, 'vehicule_id' => $vehicule->id, 'nom' => 'Circuit centre-ville', 'tarif_aller_retour' => 15000]);
        $arret = BusArret::create(['trajet_id' => $trajet->id, 'nom' => 'Rond-point']);
        BusAffectation::create([
            'eleve_id' => $eleve->id, 'trajet_id' => $trajet->id, 'arret_id' => $arret->id,
            'annee_scolaire_id' => $annee->id, 'tarif_mensuel' => 15000, 'option_trajet' => 'aller_retour', 'statut' => 'actif',
        ]);

        AbsenceTrimestre::create([
            'eleve_id' => $eleve->id, 'trimestre_id' => $trimestre->id,
            'heures_justifiees' => 2.5, 'heures_non_justifiees' => 1.0,
        ]);

        $reponse = $this->actingAs($admin, 'sanctum')
            ->withHeader('X-School-Id', $school->id)
            ->get('/api/v1/eleves/export');

        $reponse->assertOk();
        $reponse->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}
