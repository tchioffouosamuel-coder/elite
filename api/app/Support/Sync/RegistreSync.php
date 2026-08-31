<?php

namespace App\Support\Sync;

use App\Models\AnneeScolaire;
use App\Models\Annonce;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\Competence;
use App\Models\Eleve;
use App\Models\EmploiDuTemps;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\NotificationInterne;
use App\Models\Note;
use App\Models\Personnel;
use App\Models\Presence;
use App\Models\ProgressionItem;
use App\Models\Sanction;
use App\Models\Seance;
use App\Models\Sequence;
use App\Models\Trimestre;
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
    public static function entites(): array
    {
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
                'portee' => fn (Builder $q, int $s) => $q->whereHas('classe', fn ($c) => $c->where('school_id', $s)),
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
                'portee' => fn (Builder $q, int $s) => $q->whereHas('classeMatiere.classe', fn ($c) => $c->where('school_id', $s)),
                'permission' => 'pedagogie.view',
            ],

            // --- Personnes.
            'eleves' => [
                'modele' => Eleve::class,
                'colonnes' => ['id', 'school_id', 'classe_id', 'matricule', 'nom_complet', 'sexe', 'date_naissance', 'lieu_naissance', 'nationalite', 'redoublant', 'statut', 'photo_path'],
                'portee' => fn (Builder $q, int $s) => $q->where('school_id', $s),
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

            // --- Écritures du quotidien : le cœur du hors-ligne.
            'seances' => [
                'modele' => Seance::class,
                'colonnes' => ['id', 'school_id', 'classe_id', 'classe_matiere_id', 'trimestre_id', 'emploi_du_temps_id', 'date_seance', 'heure_debut', 'heure_fin', 'salle', 'contenu', 'observations', 'donnees_personnalisees', 'statut', 'appel_verrouille_le'],
                'portee' => fn (Builder $q, int $s) => $q->where('school_id', $s),
                'permission' => 'emploi_du_temps.view',
            ],
            'presences' => [
                'modele' => Presence::class,
                'colonnes' => ['id', 'seance_id', 'eleve_id', 'statut', 'motif', 'justifie', 'remarque'],
                'portee' => fn (Builder $q, int $s) => $q->whereHas('seance', fn ($e) => $e->where('school_id', $s)),
                'permission' => 'emploi_du_temps.view',
            ],
            'notes' => [
                'modele' => Note::class,
                'colonnes' => ['id', 'eleve_id', 'classe_matiere_id', 'sequence_id', 'composante', 'valeur', 'saisi_par'],
                'portee' => fn (Builder $q, int $s) => $q->whereHas('eleve', fn ($e) => $e->where('school_id', $s)),
                'permission' => 'notes.view',
            ],
            'sanctions' => [
                'modele' => Sanction::class,
                'colonnes' => ['id', 'eleve_id', 'classe_id', 'trimestre_id', 'type', 'duree_jours', 'date_debut', 'date_fin', 'motif', 'commentaire', 'date_sanction', 'statut', 'impacte_bulletin', 'enregistre_par'],
                'portee' => fn (Builder $q, int $s) => $q->whereHas('eleve', fn ($e) => $e->where('school_id', $s)),
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
            'emplois_du_temps', 'eleves', 'personnels', 'seances',
            'annonces', 'notifications_internes' => $m->school_id,

            'trimestres' => $m->anneeScolaire?->school_id,
            'sequences' => $m->trimestre?->anneeScolaire?->school_id,
            'classe_matieres' => $m->classe?->school_id,
            'progression_items' => $m->classeMatiere?->classe?->school_id,
            'presences' => $m->seance?->school_id,
            'notes', 'sanctions' => $m->eleve?->school_id,

            default => null,
        };
    }
}
