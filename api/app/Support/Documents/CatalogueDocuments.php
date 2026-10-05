<?php

namespace App\Support\Documents;

/**
 * Inventaire de TOUS les documents que la plateforme sait produire, pour le
 * centre de documents (cf. DocumentController).
 *
 * Aucun document n'est regénéré ici : chaque entrée pointe vers la route
 * API qui le produit déjà — le centre de documents l'appelle en
 * sous-requête (cf. ExecuteurDocument). Un document ajouté à l'application
 * n'a qu'à être déclaré ici pour devenir disponible, en unitaire comme en
 * paquet.
 *
 * Clés d'une entrée :
 * - `unite` : ce que représente UN fichier — ecole, sous_systeme, classe,
 *   eleve, personnel, vehicule, departement, budget, conseil,
 *   archive_classe, archive_eleve, versement, versement_bus,
 *   preinscription, vente, bulletin_paie (cf. PlanificateurDocuments) ;
 * - `formats` : format => chemin sous `/api/v1/`, avec jetons `{…}` ;
 * - `requete` : paramètres de requête communs à tous les formats ;
 * - `parametres` : réglages demandés à l'utilisateur (trimestre, annee,
 *   periode, paie, date, semaine, liste_classe, liste_personnel,
 *   liste_transport) ;
 * - `filtres` : pour une unité `ecole`, paramètres qui permettent de
 *   descendre plus finement quand le périmètre le demande (classe_id,
 *   sous_systeme_id) — un fichier par classe plutôt qu'un pour l'école ;
 * - `types_ecole` : réservé à ces types d'école (bulletins primaire/secondaire).
 * - `classes_examen` : seules les classes dotées d'un code d'examen.
 *
 * Jetons disponibles : {school}, {classe}, {sous_systeme}, {id} (cible de
 * l'unité), {trimestre_id}, {annee_id}, {du}, {au}, {mois}, {annee},
 * {date}, {semaine}, et ceux des listes personnalisées.
 *
 * Volontairement absents (pas de fichier côté serveur, écrans calculés à
 * l'affichage) : rapports financiers balance/résultat/trésorerie/tableau
 * de bord, rapport des minorités, rapport infrastructures, rapport de mise
 * en place, classements à l'écran. Les documents des espaces parent/élève
 * et les liens de vérification publics sont des doublons de ceux-ci.
 */
final class CatalogueDocuments
{
    public const CATEGORIES = [
        'listes' => 'Listes',
        'listes_personnalisees' => 'Listes personnalisées',
        'pedagogie' => 'Bulletins, résultats & pédagogie',
        'vie_scolaire' => 'Vie scolaire & discipline',
        'effectifs' => 'Effectifs & rapports',
        'finances' => 'Finances',
        'recus' => 'Reçus & factures',
        'personnel' => 'Personnel & paie',
        'archives' => 'Archives',
        'referentiels' => 'Exports de référentiels',
        'modeles' => "Modèles d'import (vierges)",
    ];

    public const UNITES = [
        'ecole' => 'par école',
        'sous_systeme' => 'par sous-système',
        'classe' => 'par classe',
        'eleve' => 'par élève',
        'personnel' => 'par agent',
        'vehicule' => 'par véhicule',
        'departement' => 'par département',
        'budget' => 'par budget',
        'conseil' => 'par conseil de classe',
        'archive_classe' => 'par classe archivée',
        'archive_eleve' => 'par élève archivé',
        'versement' => 'par versement',
        'versement_bus' => 'par versement transport',
        'preinscription' => 'par préinscription payée',
        'vente' => 'par vente',
        'bulletin_paie' => 'par bulletin de paie',
    ];

    public const FORMATS = [
        'pdf' => 'PDF',
        'word' => 'Word',
        'excel' => 'Excel',
        'zip' => 'ZIP',
    ];

    private const LISTE_CLASSE = [
        'titre_fr' => '{titre_fr}', 'titre_en' => '{titre_en}', 'colonnes' => '{colonnes}',
        'moyenne_type' => '{moyenne_type}', 'moyenne_reference_id' => '{moyenne_reference_id}',
    ];

    private const LISTE_SIMPLE = ['titre_fr' => '{titre_fr}', 'titre_en' => '{titre_en}', 'colonnes' => '{colonnes}'];

