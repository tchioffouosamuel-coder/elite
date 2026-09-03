<?php

namespace App\Services;

use App\Models\AnneeScolaire;
use App\Models\Eleve;
use App\Models\Preinscription;
use App\Models\School;
use App\Models\Tuteur;
use App\Models\TuteurTelephone;
use RuntimeException;

/**
 * Préinscriptions déposées par un parent — pour un enfant déjà scolarisé
 * (révision de sa fiche, éventuellement accompagnée d'un versement) ou pour
 * un nouvel enfant.
 *
 * Rien n'atteint `eleves`/`tuteurs` à la soumission : {@see soumettre()} ne
 * fait qu'enregistrer la proposition. Seul {@see valider()} l'applique, et
 * c'est aussi lui qui encaisse réellement — un parent ne peut pas se
 * délivrer un reçu à lui-même.
 */
class PreinscriptionService extends BaseService
{
    public function __construct(
        private readonly ScolariteService $scolarite,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * @param  array{
     *   type: string, eleve_id?: ?int, school_id?: ?int,
     *   donnees_eleve: array, donnees_tuteurs: array, note_admin?: ?string,
     *   montant_verser?: ?int, mode_versement?: ?string, reference_externe?: ?string, rubriques_versement?: ?array,
     * }  $donnees
     */
    public function soumettre(Tuteur $tuteur, array $donnees): Preinscription
    {
        $type = $donnees['type'];

        if ($type === 'existant') {
            $eleve = Eleve::findOrFail($donnees['eleve_id']);

            if (! $tuteur->eleves()->where('eleves.id', $eleve->id)->exists()) {
                throw new RuntimeException("Cet élève n'est pas rattaché à votre compte.");
            }

            // Une demande en attente vaut déjà pour cet élève : en déposer une
            // seconde ne ferait que dupliquer la file d'attente de l'admin.
            // Le parent doit corriger celle qui existe déjà, pas en ouvrir
            // une autre.
            if (Preinscription::where('eleve_id', $eleve->id)->where('statut', 'en_attente')->exists()) {
                throw new RuntimeException(
                    "Une préinscription est déjà en attente de validation pour cet élève. Vous pouvez la modifier tant qu'elle n'a pas été traitée."
                );
            }

            $schoolId = $eleve->school_id;
        } else {
            // Une nouvelle inscription peut viser n'importe quelle école du
            // complexe du tuteur, pas seulement la sienne — un parent inscrit
            // parfois un cadet dans un autre établissement du groupe.
            $schoolId = (int) ($donnees['school_id'] ?? $tuteur->school_id);
            $ecole = School::findOrFail($schoolId);

            if ($ecole->complexe_id !== $tuteur->school->complexe_id) {
                throw new RuntimeException("Cet établissement n'appartient pas au même complexe.");
            }

            // Même garde-fou que ci-dessus, pour un enfant pas encore
            // scolarisé : sans identifiant d'élève à comparer, on rapproche
            // sur nom + date de naissance parmi les demandes du même tuteur.
            $doublon = Preinscription::where('tuteur_id', $tuteur->id)
                ->where('type', 'nouveau')
                ->where('statut', 'en_attente')
                ->get()
                ->contains(
                    fn (Preinscription $p) => ($p->donnees_eleve['nom_complet'] ?? null) === ($donnees['donnees_eleve']['nom_complet'] ?? null)
                        && ($p->donnees_eleve['date_naissance'] ?? null) === ($donnees['donnees_eleve']['date_naissance'] ?? null)
                );

            if ($doublon) {
                throw new RuntimeException(
                    "Une préinscription est déjà en attente de validation pour cet enfant. Vous pouvez la modifier tant qu'elle n'a pas été traitée."
                );
            }

            $eleve = null;
        }

        $preinscription = Preinscription::create([
            'school_id' => $schoolId,
            'tuteur_id' => $tuteur->id,
            'eleve_id' => $eleve?->id,
            'type' => $type,
            'statut' => 'en_attente',
            'donnees_eleve' => $donnees['donnees_eleve'],
            'donnees_tuteurs' => $donnees['donnees_tuteurs'],
            'note_admin' => $donnees['note_admin'] ?? null,
            'montant_verser' => $donnees['montant_verser'] ?? null,
            'mode_versement' => $donnees['mode_versement'] ?? null,
            'reference_externe' => $donnees['reference_externe'] ?? null,
            'rubriques_versement' => $donnees['rubriques_versement'] ?? null,
        ]);

        $nomPropose = $donnees['donnees_eleve']['nom_complet'] ?? $eleve?->nom_complet ?? 'un enfant';

        $this->notifications->notifierParPermission(
            $schoolId,
            'eleves.manage',
            'preinscription',
            'Nouvelle préinscription déposée',
            $type === 'nouveau'
                ? "{$tuteur->nom_complet} propose l'inscription de {$nomPropose}."
                : "{$tuteur->nom_complet} propose une révision de la fiche de {$nomPropose}.",
            "/preinscriptions/{$preinscription->id}",
        );

        return $preinscription;
    }

    /**
     * Applique la proposition aux tables réelles : met à jour ou crée
     * l'élève, synchronise les tuteurs, encaisse le versement annoncé s'il y
     * en a un — dans cet ordre, pour qu'un dossier de scolarité ait un élève
     * à qui s'ouvrir avant que l'encaissement ne le réclame.
     */
    public function valider(Preinscription $preinscription, ?int $adminUserId = null): Preinscription
    {
        // Une préinscription rejetée reste validable après coup — un rejet
        // n'est pas forcément définitif (pièce manquante depuis fournie,
        // erreur d'appréciation) — mais une fois validée, plus question d'y
        // revenir : l'élève et l'encaissement associés existent déjà.
        if (! in_array($preinscription->statut, ['en_attente', 'rejetee'], true)) {
            throw new RuntimeException('Cette préinscription a déjà été traitée.');
        }

        return $this->transaction(function () use ($preinscription, $adminUserId) {
            $eleve = $preinscription->type === 'existant'
                ? $this->appliquerSurExistant($preinscription)
                : $this->creerNouvelEleve($preinscription);

            $this->synchroniserTuteurs($eleve, $preinscription->school_id, $preinscription->donnees_tuteurs);

            $versementId = null;

            if ($preinscription->type === 'existant' && ($preinscription->montant_verser ?? 0) > 0) {
                $annee = AnneeScolaire::where('school_id', $preinscription->school_id)->where('is_active', true)->firstOrFail();
                $dossier = $this->scolarite->dossier($eleve, $annee);

                $versement = $this->scolarite->encaisser($dossier, [
                    'montant' => $preinscription->montant_verser,
                    'mode' => $preinscription->mode_versement ?? 'especes',
                    'reference_externe' => $preinscription->reference_externe,
                    'note' => 'Versement initié par le parent à la préinscription.',
                    'lignes' => $preinscription->rubriques_versement,
                ], $adminUserId);

                $versementId = $versement->id;
            }

            $preinscription->update([
                'eleve_id' => $eleve->id,
                'statut' => 'validee',
                'versement_id' => $versementId,
                'traite_par' => $adminUserId,
                'traite_le' => now(),
            ]);

            return $preinscription->fresh();
        });
    }

    /**
     * Préinscription créée et validée d'un même geste par l'admin, pour un
     * élève déjà connu du système — la réinscription de fin d'année, saisie
     * directement au guichet plutôt que déposée puis examinée. Contrairement
     * à {@see soumettre()}, elle ne passe jamais par `en_attente` : la ligne
     * de la file d'attente n'existe que pour l'historique, déjà `validee` à
     * la création.
     *
     * `tuteur_id` est une colonne obligatoire de la table (l'auteur de la
     * demande, côté parent) : ici, il n'y a pas de demandeur, donc on y met
     * le tuteur principal de l'élève — celui qu'un dossier existant porte
     * forcément, sans quoi il n'aurait pas pu être inscrit la première fois.
     *
     * @param  array{
     *   donnees_eleve: array, donnees_tuteurs: array, classe_id?: ?int,
     *   montant_verser?: ?int, mode_versement?: ?string, reference_externe?: ?string, rubriques_versement?: ?array,
     * }  $donnees
     */
    public function creerEtValiderParAdmin(Eleve $eleve, array $donnees, int $adminUserId): Preinscription
    {
        $tuteurId = $eleve->tuteurs()->wherePivot('is_principal', true)->value('tuteurs.id')
            ?? $eleve->tuteurs()->value('tuteurs.id');

        if ($tuteurId === null) {
            throw new RuntimeException(
                "Cet élève n'a aucun tuteur enregistré : ajoutez-en un depuis sa fiche avant de le réinscrire."
            );
        }

        return $this->transaction(function () use ($eleve, $tuteurId, $donnees, $adminUserId) {
            $preinscription = Preinscription::create([
                'school_id' => $eleve->school_id,
                'tuteur_id' => $tuteurId,
                'eleve_id' => $eleve->id,
                'type' => 'existant',
                'statut' => 'en_attente',
                'donnees_eleve' => $donnees['donnees_eleve'],
                'donnees_tuteurs' => $donnees['donnees_tuteurs'],
                'classe_id' => $donnees['classe_id'] ?? null,
                'montant_verser' => $donnees['montant_verser'] ?? null,
                'mode_versement' => $donnees['mode_versement'] ?? null,
                'reference_externe' => $donnees['reference_externe'] ?? null,
                'rubriques_versement' => $donnees['rubriques_versement'] ?? null,
            ]);

            return $this->valider($preinscription, $adminUserId);
        });
    }

    /**
     * Corrige les informations d'une préinscription avant validation — une
     * coquille ou un champ mal orthographié ne doit pas obliger à rejeter puis
     * à faire redéposer toute la démarche. Fermé dès que la préinscription est
     * traitée : au-delà, c'est la fiche élève elle-même qu'il faut corriger.
     *
     * Sert aussi bien l'admin (qui ne corrige que l'élève, les tuteurs et la
     * classe visée) que le parent (qui peut en plus revoir le versement
     * annoncé) : `$extra` ne porte que les clés que l'appelant fournit — en
     * particulier `classe_id`, jamais présent dans les données validées côté
     * parent, qui ne peut donc jamais l'atteindre par ce biais.
     */
    public function modifierDonnees(Preinscription $preinscription, array $donneesEleve, array $donneesTuteurs, array $extra = []): Preinscription
    {
        if ($preinscription->statut !== 'en_attente') {
            throw new RuntimeException('Cette préinscription a déjà été traitée.');
        }

        $preinscription->update([
            'donnees_eleve' => $donneesEleve,
            'donnees_tuteurs' => $donneesTuteurs,
            ...array_intersect_key($extra, array_flip([
                'classe_id', 'note_admin', 'montant_verser', 'mode_versement', 'reference_externe', 'rubriques_versement',
            ])),
        ]);

        return $preinscription->fresh();
    }

    public function rejeter(Preinscription $preinscription, string $motif, ?int $adminUserId = null): Preinscription
    {
        if ($preinscription->statut !== 'en_attente') {
            throw new RuntimeException('Cette préinscription a déjà été traitée.');
        }

        $preinscription->update([
            'statut' => 'rejetee',
            'motif_rejet' => $motif,
            'traite_par' => $adminUserId,
            'traite_le' => now(),
        ]);

        return $preinscription->fresh();
    }

    /**
     * La classe ne suit jamais `donnees_eleve` ici, même si le parent en a
     * proposé une : ce n'est qu'une note à son attention (`note_admin`), pas
     * un champ qu'il peut modifier lui-même. Seul `classe_id` — renseigné
     * explicitement par l'admin, cf. {@see modifierClasse()} — vaut décision
     * de changer la classe ; `null` laisse la classe actuelle de l'élève
     * inchangée.
     */
    private function appliquerSurExistant(Preinscription $preinscription): Eleve
    {
        $eleve = Eleve::findOrFail($preinscription->eleve_id);
        $donnees = $preinscription->donnees_eleve;
        unset($donnees['classe_id']);

        if ($preinscription->classe_id !== null) {
            $donnees['classe_id'] = $preinscription->classe_id;
        }

        $eleve->update($donnees);

        return $eleve->fresh();
    }

    /**
     * `classe_id` prime sur celle proposée dans `donnees_eleve` : c'est ce
     * que l'admin a choisi en dernier lieu s'il a corrigé la proposition du
     * parent (cf. {@see modifierClasse()}), sinon celle-ci reste la valeur
     * par défaut pour une nouvelle inscription.
     */
    private function creerNouvelEleve(Preinscription $preinscription): Eleve
    {
        $donnees = $preinscription->donnees_eleve;

        if ($preinscription->classe_id !== null) {
            $donnees['classe_id'] = $preinscription->classe_id;
        }

        return Eleve::create([
            ...$donnees,
            'school_id' => $preinscription->school_id,
            'matricule' => Eleve::genererMatricule($preinscription->school_id),
            'statut' => 'actif',
        ]);
    }

    /**
     * Additif plutôt que « détache tout puis recrée » : un parent qui soumet
     * sa préinscription ne connaît pas forcément les coordonnées complètes de
     * l'autre parent déjà rattaché, et ne doit pas pouvoir le débrancher de
     * la fiche par une simple omission.
     */
    private function synchroniserTuteurs(Eleve $eleve, int $schoolId, array $tuteurs): void
    {
        foreach ($tuteurs as $data) {
            $attributs = [
                'nom_complet' => $data['nom_complet'],
                'email' => $data['email'] ?? null,
                'profession' => $data['profession'] ?? null,
                'lieu_service' => $data['lieu_service'] ?? null,
                'adresse' => $data['adresse'] ?? null,
            ];

            $telephonePrincipal = $this->extrairePrincipal($data);

            $tuteur = $telephonePrincipal
                ? Tuteur::updateOrCreate(['school_id' => $schoolId, 'telephone' => $telephonePrincipal], $attributs)
                : Tuteur::create([...$attributs, 'school_id' => $schoolId]);

            $this->syncTelephonesTuteur($tuteur, $data);

            $eleve->tuteurs()->syncWithoutDetaching([
                $tuteur->id => [
                    'lien_parente' => $data['lien_parente'] ?? null,
                    'is_principal' => $data['is_principal'] ?? false,
                ],
            ]);
        }
    }

    /** Le numéro flaggé `is_principal` dans `telephones`, sinon le premier, sinon l'ancien champ `telephone` unique. */
    private function extrairePrincipal(array $data): ?string
    {
        $telephones = $data['telephones'] ?? [];

        if ($telephones !== []) {
            $principal = collect($telephones)->first(fn ($tel) => ! empty($tel['is_principal'])) ?? $telephones[0];

            return $principal['numero'] ?? null;
        }

        return $data['telephone'] ?? null;
    }

    /**
     * Remplace les numéros du tuteur par ceux soumis (au moins un principal)
     * et recopie le principal dans l'ancien champ `tuteurs.telephone` —
     * encore lu par la recherche rapide, les SMS et la connexion au portail
     * parent.
     */
    private function syncTelephonesTuteur(Tuteur $tuteur, array $data): void
    {
        $telephones = $data['telephones'] ?? [];

        if ($telephones === []) {
            if (! empty($data['telephone']) && $tuteur->telephones()->doesntExist()) {
                TuteurTelephone::create(['tuteur_id' => $tuteur->id, 'numero' => $data['telephone'], 'is_principal' => true]);
            }

            return;
        }

        $tuteur->telephones()->delete();

        $aUnPrincipal = collect($telephones)->contains(fn ($tel) => ! empty($tel['is_principal']));

        foreach ($telephones as $index => $tel) {
            TuteurTelephone::create([
                'tuteur_id' => $tuteur->id,
                'numero' => $tel['numero'],
                'is_principal' => $aUnPrincipal ? ! empty($tel['is_principal']) : $index === 0,
            ]);
        }
    }
}
