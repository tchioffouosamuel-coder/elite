<?php

namespace App\Support\Sync;

use App\Models\AnneeScolaire;
use App\Models\Annonce;
use App\Models\AvanceSalaire;
use App\Models\BudgetFonctionnement;
use App\Models\BudgetPersonnel;
use App\Models\BusAffectation;
use App\Models\BusArret;
use App\Models\BusTrajet;
use App\Models\BusVehicule;
use App\Models\BusVersement;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\Competence;
use App\Models\CompteComptable;
use App\Models\DemandeAvanceSalaire;
use App\Models\DetteAnterieure;
use App\Models\DossierFraisAnnexe;
use App\Models\DossierScolarite;
use App\Models\EcritureComptable;
use App\Models\Eleve;
use App\Models\EmploiDuTemps;
use App\Models\EquipementMobilier;
use App\Models\FonctionReferentiel;
use App\Models\FraisAnnexe;
use App\Models\GrilleFrais;
use App\Models\Infrastructure;
use App\Models\InventaireArticle;
use App\Models\Matiere;
use App\Models\Moratoire;
use App\Models\Niveau;
use App\Models\NotificationInterne;
use App\Models\Note;
use App\Models\Personnel;
use App\Models\Presence;
use App\Models\ProgressionItem;
use App\Models\Remise;
use App\Models\Sanction;
use App\Models\Seance;
use App\Models\Sequence;
use App\Models\TrancheScolarite;
use App\Models\Trimestre;
use App\Models\User;
use App\Models\Versement;
use App\Models\VersementLigne;
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

            // --- Structure pédagogique.
            'classes' => [
                'modele' => Classe::class,
                'colonnes' => ['id', 'school_id', 'niveau_id', 'niveau_scolaire_id', 'professeur_principal_id', 'titulaire_id', 'surveillant_general_id', 'nom', 'sigle', 'sous_systeme_id', 'niveau_classe', 'filiere', 'capacite', 'qr_token'],
                'portee' => fn (Builder $q, int $s) => $q->where('school_id', $s),
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
                'colonnes' => ['id', 'eleve_id', 'classe_matiere_id', 'sequence_id', 'composante', 'valeur', 'saisi_par'],
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
            'annee_scolaires', 'niveaux', 'matieres', 'classes',
            'emplois_du_temps', 'eleves', 'personnels', 'fonction_referentiel', 'seances',
            'annonces', 'notifications_internes',
            'grilles_frais', 'frais_annexes', 'dossiers_scolarite',
            'versements', 'moratoires', 'remises', 'dettes_anterieures',
            'tranches_scolarite', 'ecritures_comptables', 'budgets_fonctionnement',
            'avances_salaire', 'demandes_avance_salaire', 'budgets_personnel',
            'bus_vehicules', 'bus_trajets', 'bus_versements',
            'inventaire_articles', 'infrastructures', 'equipements_mobiliers' => $m->school_id,

            'trimestres' => $m->anneeScolaire?->school_id,
            'sequences' => $m->trimestre?->anneeScolaire?->school_id,
            'classe_matieres' => $m->classe?->school_id,
            'progression_items' => $m->classeMatiere?->classe?->school_id,
            'presences' => $m->seance?->school_id,
            'notes', 'sanctions', 'bus_affectations', 'visites_infirmerie' => $m->eleve?->school_id,
            'dossier_frais_annexes' => $m->dossier?->school_id,
            'versement_lignes' => $m->versement?->school_id,
            'bus_arrets' => $m->trajet?->school_id,
            'visite_infirmerie_materiels' => $m->visite?->eleve?->school_id,
            // Référentiel commun au complexe : aucune pierre tombale scopée
            // par école n'a de sens pour lui (même statut que `niveaux`
            // partagés, mais sans repli possible ici).
            'comptes_comptables' => null,

            default => null,
        };
    }
}