    /** @return array<string, array<string, mixed>> */
    public static function documents(): array
    {
        $documents = [
            // ─── Listes ──────────────────────────────────────────────────
            'liste_classe' => [
                'libelle' => 'Liste des élèves de la classe', 'categorie' => 'listes', 'unite' => 'classe',
                'formats' => ['pdf' => 'classes/{classe}/eleves/pdf', 'word' => 'classes/{classe}/eleves/word', 'excel' => 'eleves/export?classe_id={classe}'],
            ],
            'liste_ecole' => [
                'libelle' => "Liste générale des élèves de l'école", 'categorie' => 'listes', 'unite' => 'ecole',
                'formats' => ['pdf' => 'eleves/pdf?school_id={school}', 'excel' => 'eleves/export'],
            ],
            'eleves_sans_classe' => [
                'libelle' => 'Élèves sans classe', 'categorie' => 'listes', 'unite' => 'ecole',
                'formats' => ['excel' => 'eleves/export?sans_classe=1'],
            ],
            'liste_classes' => ['libelle' => 'Liste des classes', 'categorie' => 'listes', 'unite' => 'ecole', 'formats' => ['excel' => 'classes/export']],
            'liste_matieres' => [
                'libelle' => 'Matières et coefficients', 'categorie' => 'listes', 'unite' => 'ecole',
                'formats' => ['excel' => 'matieres/export'], 'filtres' => ['classe_id' => 'classe'],
            ],
            'liste_personnel' => ['libelle' => 'Liste du personnel', 'categorie' => 'listes', 'unite' => 'ecole', 'formats' => ['excel' => 'personnels/export']],
            'fichier_personnel' => ['libelle' => 'Fichier du personnel', 'categorie' => 'listes', 'unite' => 'ecole', 'formats' => ['pdf' => 'personnels/fichier']],
            'identifiants_personnel' => ['libelle' => 'Identifiants de connexion du personnel', 'categorie' => 'listes', 'unite' => 'ecole', 'formats' => ['pdf' => 'personnels/identifiants']],
            'identifiants_eleves' => ['libelle' => 'Identifiants de connexion des élèves', 'categorie' => 'listes', 'unite' => 'ecole', 'formats' => ['pdf' => 'eleves/identifiants/pdf']],
            'identifiants_parents' => ['libelle' => 'Identifiants de connexion des parents', 'categorie' => 'listes', 'unite' => 'ecole', 'formats' => ['pdf' => 'tuteurs/identifiants/pdf']],
            'souscriptions_bus' => ['libelle' => 'Souscriptions au transport scolaire', 'categorie' => 'listes', 'unite' => 'ecole', 'formats' => ['excel' => 'bus/affectations/export']],
            'eleves_vehicule' => ['libelle' => 'Élèves transportés par véhicule', 'categorie' => 'listes', 'unite' => 'vehicule', 'formats' => ['pdf' => 'bus/vehicules/{id}/eleves/pdf']],
            'preinscriptions' => ['libelle' => 'Préinscriptions', 'categorie' => 'listes', 'unite' => 'ecole', 'formats' => ['excel' => 'preinscriptions/export']],
            'non_inscrits' => ['libelle' => 'Anciens élèves non encore réinscrits', 'categorie' => 'listes', 'unite' => 'ecole', 'formats' => ['excel' => 'preinscriptions/non-inscrits/export']],
            'anciens_reinscrits' => ['libelle' => 'Anciens élèves réinscrits', 'categorie' => 'listes', 'unite' => 'ecole', 'formats' => ['excel' => 'dashboard/anciens-reinscrits/export']],
            'matricules_nationaux' => ['libelle' => 'Matricules nationaux', 'categorie' => 'listes', 'unite' => 'ecole', 'formats' => ['excel' => 'matricules-nationaux/export']],

            // ─── Listes personnalisées ──────────────────────────────────
            'liste_personnalisee_classe' => [
                'libelle' => 'Liste de classe personnalisée', 'categorie' => 'listes_personnalisees', 'unite' => 'classe',
                'formats' => ['pdf' => 'classes/{classe}/liste-personnalisee/pdf', 'word' => 'classes/{classe}/liste-personnalisee/word', 'excel' => 'classes/{classe}/liste-personnalisee/excel'],
                'requete' => self::LISTE_CLASSE, 'parametres' => ['liste_classe'],
            ],
            'liste_personnalisee_personnel' => [
                'libelle' => 'Liste du personnel personnalisée', 'categorie' => 'listes_personnalisees', 'unite' => 'ecole',
                'formats' => ['pdf' => 'personnels/liste-personnalisee/pdf', 'word' => 'personnels/liste-personnalisee/word', 'excel' => 'personnels/liste-personnalisee/excel'],
                'requete' => [...self::LISTE_SIMPLE, 'statut' => 'actif'], 'parametres' => ['liste_personnel'],
            ],
            'liste_personnalisee_transport' => [
                'libelle' => 'Liste du transport personnalisée', 'categorie' => 'listes_personnalisees', 'unite' => 'ecole',
                'formats' => ['pdf' => 'bus/affectations/liste-personnalisee/pdf', 'word' => 'bus/affectations/liste-personnalisee/word', 'excel' => 'bus/affectations/liste-personnalisee/excel'],
                'requete' => self::LISTE_SIMPLE, 'parametres' => ['liste_transport'], 'filtres' => ['classe_id' => 'classe'],
            ],

            // ─── Bulletins, résultats & pédagogie ───────────────────────
            'bulletins_classe' => [
                'libelle' => 'Bulletins de la classe (secondaire)', 'categorie' => 'pedagogie', 'unite' => 'classe',
                'formats' => ['pdf' => 'classes/{classe}/bulletins'], 'requete' => ['trimestre_id' => '{trimestre_id}'],
                'parametres' => ['trimestre'], 'types_ecole' => ['secondaire'],
            ],
            'bulletins_classe_primaire' => [
                'libelle' => 'Bulletins de la classe (primaire / maternelle)', 'categorie' => 'pedagogie', 'unite' => 'classe',
                'formats' => ['pdf' => 'classes/{classe}/bulletins-primaire'], 'requete' => ['trimestre_id' => '{trimestre_id}'],
                'parametres' => ['trimestre'], 'types_ecole' => ['primaire', 'maternelle'],
            ],
            'bulletin_eleve' => [
                'libelle' => 'Bulletin individuel (secondaire)', 'categorie' => 'pedagogie', 'unite' => 'eleve',
                'formats' => ['pdf' => 'eleves/{id}/bulletin'], 'requete' => ['trimestre_id' => '{trimestre_id}'],
                'parametres' => ['trimestre'], 'types_ecole' => ['secondaire'],
            ],
            'bulletin_eleve_primaire' => [
                'libelle' => 'Bulletin individuel (primaire / maternelle)', 'categorie' => 'pedagogie', 'unite' => 'eleve',
                'formats' => ['pdf' => 'eleves/{id}/bulletin-primaire'], 'requete' => ['trimestre_id' => '{trimestre_id}'],
                'parametres' => ['trimestre'], 'types_ecole' => ['primaire', 'maternelle'],
            ],
            'classement' => [
                'libelle' => 'Classement de la classe', 'categorie' => 'pedagogie', 'unite' => 'classe',
                'formats' => ['excel' => 'classes/{classe}/classement/export'], 'requete' => ['trimestre_id' => '{trimestre_id}'],
                'parametres' => ['trimestre'], 'types_ecole' => ['secondaire'],
            ],
            'palmares' => [
                'libelle' => 'Palmarès', 'categorie' => 'pedagogie', 'unite' => 'ecole',
                'formats' => ['pdf' => 'palmares/pdf', 'excel' => 'palmares/export'], 'requete' => ['trimestre_id' => '{trimestre_id}'],
                'parametres' => ['trimestre'], 'filtres' => ['classe_id' => 'classe'],
            ],
            'statistiques_pedagogiques' => [
                'libelle' => 'Statistiques pédagogiques', 'categorie' => 'pedagogie', 'unite' => 'ecole',
                'formats' => ['pdf' => 'statistiques/pedagogiques/pdf'], 'requete' => ['trimestre_id' => '{trimestre_id}'], 'parametres' => ['trimestre'],
            ],
            'statistiques_departement' => [
                'libelle' => 'Statistiques pédagogiques du département', 'categorie' => 'pedagogie', 'unite' => 'departement',
                'formats' => ['pdf' => 'departements/{id}/statistiques/pedagogiques/export-pdf'], 'requete' => ['trimestre_id' => '{trimestre_id}'], 'parametres' => ['trimestre'],
            ],
            'progression_classe' => ['libelle' => 'Fiches de progression de la classe', 'categorie' => 'pedagogie', 'unite' => 'classe', 'formats' => ['pdf' => 'classes/{classe}/progression/pdf']],
            'emploi_du_temps' => [
                'libelle' => 'Emploi du temps de la classe', 'categorie' => 'pedagogie', 'unite' => 'classe',
                'formats' => ['pdf' => 'classes/{classe}/emploi-du-temps/export-pdf', 'excel' => 'classes/{classe}/emploi-du-temps/export'],
            ],
            'pv_conseil_classe' => ['libelle' => 'Procès-verbal du conseil de classe', 'categorie' => 'pedagogie', 'unite' => 'conseil', 'formats' => ['pdf' => 'conseils-classe/{id}/pv'], 'parametres' => ['annee']],
            'cartes_scolaires' => ['libelle' => 'Cartes scolaires', 'categorie' => 'pedagogie', 'unite' => 'classe', 'formats' => ['pdf' => 'classes/{classe}/cartes-scolaires']],
            'photos_examen' => [
                'libelle' => "Photos d'examen", 'categorie' => 'pedagogie', 'unite' => 'classe',
                'formats' => ['zip' => 'photos-examen/classes/{classe}/archive'], 'classes_examen' => true,
            ],
            'attestation_scolarite' => ['libelle' => 'Attestation de scolarité', 'categorie' => 'pedagogie', 'unite' => 'eleve', 'formats' => ['word' => 'eleves/{id}/attestation-scolarite']],
            'appreciations' => ['libelle' => 'Appréciations', 'categorie' => 'pedagogie', 'unite' => 'ecole', 'formats' => ['excel' => 'appreciations/export']],
            'competences' => ['libelle' => 'Compétences', 'categorie' => 'pedagogie', 'unite' => 'ecole', 'formats' => ['excel' => 'competences/export']],

            // ─── Vie scolaire & discipline ──────────────────────────────
            'fiche_appel' => [
                'libelle' => "Fiche d'appel hebdomadaire", 'categorie' => 'vie_scolaire', 'unite' => 'classe',
                'formats' => ['pdf' => 'classes/{classe}/fiche-appel/pdf'], 'requete' => ['semaine' => '{semaine}'], 'parametres' => ['semaine'],
            ],
            'bilan_disciplinaire' => [
                'libelle' => 'Bilan disciplinaire de la classe', 'categorie' => 'vie_scolaire', 'unite' => 'classe',
                'formats' => ['pdf' => 'classes/{classe}/bilan-disciplinaire/pdf'], 'requete' => ['trimestre_id' => '{trimestre_id}'], 'parametres' => ['trimestre'],
            ],
            'pv_conseil_discipline' => [
                'libelle' => 'PV du conseil de discipline', 'categorie' => 'vie_scolaire', 'unite' => 'ecole',
                'formats' => ['pdf' => 'sanctions/pv-conseil/pdf'], 'requete' => ['trimestre_id' => '{trimestre_id}'],
                // Maternelle et primaire ne prononcent pas de sanctions (refus de la route elle-même).
                'parametres' => ['trimestre'], 'filtres' => ['classe_id' => 'classe'], 'types_ecole' => ['secondaire'],
            ],
            'statistiques_disciplinaires' => [
                'libelle' => 'Statistiques disciplinaires', 'categorie' => 'vie_scolaire', 'unite' => 'ecole',
                'formats' => ['pdf' => 'statistiques/disciplinaires/pdf'], 'requete' => ['trimestre_id' => '{trimestre_id}'], 'parametres' => ['trimestre'],
            ],
            'visites_infirmerie' => ['libelle' => "Visites à l'infirmerie", 'categorie' => 'vie_scolaire', 'unite' => 'ecole', 'formats' => ['excel' => 'infirmerie/visites/export']],

            // ─── Effectifs & rapports ───────────────────────────────────
            'recapitulatif_effectifs' => [
                'libelle' => "Récapitulatif d'effectifs", 'categorie' => 'effectifs', 'unite' => 'ecole',
                'formats' => ['pdf' => 'eleves/recapitulatif-effectifs/pdf?school_id={school}'], 'filtres' => ['classe_id' => 'classe'],
            ],
            'recapitulatif_sous_systemes' => [
                'libelle' => "Récapitulatif d'effectifs par sous-système", 'categorie' => 'effectifs', 'unite' => 'ecole',
                'formats' => ['pdf' => 'eleves/recapitulatif-sous-systemes/pdf?school_id={school}'], 'filtres' => ['sous_systeme_id' => 'sous_systeme'],
            ],
            'tableau_ages' => [
                'libelle' => 'Tableau des âges', 'categorie' => 'effectifs', 'unite' => 'ecole',
                'formats' => ['pdf' => 'eleves/tableau-ages/pdf?school_id={school}'], 'filtres' => ['classe_id' => 'classe', 'sous_systeme_id' => 'sous_systeme'],
            ],
            'rapport_rentree' => ['libelle' => 'Rapport de rentrée scolaire', 'categorie' => 'effectifs', 'unite' => 'ecole', 'formats' => ['pdf' => 'rapport-rentree/complet/pdf', 'word' => 'rapport-rentree/complet/docx']],
            'rapport_trimestre' => [
                'libelle' => 'Rapport de fin de trimestre', 'categorie' => 'effectifs', 'unite' => 'ecole',
                'formats' => ['word' => 'rapport-trimestre/complet/docx'], 'requete' => ['trimestre_id' => '{trimestre_id}'], 'parametres' => ['trimestre'],
            ],
            'infrastructures' => ['libelle' => 'Infrastructures', 'categorie' => 'effectifs', 'unite' => 'ecole', 'formats' => ['excel' => 'infrastructures/export']],
            'equipements' => ['libelle' => 'Équipements et mobilier', 'categorie' => 'effectifs', 'unite' => 'ecole', 'formats' => ['excel' => 'infrastructures/equipements/export']],
            'inventaire' => ['libelle' => 'Inventaire', 'categorie' => 'effectifs', 'unite' => 'ecole', 'formats' => ['excel' => 'inventaire/export']],

            // ─── Finances ───────────────────────────────────────────────
            'situation_caisse' => [
                'libelle' => 'Situation de caisse (scolarité)', 'categorie' => 'finances', 'unite' => 'ecole',
                'formats' => ['excel' => 'scolarite/situation/export'], 'filtres' => ['classe_id' => 'classe'],
            ],
            'insolvables' => [
                'libelle' => 'Liste des insolvables', 'categorie' => 'finances', 'unite' => 'ecole',
                'formats' => ['pdf' => 'finance/insolvables/pdf', 'excel' => 'finance/insolvables/excel'], 'filtres' => ['classe_id' => 'classe'],
            ],
            'dettes_anterieures' => [
                'libelle' => 'Dettes antérieures', 'categorie' => 'finances', 'unite' => 'ecole',
                'formats' => ['pdf' => 'finance/dettes-anterieures/pdf', 'excel' => 'finance/dettes-anterieures/excel'], 'filtres' => ['classe_id' => 'classe'],
            ],
            'remises' => [
                'libelle' => 'Remises accordées', 'categorie' => 'finances', 'unite' => 'ecole',
                'formats' => ['pdf' => 'finance/remises/pdf'], 'requete' => ['annee_scolaire_id' => '{annee_id}'],
                'parametres' => ['annee'], 'filtres' => ['classe_id' => 'classe'],
            ],
            'bilan_depenses' => [
                'libelle' => 'Bilan des dépenses', 'categorie' => 'finances', 'unite' => 'ecole',
                'formats' => ['pdf' => 'depenses/bilan/pdf'], 'requete' => ['du' => '{du}', 'au' => '{au}'], 'parametres' => ['periode'],
            ],
            'etat_synthese' => [
                'libelle' => 'État de synthèse', 'categorie' => 'finances', 'unite' => 'ecole',
                'formats' => ['pdf' => 'etat-synthese/pdf?school_id={school}&annee_scolaire_id={annee_id}'], 'parametres' => ['annee'],
            ],
            'etat_synthese_serie' => ['libelle' => 'État de synthèse pluriannuel', 'categorie' => 'finances', 'unite' => 'ecole', 'formats' => ['pdf' => 'etat-synthese/serie/pdf?school_id={school}']],
            'bilan_vehicule' => [
                'libelle' => 'Bilan financier du véhicule', 'categorie' => 'finances', 'unite' => 'vehicule',
                'formats' => ['pdf' => 'bus/vehicules/{id}/bilan/pdf'], 'requete' => ['du' => '{du}', 'au' => '{au}'], 'parametres' => ['periode'],
            ],
            'bilan_budget' => [
                'libelle' => 'Bilan de budget de fonctionnement', 'categorie' => 'finances', 'unite' => 'budget',
                'formats' => ['pdf' => 'budgets-personnel/{id}/bilan/pdf'], 'requete' => ['du' => '{du}', 'au' => '{au}'], 'parametres' => ['annee', 'periode'],
            ],
            'grille_frais' => ['libelle' => 'Grille des frais', 'categorie' => 'finances', 'unite' => 'ecole', 'formats' => ['excel' => 'tarifs/grille-frais/export']],
            'frais_annexes' => ['libelle' => 'Frais annexes', 'categorie' => 'finances', 'unite' => 'ecole', 'formats' => ['excel' => 'tarifs/frais-annexes/export']],
            'tranches_scolarite' => ['libelle' => 'Tranches de scolarité', 'categorie' => 'finances', 'unite' => 'ecole', 'formats' => ['excel' => 'tranches-scolarite/export']],

            // ─── Reçus & factures ───────────────────────────────────────
            'recu_versement' => ['libelle' => 'Reçus de versement (scolarité)', 'categorie' => 'recus', 'unite' => 'versement', 'formats' => ['pdf' => 'versements/{id}/recu'], 'parametres' => ['periode']],
            'recu_transport' => ['libelle' => 'Reçus de versement (transport)', 'categorie' => 'recus', 'unite' => 'versement_bus', 'formats' => ['pdf' => 'bus/versements/{id}/recu'], 'parametres' => ['periode']],
            'recu_preinscription' => ['libelle' => 'Reçus de préinscription', 'categorie' => 'recus', 'unite' => 'preinscription', 'formats' => ['pdf' => 'preinscriptions/{id}/recu'], 'parametres' => ['periode']],
            'facture_vente' => ['libelle' => 'Factures du point de vente', 'categorie' => 'recus', 'unite' => 'vente', 'formats' => ['pdf' => 'point-de-vente/ventes/{id}/facture'], 'parametres' => ['periode']],

            // ─── Personnel & paie ───────────────────────────────────────
            'fiche_identification' => [
                'libelle' => "Fiche d'identification de l'agent", 'categorie' => 'personnel', 'unite' => 'personnel',
                'formats' => ['pdf' => 'personnels/{id}/fiche-identification/pdf', 'word' => 'personnels/{id}/fiche-identification/word'],
            ],
            'attestation_employeur' => ['libelle' => 'Attestation employeur', 'categorie' => 'personnel', 'unite' => 'personnel', 'formats' => ['word' => 'personnels/{id}/attestation-employeur']],
            'bulletin_paie' => ['libelle' => 'Bulletins de paie', 'categorie' => 'personnel', 'unite' => 'bulletin_paie', 'formats' => ['pdf' => 'paie/bulletins/{id}/pdf'], 'parametres' => ['paie']],
            'bordereau_virement' => [
                'libelle' => 'Bordereau de virement des salaires', 'categorie' => 'personnel', 'unite' => 'ecole',
                'formats' => ['pdf' => 'paie/bordereau/pdf?school_id={school}&annee={annee}&mois={mois}'], 'parametres' => ['paie'],
            ],
            'etat_emargement' => [
                'libelle' => "État d'émargement", 'categorie' => 'personnel', 'unite' => 'ecole',
                'formats' => ['pdf' => 'paie/etat-emargement?annee={annee}&mois={mois}'], 'parametres' => ['paie'],
            ],
            'fiche_presence' => [
                'libelle' => 'Fiche de présence journalière du personnel', 'categorie' => 'personnel', 'unite' => 'ecole',
                'formats' => ['pdf' => 'personnels/presences-journalieres/fiche'], 'requete' => ['date' => '{date}'], 'parametres' => ['date'],
            ],
            'presences_personnel' => [
                'libelle' => 'Présences journalières du personnel', 'categorie' => 'personnel', 'unite' => 'ecole',
                'formats' => ['excel' => 'personnels/presences-journalieres/export'], 'requete' => ['date_debut' => '{du}', 'date_fin' => '{au}'], 'parametres' => ['periode'],
            ],

            // ─── Archives ───────────────────────────────────────────────
            'pv_archive' => [
                'libelle' => 'PV de conseil archivé', 'categorie' => 'archives', 'unite' => 'archive_classe',
                'formats' => ['pdf' => 'archives/annees/{annee_id}/classes/{id}/pv'], 'parametres' => ['annee'],
            ],
            'bulletin_archive' => [
                'libelle' => 'Bulletin annuel archivé', 'categorie' => 'archives', 'unite' => 'archive_eleve',
                'formats' => ['pdf' => 'archives/annees/{annee_id}/classes/{classe}/bulletin/{id}'], 'parametres' => ['annee'],
            ],

            // ─── Exports de référentiels ────────────────────────────────
            'ref_departements' => ['libelle' => 'Départements', 'categorie' => 'referentiels', 'unite' => 'ecole', 'formats' => ['excel' => 'departements/export']],
            'ref_fonctions' => ['libelle' => 'Fonctions', 'categorie' => 'referentiels', 'unite' => 'ecole', 'formats' => ['excel' => 'fonctions-referentiel/export']],
            'ref_banques' => ['libelle' => 'Banques', 'categorie' => 'referentiels', 'unite' => 'ecole', 'formats' => ['excel' => 'banques/export']],
            'ref_sous_systemes' => ['libelle' => 'Sous-systèmes', 'categorie' => 'referentiels', 'unite' => 'ecole', 'formats' => ['excel' => 'sous-systemes/export']],
            'ref_niveaux' => ['libelle' => 'Niveaux', 'categorie' => 'referentiels', 'unite' => 'ecole', 'formats' => ['excel' => 'niveaux/export']],
            'ref_vehicules' => ['libelle' => 'Véhicules de transport', 'categorie' => 'referentiels', 'unite' => 'ecole', 'formats' => ['excel' => 'bus/vehicules/export']],
        ];

        return $documents + self::modelesImport();
    }

