<?php

namespace App\Support\Sync;

use App\Models\AbsenceTrimestre;
use App\Models\ActiviteRentree;
use App\Models\Amortissement;
use App\Models\AnneeScolaire;
use App\Models\Annonce;
use App\Models\Apee;
use App\Models\AssuranceScolaire;
use App\Models\AvanceSalaire;
use App\Models\BudgetFonctionnement;
use App\Models\BudgetPersonnel;
use App\Models\BulletinPublication;
use App\Models\BusAffectation;
use App\Models\BusArret;
use App\Models\BusTrajet;
use App\Models\BusVehicule;
use App\Models\BusVersement;
use App\Models\Appreciation;
use App\Models\ChampPersonnalise;
use App\Models\Classe;
use App\Models\ClasseCompetence;
use App\Models\ClasseMatiere;
use App\Models\Competence;
use App\Models\CompteComptable;
use App\Models\ConseilEcole;
use App\Models\DemandeAvanceSalaire;
use App\Models\Departement;
use App\Models\Depense;
use App\Models\DetteAnterieure;
use App\Models\DocumentReference;
use App\Models\DossierFraisAnnexe;
use App\Models\DossierScolarite;
use App\Models\EcritureComptable;
use App\Models\Eleve;
use App\Models\EleveTuteur;
use App\Models\EmploiDuTemps;
use App\Models\EntreeStock;
use App\Models\EquipementMobilier;
use App\Models\Evaluation;
use App\Models\EvaluationQuestion;
use App\Models\FonctionReferentiel;
use App\Models\FraisAnnexe;
use App\Models\GrilleFrais;
use App\Models\Immobilisation;
use App\Models\Infrastructure;
use App\Models\InventaireArticle;
use App\Models\JustificationAbsence;
use App\Models\MalaiseReferentiel;
use App\Models\Matiere;
use App\Models\ModificationEleve;
use App\Models\Moratoire;
use App\Models\Niveau;
use App\Models\NiveauScolaire;
use App\Models\NotificationInterne;
use App\Models\Note;
use App\Models\Observation;
use App\Models\Personnel;
use App\Models\Preinscription;
use App\Models\Presence;
use App\Models\ProgressionColonne;
use App\Models\ProgressionItem;
use App\Models\RapportRentreeTexte;
use App\Models\RapportTrimestreTexte;
use App\Models\Remise;
use App\Models\Revendication;
use App\Models\Sanction;
use App\Models\Seance;
use App\Models\Sequence;
use App\Models\SousSysteme;
use App\Models\TrancheScolarite;
use App\Models\Trimestre;
use App\Models\Tuteur;
use App\Models\TuteurTelephone;
use App\Models\User;
use App\Models\VenteDenree;
use App\Models\VenteFourniture;
use App\Models\VenteFournitureLigne;
use App\Models\Versement;
use App\Models\VersementLigne;
use App\Models\VisiteAutorite;
use App\Models\VisiteInfirmerie;
use App\Models\VisiteInfirmerieMateriel;
use Illuminate\Database\Eloquent\Builder;

/**
 * Catalogue des entités que le mobile réplique dans sa base locale.
 *
 * Une entité n'est pas décrite par une Resource existante : celles-ci imbriquent
 * les relations (`classe` complète dans `EleveResource`) pour un affichage web
 * direct, alors que le mobile stocke des tables relationnelles et fait ses
 * jointures en SQLite. On expose donc des lignes **plates**, clés étrangères
 * comprises — ce qui rend aussi la charge utile bien plus légère sur un réseau
 * mobile.
 *
 * La clé d'entité est volontairement stable et découplée du nom de classe :
 * renommer un modèle ne doit pas invalider les curseurs déjà en circulation.
 *
 * Le périmètre s'ajoute au filtre d'école : un compte borné (enseignant,
 * censeur, surveillant général — {@see \App\Support\Perimetre::estBorne()})
 * ne réplique que ses classes, pas l'établissement entier. Un compte non
 * borné (direction, super admin) ou l'absence d'utilisateur (routine
 * interne) reçoit `classes() === null`, qui désactive ce filtre — c'est le
 * même contrat que {@see \App\Models\Concerns\FiltreParPerimetre}.
 */
class RegistreSync
{
    /**
     * @return array<string, array{
     *     modele: class-string,
     *     colonnes: list<string>,
     *     portee: callable(Builder, int): Builder,
     *     permission: ?string
     * }>
     */
    public static function entites(?User $user = null): array
    {
        // Calculé une seule fois : chaque closure ci-dessous le capture par
        // valeur (arrow function), sans quoi `$user->perimetre()->classes()`
        // relancerait ses requêtes à chaque entité du catalogue.
        $classesPerimetre = $user?->perimetre()->classes();

        return [
            // --- Référentiels : socle nécessaire à l'affichage de tout le reste.
            'annee_scolaires' => [
                'modele' => AnneeScolaire::class,
                'colonnes' => ['id', 'school_id', 'libelle', 'date_debut', 'date_fin', 'is_active'],
                'portee' => fn (Builder $q, int $s) => $q->where('school_id', $s),
                'permission' => null,
            ],
            'trimestres' => [
                'modele' => Trimestre::class,
                'colonnes' => ['id', 'annee_scolaire_id', 'libelle', 'ordre', 'date_debut', 'date_fin', 'is_active'],
                'portee' => fn (Builder $q, int $s) => $q->whereHas('anneeScolaire', fn ($a) => $a->where('school_id', $s)),
                'permission' => null,
            ],
            'sequences' => [
                'modele' => Sequence::class,
                'colonnes' => ['id', 'trimestre_id', 'ordre', 'libelle'],
                'portee' => fn (Builder $q, int $s) => $q->whereHas('trimestre.anneeScolaire', fn ($a) => $a->where('school_id', $s)),
                'permission' => null,
            ],
            'niveaux' => [
                'modele' => Niveau::class,
                'colonnes' => ['id', 'code', 'name_fr', 'name_en', 'sous_system_id', 'school_id', 'ordre'],
                // Référentiel partiellement global : certaines lignes n'ont pas
                // d'école (cf. NiveauController, hors du groupe `tenant`).
                'portee' => fn (Builder $q, int $s) => $q->where(fn ($w) => $w->where('school_id', $s)->orWhereNull('school_id')),
                'permission' => null,
            ],
            // FK obligatoire de `niveaux.sous_system_id`/`classes.sous_systeme_id` :
            // sans lui, ces lignes arrivent avec une clé étrangère qui ne
            // résout jamais rien en local (aucun crash grâce à `PRAGMA
            // foreign_keys = OFF` côté desktop, mais l'écran affiche un champ
            // vide au lieu du sous-système réel).
            'sous_systemes' => [
                'modele' => SousSysteme::class,
                'colonnes' => ['id', 'school_id', 'code', 'nom', 'description'],
                'portee' => fn (Builder $q, int $s) => $q->where('school_id', $s),
                'permission' => null,
            ],
            // FK de `matieres.departement_id` — même raison que ci-dessus.
            'departements' => [
                'modele' => Departement::class,
                'colonnes' => ['id', 'school_id', 'nom', 'head_personnel_id'],
                'portee' => fn (Builder $q, int $s) => $q->where('school_id', $s),
                'permission' => 'personnel.view',
            ],
            'matieres' => [
                'modele' => Matiere::class,
                'colonnes' => ['id', 'school_id', 'departement_id', 'competence_id', 'nom', 'nom_en', 'abbreviation', 'statut'],
                'portee' => fn (Builder $q, int $s) => $q->where('school_id', $s),
                'permission' => 'pedagogie.view',
            ],
            // Le barème et les volets du primaire vivent désormais ici : sans
            // cette entrée, le poste hors ligne ne saurait plus sur quoi noter.
            'competences' => [
                'modele' => Competence::class,
                'colonnes' => ['id', 'school_id', 'label_fr', 'label_en', 'abbreviation', 'notation', 'evalue_pratique', 'repartition_volets', 'ordre', 'statut'],
                'portee' => fn (Builder $q, int $s) => $q->where('school_id', $s),
                'permission' => 'pedagogie.view',
            ],
            // Attribution d'une compétence à une classe — sans elle, le poste
            // hors ligne ne sait pas quelles compétences noter pour une classe
            // donnée (cf. `GET classes/{id}/competences`, son pendant en
            // ligne). Bornée comme `classe_matieres` : c'est la même notion
            // d'affectation, juste portée par une compétence plutôt qu'une
            // matière.
            'classe_competences' => [
                'modele' => ClasseCompetence::class,
                'colonnes' => ['id', 'classe_id', 'competence_id', 'groupe', 'statut'],
                'portee' => fn (Builder $q, int $s) => $q
                    ->whereHas('classe', fn ($c) => $c->where('school_id', $s))
                    ->when($classesPerimetre !== null, fn (Builder $q2) => $q2->whereIn('classe_id', $classesPerimetre)),
                'permission' => 'pedagogie.view',
            ],
            // Référentiel d'appréciations de la maternelle (cf.
            // `NotePrimaireService::parAppreciation()`) : sans lui, la grille
            // primaire hors ligne ne peut proposer aucun niveau à cocher pour
            // ces classes-là.
            'appreciations' => [
                'modele' => Appreciation::class,
                'colonnes' => ['id', 'school_id', 'label_fr', 'label_en', 'emoji', 'couleur', 'ordre', 'statut'],
                'portee' => fn (Builder $q, int $s) => $q->where('school_id', $s),
                'permission' => 'pedagogie.view',
            ],

            // --- Structure pédagogique.
            // Alignée sur `Classe::scopeDansPerimetre()` (cf.
            // `FiltreParPerimetre`), qu'utilise déjà `GET /classes` en ligne :
            // sans ce filtre, un compte borné voyait ici l'établissement
            // entier alors que `seances`/`presences`/`notes` ci-dessous
            // restaient correctement bornés à ses propres classes — la
            // classe apparaissait dans la liste locale mais restait vide de
            // tout, sans indication que ce n'était pas un problème de sync.
            'classes' => [
                'modele' => Classe::class,
                'colonnes' => ['id', 'school_id', 'niveau_id', 'niveau_scolaire_id', 'professeur_principal_id', 'titulaire_id', 'surveillant_general_id', 'nom', 'sigle', 'sous_systeme_id', 'niveau_classe', 'filiere', 'capacite', 'qr_token'],
                'portee' => fn (Builder $q, int $s) => $q->where('school_id', $s)
                    ->when($classesPerimetre !== null, fn (Builder $q2) => $q2->whereIn('id', $classesPerimetre)),
                'permission' => 'classes.view',
            ],
            'classe_matieres' => [
                'modele' => ClasseMatiere::class,
                'colonnes' => ['id', 'classe_id', 'matiere_id', 'personnel_id', 'coefficient', 'quota_horaire', 'groupe', 'competences', 'statut'],
                'portee' => fn (Builder $q, int $s) => $q
                    ->whereHas('classe', fn ($c) => $c->where('school_id', $s))
                    ->when($classesPerimetre !== null, fn (Builder $q2) => $q2->whereIn('classe_id', $classesPerimetre)),
                'permission' => 'pedagogie.view',
            ],
            'emplois_du_temps' => [
                'modele' => EmploiDuTemps::class,
                'colonnes' => ['id', 'school_id', 'classe_id', 'classe_matiere_id', 'jour', 'heure_debut', 'heure_fin', 'salle'],
                'portee' => fn (Builder $q, int $s) => $q->where('school_id', $s),
                'permission' => 'emploi_du_temps.view',
            ],
            'progression_items' => [
                'modele' => ProgressionItem::class,
                // Gabarits établissement (cf. migration
                // `refaire_fiche_progression_gabarits`) : les colonnes MINESEC
                // (objectifs, materiel, activites, devoirs, lesson, mode,
                // references, research_questions, introduction, presentation,
                // conclusion...) n'existent plus depuis ce changement de forme.
                'colonnes' => [
                    'id', 'classe_matiere_id', 'parent_id', 'type', 'titre', 'description', 'ordre', 'sequence_id', 'duree_prevue',
                    'topic', 'sous_topic', 'competence', 'expected_learning_outcomes', 'entry_behaviour', 'teaching_aids', 'teaching_learning_strategies',
                    'learners_activities', 'facilitators_activities', 'assessment', 'assignment', 'remarks',
                    'semaine', 'date_prevue', 'date_realisee', 'duree', 'colonnes_libres',
                ],
                'portee' => fn (Builder $q, int $s) => $q
                    ->whereHas('classeMatiere.classe', fn ($c) => $c->where('school_id', $s)
                        ->when($classesPerimetre !== null, fn (Builder $q2) => $q2->whereIn('classes.id', $classesPerimetre))),
                'permission' => 'pedagogie.view',
            ],
            // Niveau interne au primaire/maternelle (SIL, CP, CE1… — page
            // « Niveaux d'enseignement »), un modèle distinct de `Niveau`
            // (référentiel MINESEC partagé) malgré le nom proche.
            'niveaux_scolaires' => [
                'modele' => NiveauScolaire::class,
                'colonnes' => ['id', 'school_id', 'code', 'libelle', 'ordre', 'animateur_personnel_id'],
                'portee' => fn (Builder $q, int $s) => $q->where('school_id', $s),
                'permission' => 'pedagogie.view',
            ],
            'champs_personnalises' => [
                'modele' => ChampPersonnalise::class,
                'colonnes' => ['id', 'classe_matiere_id', 'libelle', 'type', 'ordre'],
                'portee' => fn (Builder $q, int $s) => $q->whereHas('classeMatiere.classe', fn ($c) => $c->where('school_id', $s)),
                'permission' => 'pedagogie.view',
            ],
            'progression_colonnes' => [
                'modele' => ProgressionColonne::class,
                'colonnes' => ['id', 'classe_matiere_id', 'libelle', 'ordre'],
                'portee' => fn (Builder $q, int $s) => $q->whereHas('classeMatiere.classe', fn ($c) => $c->where('school_id', $s)),
                'permission' => 'pedagogie.view',
            ],
            'evaluations' => [
                'modele' => Evaluation::class,
                'colonnes' => ['id', 'school_id', 'classe_matiere_id', 'progression_item_id', 'titre', 'type', 'date_prevue', 'bareme', 'competences', 'cree_par'],
                'portee' => fn (Builder $q, int $s) => $q->where('school_id', $s),
                'permission' => 'pedagogie.view',
            ],
            'evaluation_questions' => [
                'modele' => EvaluationQuestion::class,
                'colonnes' => ['id', 'evaluation_id', 'enonce', 'bareme_question', 'ordre'],
                'portee' => fn (Builder $q, int $s) => $q->whereHas('evaluation', fn ($e) => $e->where('school_id', $s)),
                'permission' => 'pedagogie.view',
            ],

            // --- Personnes.
            'eleves' => [
                'modele' => Eleve::class,
                'colonnes' => ['id', 'school_id', 'classe_id', 'matricule', 'nom_complet', 'sexe', 'date_naissance', 'lieu_naissance', 'nationalite', 'redoublant', 'statut', 'photo_path'],
                'portee' => fn (Builder $q, int $s) => $q->where('school_id', $s)
                    ->when($classesPerimetre !== null, fn (Builder $q2) => $q2->whereIn('classe_id', $classesPerimetre)),
                'permission' => 'eleves.view',
            ],
            'personnels' => [
                'modele' => Personnel::class,
                // Volontairement restreint : le mobile n'a besoin que de quoi
                // afficher « qui enseigne quoi ». Le dossier RH complet (CNI,
                // CNPS, salaire, situation matrimoniale) n'a rien à faire
                // répliqué sur le téléphone de chaque enseignant.
                'colonnes' => ['id', 'school_id', 'departement_id', 'fonction_id', 'matricule', 'nom_complet', 'civilite', 'sexe', 'telephone', 'email', 'statut', 'photo_path'],
                'portee' => fn (Builder $q, int $s) => $q->where('school_id', $s),
                'permission' => 'personnel.view',
            ],
            // Référentiel dont dépend `personnels.fonction_id` — sans lui, la
            // relation `Personnel::fonctionReference()` ne résout jamais rien
            // en local : `estEnseignant()` retombe systématiquement à faux,
            // et le tableau de bord affiche zéro enseignant quel que soit
            // l'effectif réel. Créé par école (cf. migration
            // `create_fonction_referentiel_table`), qui ne peut pas l'avoir
            // fait localement : elle tourne avant que la première école ne
            // soit provisionnée sur ce poste, la table y reste donc vide sans
            // cette synchronisation.
            'fonction_referentiel' => [
                'modele' => FonctionReferentiel::class,
                'colonnes' => ['id', 'school_id', 'label_fr', 'label_en'],
                'portee' => fn (Builder $q, int $s) => $q->where('school_id', $s),
                'permission' => 'personnel.view',
            ],

            // --- Tuteurs (comptes parents). Le compte `User` du portail
            // parent lui-même n'entre pas dans le registre : un poste
            // desktop mono-utilisateur n'a aucune raison de répliquer les
            // identifiants de connexion de chaque tuteur.
            'tuteurs' => [
                'modele' => Tuteur::class,
                'colonnes' => ['id', 'school_id', 'user_id', 'nom_complet', 'telephone', 'email', 'profession', 'lieu_service', 'adresse'],
                'portee' => fn (Builder $q, int $s) => $q->where('school_id', $s),
                'permission' => 'eleves.manage',
            ],
            'eleve_tuteurs' => [
                'modele' => EleveTuteur::class,
                'colonnes' => ['id', 'eleve_id', 'tuteur_id', 'lien_parente', 'is_principal'],
                'portee' => fn (Builder $q, int $s) => $q->whereHas('eleve', fn ($e) => $e->where('school_id', $s)),
                'permission' => 'eleves.manage',
            ],
            'tuteur_telephones' => [
                'modele' => TuteurTelephone::class,
                'colonnes' => ['id', 'tuteur_id', 'numero', 'is_principal'],
                'portee' => fn (Builder $q, int $s) => $q->whereHas('tuteur', fn ($t) => $t->where('school_id', $s)),
                'permission' => 'eleves.manage',
            ],

            // --- Dossier élève : dépôts et échanges avec la famille.
            'preinscriptions' => [
                'modele' => Preinscription::class,
                'colonnes' => ['id', 'school_id', 'tuteur_id', 'eleve_id', 'type', 'statut', 'donnees_eleve', 'donnees_tuteurs', 'note_admin', 'montant_verser', 'mode_versement', 'reference_externe', 'rubriques_versement', 'versement_id', 'motif_rejet', 'traite_par', 'traite_le'],
                'portee' => fn (Builder $q, int $s) => $q->where('school_id', $s),
                'permission' => 'eleves.manage',
            ],
            'modifications_eleves' => [
                'modele' => ModificationEleve::class,
                'colonnes' => ['id', 'school_id', 'eleve_id', 'tuteur_id', 'donnees', 'statut', 'motif_rejet', 'traite_par', 'traite_le'],
                'portee' => fn (Builder $q, int $s) => $q->where('school_id', $s),
                'permission' => 'eleves.manage',
            ],
            'observations' => [
                'modele' => Observation::class,
                'colonnes' => ['id', 'school_id', 'eleve_id', 'user_id', 'contenu'],
                'portee' => fn (Builder $q, int $s) => $q->where('school_id', $s),
                'permission' => 'eleves.view',
            ],
            'justifications_absences' => [
                'modele' => JustificationAbsence::class,
                'colonnes' => ['id', 'school_id', 'eleve_id', 'tuteur_id', 'date_debut', 'date_fin', 'motif', 'description', 'statut', 'presence_id'],
                'portee' => fn (Builder $q, int $s) => $q->where('school_id', $s),
                'permission' => 'emploi_du_temps.view',
            ],
            'absences_trimestre' => [
                'modele' => AbsenceTrimestre::class,
                'colonnes' => ['id', 'eleve_id', 'trimestre_id', 'heures_justifiees', 'heures_non_justifiees', 'mis_a_jour_par'],
                'portee' => fn (Builder $q, int $s) => $q->whereHas('eleve', fn ($e) => $e->where('school_id', $s)),
                'permission' => 'notes.view',
            ],

            // --- Écritures du quotidien : le cœur du hors-ligne.
            'seances' => [
                'modele' => Seance::class,
                'colonnes' => ['id', 'school_id', 'classe_id', 'classe_matiere_id', 'trimestre_id', 'emploi_du_temps_id', 'date_seance', 'heure_debut', 'heure_fin', 'salle', 'contenu', 'observations', 'donnees_personnalisees', 'statut', 'appel_verrouille_le'],
                'portee' => fn (Builder $q, int $s) => $q->where('school_id', $s)
                    ->when($classesPerimetre !== null, fn (Builder $q2) => $q2->whereIn('classe_id', $classesPerimetre)),
                'permission' => 'emploi_du_temps.view',
            ],
            'presences' => [
                'modele' => Presence::class,
                'colonnes' => ['id', 'seance_id', 'eleve_id', 'statut', 'motif', 'justifie', 'remarque'],
                'portee' => fn (Builder $q, int $s) => $q->whereHas('seance', fn ($e) => $e->where('school_id', $s)
                    ->when($classesPerimetre !== null, fn (Builder $e2) => $e2->whereIn('classe_id', $classesPerimetre))),
                'permission' => 'emploi_du_temps.view',
            ],
            'notes' => [
                'modele' => Note::class,
                // `classe_competence_id`/`appreciation_id` couvrent le
                // primaire/maternelle (cf. `classe_competences` et
                // `appreciations` ci-dessus) — `classe_matiere_id` reste seul
                // renseigné au secondaire, les deux couples sont mutuellement
                // exclusifs sur une même ligne.
                'colonnes' => ['id', 'eleve_id', 'classe_matiere_id', 'classe_competence_id', 'sequence_id', 'composante', 'valeur', 'appreciation_id', 'saisi_par'],
                'portee' => fn (Builder $q, int $s) => $q->whereHas('eleve', fn ($e) => $e->where('school_id', $s)
                    ->when($classesPerimetre !== null, fn (Builder $e2) => $e2->whereIn('classe_id', $classesPerimetre))),
                'permission' => 'notes.view',
            ],
            'sanctions' => [
                'modele' => Sanction::class,
                'colonnes' => ['id', 'eleve_id', 'classe_id', 'trimestre_id', 'type', 'duree_jours', 'date_debut', 'date_fin', 'motif', 'commentaire', 'date_sanction', 'statut', 'impacte_bulletin', 'enregistre_par'],
                'portee' => fn (Builder $q, int $s) => $q->whereHas('eleve', fn ($e) => $e->where('school_id', $s)
                    ->when($classesPerimetre !== null, fn (Builder $e2) => $e2->whereIn('classe_id', $classesPerimetre))),
                'permission' => 'discipline.view',
            ],
            'revendications' => [
                'modele' => Revendication::class,
                'colonnes' => ['id', 'eleve_id', 'classe_matiere_id', 'trimestre_id', 'type', 'objet', 'motif', 'statut', 'decision', 'date_reception', 'date_traitement', 'enregistre_par', 'traite_par'],
                'portee' => fn (Builder $q, int $s) => $q->whereHas('eleve', fn ($e) => $e->where('school_id', $s)),
                'permission' => 'revendications.view',
            ],
            'bulletin_publications' => [
                'modele' => BulletinPublication::class,
                'colonnes' => ['id', 'school_id', 'trimestre_id', 'classe_id', 'publie_par', 'publie_le'],
                'portee' => fn (Builder $q, int $s) => $q->where('school_id', $s),
                'permission' => 'bulletins.view',
            ],

            // --- Communication.
            'annonces' => [
                'modele' => Annonce::class,
                'colonnes' => ['id', 'school_id', 'titre', 'contenu', 'publie_par', 'publiee_le', 'cible_type'],
                'portee' => fn (Builder $q, int $s) => $q->where('school_id', $s),
                'permission' => 'annonces.view',
            ],
            'notifications_internes' => [
                'modele' => NotificationInterne::class,
                'colonnes' => ['id', 'school_id', 'user_id', 'type', 'titre', 'message', 'lien', 'lu', 'lu_le'],
                // Filtré sur le destinataire en plus de l'école : une
                // notification est nominative, personne n'a à répliquer celles
                // des autres sur son téléphone.
                'portee' => fn (Builder $q, int $s) => $q->where('school_id', $s)
                    ->where('user_id', auth()->id()),
                'permission' => null,
            ],

            // --- Finance / scolarité. Aucune donnée bancaire structurée dans
            // ce module (le paiement porte un `mode` et une référence texte
            // libre) : rien à exclure au-delà de ce que chaque contrôleur
            // expose déjà en lecture.
            'grilles_frais' => [
                'modele' => GrilleFrais::class,
                'colonnes' => ['id', 'school_id', 'annee_scolaire_id', 'classe_id', 'montant'],
                'portee' => fn (Builder $q, int $s) => $q->where('school_id', $s),
                'permission' => 'finance.view',
            ],
            'frais_annexes' => [
                'modele' => FraisAnnexe::class,
                'colonnes' => ['id', 'school_id', 'annee_scolaire_id', 'libelle', 'montant', 'obligatoire', 'is_active'],
                'portee' => fn (Builder $q, int $s) => $q->where('school_id', $s),
                'permission' => 'finance.view',
            ],
            'dossiers_scolarite' => [
                'modele' => DossierScolarite::class,
                'colonnes' => ['id', 'school_id', 'annee_scolaire_id', 'eleve_id', 'montant_scolarite', 'remise', 'report_dette', 'observation'],
                'portee' => fn (Builder $q, int $s) => $q->where('school_id', $s),
                'permission' => 'finance.view',
            ],
            'dossier_frais_annexes' => [
                'modele' => DossierFraisAnnexe::class,
                'colonnes' => ['id', 'dossier_scolarite_id', 'frais_annexe_id', 'libelle', 'montant'],
                'portee' => fn (Builder $q, int $s) => $q->whereHas('dossier', fn ($d) => $d->where('school_id', $s)),
                'permission' => 'finance.view',
            ],
            'versements' => [
                'modele' => Versement::class,
                'colonnes' => ['id', 'school_id', 'dossier_scolarite_id', 'numero_recu', 'date_versement', 'montant', 'mode', 'reference_externe', 'encaisse_par', 'note', 'annule_le', 'annule_par', 'motif_annulation'],
                'portee' => fn (Builder $q, int $s) => $q->where('school_id', $s),
                'permission' => 'finance.view',
            ],
            'versement_lignes' => [
                'modele' => VersementLigne::class,
                'colonnes' => ['id', 'versement_id', 'affectation', 'dossier_frais_annexe_id', 'libelle', 'montant'],
                'portee' => fn (Builder $q, int $s) => $q->whereHas('versement', fn ($v) => $v->where('school_id', $s)),
                'permission' => 'finance.view',
            ],
            'moratoires' => [
                'modele' => Moratoire::class,
                'colonnes' => ['id', 'school_id', 'eleve_id', 'date_delivrance', 'date_expiration', 'motif', 'accorde_par'],
                'portee' => fn (Builder $q, int $s) => $q->where('school_id', $s),
                'permission' => 'finance.view',
            ],
            'remises' => [
                'modele' => Remise::class,
                'colonnes' => ['id', 'school_id', 'eleve_id', 'annee_scolaire_id', 'montant', 'motif', 'accorde_par'],
                'portee' => fn (Builder $q, int $s) => $q->where('school_id', $s),
                'permission' => 'finance.view',
            ],
            'dettes_anterieures' => [
                'modele' => DetteAnterieure::class,
                'colonnes' => ['id', 'school_id', 'eleve_id', 'montant', 'motif', 'accorde_par', 'imputee_dossier_id'],
                'portee' => fn (Builder $q, int $s) => $q->where('school_id', $s),
                'permission' => 'finance.view',
            ],
            'tranches_scolarite' => [
                'modele' => TrancheScolarite::class,
                'colonnes' => ['id', 'school_id', 'annee_scolaire_id', 'libelle', 'pourcentage', 'date_echeance', 'ordre'],
                'portee' => fn (Builder $q, int $s) => $q->where('school_id', $s),
                'permission' => 'finance.view',
            ],

            // --- Comptabilité.
            'comptes_comptables' => [
                'modele' => CompteComptable::class,
                'colonnes' => ['id', 'code', 'libelle', 'libelle_en', 'classe', 'sens', 'is_active', 'nature', 'assiette', 'montant_unitaire', 'ordre'],
                // Référentiel commun au complexe, comme `niveaux` : aucune
                // colonne d'école à filtrer.
                'portee' => fn (Builder $q, int $s) => $q,
                'permission' => 'finance.view',
            ],
            'ecritures_comptables' => [
                'modele' => EcritureComptable::class,
                'colonnes' => ['id', 'school_id', 'annee_scolaire_id', 'date_ecriture', 'libelle', 'montant', 'sens', 'compte_comptable_id', 'origine_type', 'origine_id'],
                'portee' => fn (Builder $q, int $s) => $q->where('school_id', $s),
                // Pas de `xxx.view` propre au journal (alimenté automatiquement,
                // jamais saisi) : on réutilise le privilège de ses rapports.
                'permission' => 'finance.rapports',
            ],
            'budgets_fonctionnement' => [
                'modele' => BudgetFonctionnement::class,
                'colonnes' => ['id', 'school_id', 'annee_scolaire_id', 'rubrique', 'montant_percu', 'observations'],
                'portee' => fn (Builder $q, int $s) => $q->where('school_id', $s),
                'permission' => 'finance.rapports',
            ],
            'depenses' => [
                'modele' => Depense::class,
                'colonnes' => ['id', 'school_id', 'annee_scolaire_id', 'compte_comptable_id', 'rubrique_budget_fonctionnement', 'vehicule_id', 'budget_personnel_id', 'date_depense', 'libelle', 'montant', 'source', 'mode', 'beneficiaire', 'reference_facture', 'responsable', 'saisi_par', 'justificatif_path', 'statut', 'annule_le', 'annule_par', 'motif_annulation'],
                'portee' => fn (Builder $q, int $s) => $q->where('school_id', $s),
                'permission' => 'finance.depenses',
            ],
            'immobilisations' => [
                'modele' => Immobilisation::class,
                'colonnes' => ['id', 'school_id', 'depense_id', 'compte_comptable_id', 'libelle', 'montant', 'date_mise_en_service', 'duree_annees', 'cede_le'],
                'portee' => fn (Builder $q, int $s) => $q->where('school_id', $s),
                'permission' => 'finance.rapports',
            ],
            'amortissements' => [
                'modele' => Amortissement::class,
                'colonnes' => ['id', 'immobilisation_id', 'annee_scolaire_id', 'montant', 'date_dotation'],
                'portee' => fn (Builder $q, int $s) => $q->whereHas('immobilisation', fn ($i) => $i->where('school_id', $s)),
                'permission' => 'finance.rapports',
            ],

            // --- Rapport de rentrée / vie scolaire annuelle.
            'conseils_ecole' => [
                'modele' => ConseilEcole::class,
                'colonnes' => ['id', 'school_id', 'annee_scolaire_id', 'existe', 'date_ag_elective', 'duree_mandat', 'fin_mandat', 'president_nom', 'president_fonction', 'president_telephone', 'statut_projet_ecole', 'observations'],
                'portee' => fn (Builder $q, int $s) => $q->where('school_id', $s),
                'permission' => 'finance.rapports',
            ],
            'apee' => [
                'modele' => Apee::class,
                'colonnes' => ['id', 'school_id', 'annee_scolaire_id', 'legalisee', 'date_legalisation', 'numero_recepisse', 'banque', 'numero_compte', 'president_nom', 'president_fonction', 'president_telephone', 'date_ag_elective', 'fin_mandat', 'taux_par_eleve', 'montant_percu', 'montant_depense', 'realisations'],
                'portee' => fn (Builder $q, int $s) => $q->where('school_id', $s),
                'permission' => 'finance.rapports',
            ],
            'assurances_scolaires' => [
                'modele' => AssuranceScolaire::class,
                'colonnes' => ['id', 'school_id', 'annee_scolaire_id', 'libelle', 'effectif', 'nom_assureur', 'numero_police'],
                'portee' => fn (Builder $q, int $s) => $q->where('school_id', $s),
                'permission' => 'finance.rapports',
            ],
            'visites_autorites' => [
                'modele' => VisiteAutorite::class,
                'colonnes' => ['id', 'school_id', 'annee_scolaire_id', 'date_visite', 'qualite_autorite', 'nature_visite', 'objectifs', 'observations'],
                'portee' => fn (Builder $q, int $s) => $q->where('school_id', $s),
                'permission' => 'rapport_rentree.view',
            ],
            'activites_rentree' => [
                'modele' => ActiviteRentree::class,
                'colonnes' => ['id', 'school_id', 'annee_scolaire_id', 'categorie', 'activite', 'periode', 'objectifs_vises', 'prevues', 'faites', 'taux_realisation', 'observations'],
                'portee' => fn (Builder $q, int $s) => $q->where('school_id', $s),
                'permission' => 'rapport_rentree.view',
            ],
            'ventes_denrees' => [
                'modele' => VenteDenree::class,
                'colonnes' => ['id', 'school_id', 'annee_scolaire_id', 'nature', 'vendeur_nom', 'dossier_medical_ok', 'frais_verses', 'gestion_frais'],
                'portee' => fn (Builder $q, int $s) => $q->where('school_id', $s),
                'permission' => 'rapport_rentree.view',
            ],
            'rapport_rentree_textes' => [
                'modele' => RapportRentreeTexte::class,
                'colonnes' => ['id', 'school_id', 'annee_scolaire_id', 'rubrique', 'contenu'],
                'portee' => fn (Builder $q, int $s) => $q->where('school_id', $s),
                'permission' => 'rapport_rentree.view',
            ],
            'rapport_trimestre_textes' => [
                'modele' => RapportTrimestreTexte::class,
                'colonnes' => ['id', 'school_id', 'trimestre_id', 'rubrique', 'contenu'],
                'portee' => fn (Builder $q, int $s) => $q->where('school_id', $s),
                'permission' => 'rapport_trimestre.view',
            ],

            // --- Point de vente (fournitures/denrées).
            'ventes_fournitures' => [
                'modele' => VenteFourniture::class,
                'colonnes' => ['id', 'school_id', 'annee_scolaire_id', 'numero_facture', 'date_vente', 'montant', 'mode', 'eleve_id', 'client', 'vendu_par', 'note', 'annule_le', 'annule_par', 'motif_annulation'],
                'portee' => fn (Builder $q, int $s) => $q->where('school_id', $s),
                'permission' => 'point_de_vente.view',
            ],
            'vente_fourniture_lignes' => [
                'modele' => VenteFournitureLigne::class,
                'colonnes' => ['id', 'vente_fourniture_id', 'inventaire_article_id', 'libelle', 'quantite', 'prix_unitaire', 'cout_unitaire'],
                'portee' => fn (Builder $q, int $s) => $q->whereHas('vente', fn ($v) => $v->where('school_id', $s)),
                'permission' => 'point_de_vente.view',
            ],
            'entrees_stock' => [
                'modele' => EntreeStock::class,
                'colonnes' => ['id', 'school_id', 'annee_scolaire_id', 'inventaire_article_id', 'date_entree', 'quantite', 'cout_unitaire', 'fournisseur', 'reference', 'enregistre_par', 'note'],
                'portee' => fn (Builder $q, int $s) => $q->where('school_id', $s),
                'permission' => 'point_de_vente.view',
            ],

            // --- Référentiel infirmerie, sur le même modèle que fonction_referentiel.
            'malaises_referentiel' => [
                'modele' => MalaiseReferentiel::class,
                'colonnes' => ['id', 'school_id', 'label_fr', 'label_en'],
                'portee' => fn (Builder $q, int $s) => $q->where('school_id', $s),
                'permission' => 'infirmerie.view',
            ],

            // --- Registre des numéros de documents officiels (attestations,
            // reçus...) : aucune permission de lecture dédiée, comme
            // `comptes_comptables` — usage interne à la génération de documents.
            'document_references' => [
                'modele' => DocumentReference::class,
                'colonnes' => ['id', 'school_id', 'type', 'annee_scolaire_id', 'numero', 'genere_par'],
                'portee' => fn (Builder $q, int $s) => $q->where('school_id', $s),
                'permission' => null,
            ],

            // --- Paie / RH. Volontairement restreint : `Remuneration` (montants
            // de salaire individuels) n'entre PAS dans le registre — aucun poste
            // desktop, même nominatif, n'a à en garder une copie locale. Les
            // trois entités ci-dessous n'ont pas de privilège fermé : chacune
            // s'ouvre soit au titulaire du dossier (son avance, son budget),
            // soit à qui tient `finance.paie`/`finance.budget` pour tout
            // l'établissement — même logique que `notifications_internes`.
            'avances_salaire' => [
                'modele' => AvanceSalaire::class,
                'colonnes' => ['id', 'school_id', 'personnel_id', 'montant', 'nombre_mois', 'mensualite', 'mois_debut_remboursement', 'date_avance', 'motif', 'annule_le', 'motif_annulation'],
                'portee' => fn (Builder $q, int $s) => $q->where('school_id', $s)
                    ->when(! ($user?->can('finance.paie') ?? false), fn (Builder $q2) => $q2->whereHas('personnel', fn ($p) => $p->where('user_id', $user?->id))),
                'permission' => null,
            ],
            'demandes_avance_salaire' => [
                'modele' => DemandeAvanceSalaire::class,
                'colonnes' => ['id', 'school_id', 'personnel_id', 'montant', 'nombre_mois', 'mensualite', 'mois_debut_remboursement', 'motif', 'statut', 'motif_rejet', 'avance_salaire_id'],
                'portee' => fn (Builder $q, int $s) => $q->where('school_id', $s)
                    ->when(! ($user?->can('finance.paie') ?? false), fn (Builder $q2) => $q2->whereHas('personnel', fn ($p) => $p->where('user_id', $user?->id))),
                'permission' => null,
            ],
            'budgets_personnel' => [
                'modele' => BudgetPersonnel::class,
                'colonnes' => ['id', 'school_id', 'personnel_id', 'annee_scolaire_id', 'libelle', 'montant_alloue', 'date_allocation', 'note_gestion', 'annule_le', 'motif_annulation'],
                'portee' => fn (Builder $q, int $s) => $q->where('school_id', $s)
                    ->when(! ($user?->can('finance.budget') ?? false), fn (Builder $q2) => $q2->whereHas('personnel', fn ($p) => $p->where('user_id', $user?->id))),
                'permission' => null,
            ],

            // --- Bus scolaire.
            'bus_vehicules' => [
                'modele' => BusVehicule::class,
                'colonnes' => ['id', 'school_id', 'immatriculation', 'marque', 'couleur', 'capacite', 'chauffeur_id', 'statut'],
                'portee' => fn (Builder $q, int $s) => $q->where('school_id', $s),
                'permission' => 'bus.view',
            ],
            'bus_trajets' => [
                'modele' => BusTrajet::class,
                'colonnes' => ['id', 'school_id', 'vehicule_id', 'nom', 'description', 'tarif_aller_simple', 'tarif_retour_simple', 'tarif_aller_retour'],
                'portee' => fn (Builder $q, int $s) => $q->where('school_id', $s),
                'permission' => 'bus.view',
            ],
            'bus_arrets' => [
                'modele' => BusArret::class,
                'colonnes' => ['id', 'trajet_id', 'nom', 'lieu_dit', 'ordre', 'heure_passage'],
                'portee' => fn (Builder $q, int $s) => $q->whereHas('trajet', fn ($t) => $t->where('school_id', $s)),
                'permission' => 'bus.view',
            ],
            'bus_affectations' => [
                'modele' => BusAffectation::class,
                'colonnes' => ['id', 'eleve_id', 'trajet_id', 'arret_id', 'annee_scolaire_id', 'tarif_mensuel', 'option_trajet', 'statut'],
                'portee' => fn (Builder $q, int $s) => $q->whereHas('eleve', fn ($e) => $e->where('school_id', $s)),
                'permission' => 'bus.view',
            ],
            'bus_versements' => [
                'modele' => BusVersement::class,
                'colonnes' => ['id', 'school_id', 'bus_affectation_id', 'mois', 'numero_recu', 'date_versement', 'montant', 'mode', 'reference_externe', 'encaisse_par', 'note', 'annule_le', 'annule_par', 'motif_annulation'],
                'portee' => fn (Builder $q, int $s) => $q->where('school_id', $s),
                'permission' => 'bus.view',
            ],

            // --- Infirmerie.
            'visites_infirmerie' => [
                'modele' => VisiteInfirmerie::class,
                'colonnes' => ['id', 'eleve_id', 'classe_id', 'date_visite', 'raison', 'soins_prodiges', 'type_traitement', 'structure_externe', 'cout_soins', 'autre_materiel', 'cout_autre_materiel', 'cout_materiels', 'cout_total', 'observations', 'enregistre_par'],
                'portee' => fn (Builder $q, int $s) => $q->whereHas('eleve', fn ($e) => $e->where('school_id', $s)),
                'permission' => 'infirmerie.view',
            ],
            'visite_infirmerie_materiels' => [
                'modele' => VisiteInfirmerieMateriel::class,
                'colonnes' => ['id', 'visite_infirmerie_id', 'inventaire_article_id', 'nom', 'quantite', 'cout_unitaire', 'cout'],
                'portee' => fn (Builder $q, int $s) => $q->whereHas('visite.eleve', fn ($e) => $e->where('school_id', $s)),
                'permission' => 'infirmerie.view',
            ],

            // --- Inventaire / infrastructures.
            'inventaire_articles' => [
                'modele' => InventaireArticle::class,
                'colonnes' => ['id', 'school_id', 'nom', 'code_barre', 'categorie', 'quantite', 'etat', 'localisation', 'valeur_unitaire', 'prix_vente', 'date_acquisition', 'notes'],
                // Un article sans école (`school_id` NULL) est partagé par tout
                // le complexe — même règle que `InventaireArticle::scopeForSchool()`.
                'portee' => fn (Builder $q, int $s) => $q->where(fn ($w) => $w->where('school_id', $s)->orWhereNull('school_id')),
                'permission' => 'inventaire.view',
            ],
            'infrastructures' => [
                'modele' => Infrastructure::class,
                'colonnes' => ['id', 'school_id', 'type', 'libelle', 'materiau', 'etat', 'quantite', 'besoin_quantite', 'observations'],
                'portee' => fn (Builder $q, int $s) => $q->where('school_id', $s),
                'permission' => 'infrastructures.view',
            ],
            'equipements_mobiliers' => [
                'modele' => EquipementMobilier::class,
                'colonnes' => ['id', 'school_id', 'nature', 'quantite', 'besoin_quantite'],
                'portee' => fn (Builder $q, int $s) => $q->where('school_id', $s),
                'permission' => 'infrastructures.view',
            ],
        ];
    }