    /**
     * Fichiers Excel vierges servant à l'import — un par module importable.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function modelesImport(): array
    {
        $modeles = [
            'eleves' => 'Élèves', 'classes' => 'Classes', 'personnels' => 'Personnel', 'preinscriptions' => 'Préinscriptions',
            'matricules-nationaux' => 'Matricules nationaux', 'appreciations' => 'Appréciations', 'competences' => 'Compétences',
            'banques' => 'Banques', 'departements' => 'Départements', 'fonctions-referentiel' => 'Fonctions',
            'sous-systemes' => 'Sous-systèmes', 'niveaux' => 'Niveaux', 'tranches-scolarite' => 'Tranches de scolarité',
            'tarifs/grille-frais' => 'Grille des frais', 'tarifs/frais-annexes' => 'Frais annexes', 'remunerations' => 'Rémunérations',
            'bus/vehicules' => 'Véhicules', 'bus/affectations' => 'Souscriptions transport', 'infirmerie/visites' => 'Visites infirmerie',
            'infrastructures' => 'Infrastructures', 'infrastructures/equipements' => 'Équipements', 'inventaire' => 'Inventaire',
            'personnels/presences-journalieres' => 'Présences du personnel',
        ];

        $documents = [];
        foreach ($modeles as $chemin => $libelle) {
            $documents['modele_'.str_replace(['/', '-'], '_', $chemin)] = [
                'libelle' => "Modèle d'import — {$libelle}", 'categorie' => 'modeles', 'unite' => 'ecole',
                'formats' => ['excel' => "{$chemin}/modele"],
            ];
        }

        $documents['modele_progression_classe'] = [
            'libelle' => "Modèle d'import — Progression de la classe", 'categorie' => 'modeles', 'unite' => 'classe',
            'formats' => ['excel' => 'classes/{classe}/progression/modele'],
        ];

        return $documents;
    }

    /** @return array<string, mixed>|null */
    public static function trouver(string $code): ?array
    {
        return self::documents()[$code] ?? null;
    }
}