    /** @return list<string> */
    public static function cles(): array
    {
        return array_keys(self::entites());
    }

    public static function existe(string $entite): bool
    {
        return array_key_exists($entite, self::entites());
    }

    /**
     * Clé d'entité d'un modèle, pour que l'observer de suppression sache sous
     * quel nom enregistrer la pierre tombale. `null` si le modèle ne fait pas
     * partie du périmètre répliqué — sa suppression n'intéresse personne.
     */
    public static function cleDuModele(object $modele): ?string
    {
        foreach (self::entites() as $cle => $definition) {
            if ($modele::class === $definition['modele']) {
                return $cle;
            }
        }

        return null;
    }

    /**
     * Établissement auquel rattacher la pierre tombale d'une ligne supprimée.
     *
     * Le `portee` ci-dessus filtre une requête ; ici il faut l'information
     * dans l'autre sens, à partir d'une instance unique — d'où ce second
     * aiguillage plutôt qu'une réutilisation des mêmes closures.
     */
    public static function ecoleDe(string $entite, object $m): ?int
    {
        return match ($entite) {
            'annee_scolaires', 'niveaux', 'sous_systemes', 'departements', 'matieres', 'classes',
            'emplois_du_temps', 'eleves', 'personnels', 'fonction_referentiel', 'tuteurs', 'seances',
            'annonces', 'notifications_internes', 'competences', 'appreciations',
            'grilles_frais', 'frais_annexes', 'dossiers_scolarite',
            'versements', 'moratoires', 'remises', 'dettes_anterieures',
            'tranches_scolarite', 'ecritures_comptables', 'budgets_fonctionnement',
            'avances_salaire', 'demandes_avance_salaire', 'budgets_personnel',
            'bus_vehicules', 'bus_trajets', 'bus_versements',
            'inventaire_articles', 'infrastructures', 'equipements_mobiliers',
            'niveaux_scolaires', 'evaluations', 'preinscriptions', 'modifications_eleves',
            'observations', 'justifications_absences', 'depenses', 'immobilisations',
            'conseils_ecole', 'apee', 'assurances_scolaires', 'visites_autorites',
            'activites_rentree', 'ventes_denrees', 'rapport_rentree_textes', 'rapport_trimestre_textes',
            'ventes_fournitures', 'entrees_stock', 'malaises_referentiel',
            'document_references' => $m->school_id,

            'trimestres' => $m->anneeScolaire?->school_id,
            'sequences' => $m->trimestre?->anneeScolaire?->school_id,
            'classe_matieres', 'classe_competences' => $m->classe?->school_id,
            'progression_items', 'champs_personnalises', 'progression_colonnes' => $m->classeMatiere?->classe?->school_id,
            'presences' => $m->seance?->school_id,
            'notes', 'sanctions', 'bus_affectations', 'visites_infirmerie',
            'eleve_tuteurs', 'revendications', 'absences_trimestre' => $m->eleve?->school_id,
            'dossier_frais_annexes' => $m->dossier?->school_id,
            'versement_lignes' => $m->versement?->school_id,
            'bus_arrets' => $m->trajet?->school_id,
            'visite_infirmerie_materiels' => $m->visite?->eleve?->school_id,
            'tuteur_telephones' => $m->tuteur?->school_id,
            'evaluation_questions' => $m->evaluation?->school_id,
            'amortissements' => $m->immobilisation?->school_id,
            'vente_fourniture_lignes' => $m->vente?->school_id,
            // Référentiel commun au complexe : aucune pierre tombale scopée
            // par école n'a de sens pour lui (même statut que `niveaux`
            // partagés, mais sans repli possible ici).
            'comptes_comptables' => null,

            default => null,
        };
    }
}
