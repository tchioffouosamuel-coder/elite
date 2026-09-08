<?php

namespace App\Services;

use App\Imports\PreinscriptionImport;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\DetteAnterieure;
use App\Models\DossierScolarite;
use App\Models\Eleve;
use App\Models\Preinscription;
use App\Models\Remise;
use App\Models\School;
use App\Models\Tuteur;
use App\Models\TuteurTelephone;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
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
                    fn(Preinscription $p) => ($p->donnees_eleve['nom_complet'] ?? null) === ($donnees['donnees_eleve']['nom_complet'] ?? null)
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
            'annee_scolaire_id' => $this->anneeActive($schoolId)?->id,
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
            $donneesImport = $preinscription->donnees_eleve['_import_financier'] ?? [];
            $eleve = $preinscription->type === 'existant'
                ? $this->appliquerSurExistant($preinscription)
                : $this->creerNouvelEleve($preinscription);

            $this->synchroniserTuteurs($eleve, $preinscription->school_id, $preinscription->donnees_tuteurs);

            $versementId = null;

            // Confirmer sa présence pour l'année, pour un élève déjà scolarisé,
            // doit garantir un dossier financier à jour dès la validation — pas
            // seulement quand un versement est annoncé, sinon la conversion de
            // la dette antérieure (ci-dessous) n'aurait jamais lieu pour une
            // famille qui ne paie rien dans l'immédiat.
            $montantVerse = (int) ($preinscription->montant_verser ?? $donneesImport['scolarite_payee'] ?? 0);
            $montantRemise = (int) ($donneesImport['scolarite_remise'] ?? 0);

            if ($preinscription->type === 'existant' || $montantVerse > 0 || $montantRemise > 0) {
                $annee = $preinscription->anneeScolaire ?? $this->anneeActive($preinscription->school_id);

                if ($annee === null) {
                    throw new RuntimeException("Aucune année scolaire active pour cet établissement.");
                }

                $dossier = $this->scolarite->dossier($eleve, $annee);
                $this->convertirDetteEnFraisAnnexe($dossier);

                if ($montantVerse > 0) {
                    $versement = $this->scolarite->encaisser($dossier, [
                        'montant' => $montantVerse,
                        'mode' => $preinscription->mode_versement ?? 'especes',
                        'reference_externe' => $preinscription->reference_externe,
                        'note' => 'Versement initié par le parent à la préinscription.',
                        'lignes' => $preinscription->rubriques_versement,
                    ], $adminUserId);

                    $versementId = $versement->id;
                }

                if ($montantRemise > 0) {
                    $this->enregistrerRemiseImport($eleve, $annee, $montantRemise, $adminUserId, $donneesImport['annee_source'] ?? null);
                }
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

    private function anneeActive(int $schoolId): ?AnneeScolaire
    {
        return AnneeScolaire::where('school_id', $schoolId)->where('is_active', true)->first();
    }

    /**
     * Convertit le reliquat automatiquement calculé par
     * {@see ScolariteService::dossier()} (solde impayé de l'année précédente
     * + dettes antérieures non imputées) en une ligne de frais annexe
     * distincte « Dette antérieure » — décision produit : la présenter comme
     * un poste à part plutôt que la laisser dans `report_dette`, sans jamais
     * la compter deux fois. `wasRecentlyCreated` borne l'opération au tout
     * premier appel qui ouvre le dossier : une relecture d'un dossier déjà
     * existant ne doit rien reconvertir (report_dette y vaut déjà 0 depuis
     * la première conversion, ou porte un ajustement manuel qu'il ne faut pas
     * écraser).
     */
    private function convertirDetteEnFraisAnnexe(DossierScolarite $dossier): void
    {
        if (! $dossier->wasRecentlyCreated || $dossier->report_dette <= 0) {
            return;
        }

        $dossier->fraisAnnexes()->create([
            'frais_annexe_id' => null,
            'libelle' => 'Dette antérieure',
            'montant' => $dossier->report_dette,
        ]);

        $dossier->update(['report_dette' => 0]);
    }

    /**
     * Anciens élèves (déjà présents avant le début de l'année active) qui ne
     * se sont pas encore réinscrits pour cette année — ni via une
     * préinscription `existant` validée, ni via un dossier de scolarité déjà
     * ouvert pour l'année en cours (une réinscription faite hors du circuit
     * préinscription compte aussi comme réinscrit).
     *
     * @param  int|array<int>  $schoolId
     * @return Collection<int, Eleve>
     */
    public function listeAnciensNonReinscrits(int|array $schoolId): Collection
    {
        return $this->anciensEleves($schoolId)->reject(fn(Eleve $e) => $this->estReinscritAnneeActive($e))->values();
    }

    /**
     * Anciens élèves de l'école : actifs, déjà présents avant le début de
     * l'année scolaire active — base commune au compte total (dashboard) et
     * à la liste des non-réinscrits.
     *
     * @param  int|array<int>  $schoolId
     * @return Collection<int, Eleve>
     */
    public function anciensEleves(int|array $schoolId): Collection
    {
        return Eleve::forSchool($schoolId)
            ->where('statut', 'actif')
            ->get()
            ->filter(function (Eleve $eleve) {
                $annee = $this->anneeActive($eleve->school_id);

                return $annee !== null && $eleve->created_at->lessThan($annee->date_debut);
            })
            ->values();
    }

    private function estReinscritAnneeActive(Eleve $eleve): bool
    {
        $annee = $this->anneeActive($eleve->school_id);

        if ($annee === null) {
            return false;
        }

        return Preinscription::where('eleve_id', $eleve->id)
            ->where('type', 'existant')
            ->where('statut', 'validee')
            ->where('annee_scolaire_id', $annee->id)
            ->exists()
            || DossierScolarite::where('eleve_id', $eleve->id)->where('annee_scolaire_id', $annee->id)->exists();
    }

    /** @param int|array<int> $schoolId */
    public function listeInscritsAnneeActive(int|array $schoolId): Collection
    {
        return Preinscription::forSchool($schoolId)
            ->where('statut', 'validee')
            ->get()
            ->filter(fn(Preinscription $p) => $p->annee_scolaire_id !== null && $p->annee_scolaire_id === $this->anneeActive($p->school_id)?->id)
            ->values();
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
                'annee_scolaire_id' => $this->anneeActive($eleve->school_id)?->id,
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

    /** Crée et valide directement une préinscription admin pour un nouvel élève. */
    public function creerEtValiderNouveauParAdmin(int $schoolId, array $donnees, int $adminUserId): Preinscription
    {
        return $this->transaction(function () use ($schoolId, $donnees, $adminUserId) {
            $tuteurData = $donnees['donnees_tuteurs'][0] ?? null;
            if ($tuteurData === null) {
                throw new RuntimeException('Ajoutez au moins un tuteur pour ce nouvel élève.');
            }

            $telephone = $tuteurData['telephone'] ?? null;
            $tuteur = $telephone
                ? Tuteur::updateOrCreate(['school_id' => $schoolId, 'telephone' => $telephone], ['nom_complet' => $tuteurData['nom_complet']])
                : Tuteur::create(['school_id' => $schoolId, 'nom_complet' => $tuteurData['nom_complet']]);

            $preinscription = Preinscription::create([
                'school_id' => $schoolId,
                'annee_scolaire_id' => $this->anneeActive($schoolId)?->id,
                'tuteur_id' => $tuteur->id,
                'type' => 'nouveau',
                'statut' => 'en_attente',
                'donnees_eleve' => $donnees['donnees_eleve'],
                'donnees_tuteurs' => $donnees['donnees_tuteurs'],
                'classe_id' => $donnees['classe_id'] ?? null,
                'montant_verser' => $donnees['montant_verser'] ?? null,
                'mode_versement' => $donnees['mode_versement'] ?? null,
                'reference_externe' => $donnees['reference_externe'] ?? null,
            ]);

            return $this->valider($preinscription, $adminUserId);
        });
    }

    /**
     * Une ligne d'import massif (fichier de situation, même format que
     * l'import élèves) — validée immédiatement comme
     * {@see creerEtValiderParAdmin()} : l'admin a déjà revu son fichier avant
     * de l'importer, pas de file d'attente intermédiaire.
     *
     * Ancien élève ou nouveau : jamais une colonne dédiée, toujours une
     * comparaison — cf. {@see rapprocherEleveExistant()}.
     *
     * @param  array{
     *   matricule?: ?string, nom_complet?: ?string, sexe?: ?string, date_naissance?: ?string,
     *   lieu_naissance?: ?string, nationalite?: ?string, numero_acte_naissance?: ?string, adresse?: ?string,
     *   redoublant?: ?bool, refugie?: ?string, deplace_interne?: ?string,
     *   classe?: ?string, niveau_classe?: ?string,
     *   tuteurs?: list<array{lien: string, nom: ?string, telephone: ?string, profession: ?string}>,
     *   scolarite_due?: ?int, scolarite_payee?: ?int, scolarite_remise?: ?int, dette_declaree?: ?int, annee_source?: ?string,
     * }  $ligne
     */
    public function importerLigne(int $schoolId, array $ligne, int $adminUserId): Preinscription
    {
        $classeId = $this->resoudreClasse($schoolId, [$ligne['classe'] ?? null, $ligne['niveau_classe'] ?? null]);

        $donneesEleve = array_filter([
            'nom_complet' => $ligne['nom_complet'] ?? null,
            'sexe' => $ligne['sexe'] ?? null,
            'date_naissance' => $ligne['date_naissance'] ?? null,
            'lieu_naissance' => $ligne['lieu_naissance'] ?? null,
            'nationalite' => $ligne['nationalite'] ?? null,
            'numero_acte_naissance' => $ligne['numero_acte_naissance'] ?? null,
            'adresse' => $ligne['adresse'] ?? null,
            'redoublant' => $ligne['redoublant'] ?? null,
            'refugie' => $ligne['refugie'] ?? null,
            'deplace_interne' => $ligne['deplace_interne'] ?? null,
        ], fn($v) => $v !== null);

        $donneesTuteurs = collect($ligne['tuteurs'] ?? [])
            ->values()
            ->map(fn(array $c, int $i) => [
                'nom_complet' => $c['nom'] ?? $c['lien'],
                'telephone' => $c['telephone'] ?? null,
                'lien_parente' => $c['lien'],
                'profession' => $c['profession'] ?? null,
                'is_principal' => $i === 0,
            ])->all();

        $eleve = $this->rapprocherEleveExistant($schoolId, $ligne);

        if ($eleve !== null) {
            $this->enregistrerDetteImport($eleve, $ligne);

            return $this->creerEtValiderParAdmin($eleve, [
                'donnees_eleve' => $this->ajouterDonneesFinancieresImport($donneesEleve, $ligne),
                'donnees_tuteurs' => $donneesTuteurs,
                'classe_id' => $classeId,
                'montant_verser' => max(0, (int) ($ligne['scolarite_payee'] ?? 0)) ?: null,
                'mode_versement' => 'especes',
            ], $adminUserId);
        }

        $donneesEleve = $this->ajouterDonneesFinancieresImport($donneesEleve, $ligne);

        if (
            ! empty($donneesEleve['nom_complet'])
            && (($ligne['scolarite_payee'] ?? 0) > 0)
            && (empty($donneesEleve['sexe']) || empty($donneesEleve['date_naissance']) || $classeId === null || $donneesTuteurs === [])
        ) {
            return $this->creerPreinscriptionImportIncomplete(
                $schoolId,
                $classeId,
                $donneesEleve,
                $donneesTuteurs,
                $ligne,
            );
        }

        if ($donneesTuteurs === []) {
            throw new RuntimeException("Aucun élève existant ne correspond (nom + date de naissance) : un nouvel élève doit avoir au moins un contact (père, mère ou autre).");
        }

        if (empty($donneesEleve['nom_complet']) || empty($donneesEleve['sexe']) || empty($donneesEleve['date_naissance'])) {
            throw new RuntimeException("Un nouvel élève doit avoir un nom, un sexe et une date de naissance.");
        }

        if ($classeId === null) {
            throw new RuntimeException("Classe introuvable pour un nouvel élève.");
        }

        $tuteur = $this->resoudreOuCreerTuteur($schoolId, $donneesTuteurs[0]);

        return $this->transaction(function () use ($schoolId, $classeId, $donneesEleve, $donneesTuteurs, $tuteur, $adminUserId) {
            $preinscription = Preinscription::create([
                'school_id' => $schoolId,
                'annee_scolaire_id' => $this->anneeActive($schoolId)?->id,
                'tuteur_id' => $tuteur->id,
                'eleve_id' => null,
                'type' => 'nouveau',
                'statut' => 'en_attente',
                'donnees_eleve' => $donneesEleve,
                'donnees_tuteurs' => $donneesTuteurs,
                'classe_id' => $classeId,
                'montant_verser' => max(0, (int) ($ligne['scolarite_payee'] ?? 0)) ?: null,
                'mode_versement' => 'especes',
            ]);

            return $this->valider($preinscription, $adminUserId);
        });
    }

    /** Conserve une ligne payée mais incomplète dans la file admin plutôt que de la rejeter. */
    private function creerPreinscriptionImportIncomplete(
        int $schoolId,
        ?int $classeId,
        array $donneesEleve,
        array $donneesTuteurs,
        array $ligne,
    ): Preinscription {
        if ($donneesTuteurs === []) {
            $nomContact = 'Contact à compléter - ' . ($donneesEleve['nom_complet'] ?? 'Import');
            $tuteur = $this->resoudreOuCreerTuteur($schoolId, ['nom_complet' => $nomContact]);
            $donneesTuteurs = [[
                'nom_complet' => $nomContact,
                'telephone' => null,
                'lien_parente' => 'Contact à compléter',
                'is_principal' => true,
            ]];
        } else {
            $tuteur = $this->resoudreOuCreerTuteur($schoolId, [
                'nom_complet' => $donneesTuteurs[0]['nom_complet'],
                'telephone' => $donneesTuteurs[0]['telephone'] ?? null,
            ]);
        }

        $montantPaye = (int) ($ligne['scolarite_payee'] ?? 0);
        $note = 'Import accepté avec montant déjà payé : ' . number_format($montantPaye, 0, ',', ' ') . ' FCFA.';
        if (($ligne['scolarite_due'] ?? null) !== null) {
            $note .= ' Montant dû déclaré : ' . number_format((int) $ligne['scolarite_due'], 0, ',', ' ') . ' FCFA.';
        }
        $note .= ' Compléter les informations manquantes avant validation.';

        return Preinscription::create([
            'school_id' => $schoolId,
            'annee_scolaire_id' => $this->anneeActive($schoolId)?->id,
            'tuteur_id' => $tuteur->id,
            'eleve_id' => null,
            'type' => 'nouveau',
            'statut' => 'en_attente',
            'donnees_eleve' => $donneesEleve,
            'donnees_tuteurs' => $donneesTuteurs,
            'classe_id' => $classeId,
            'note_admin' => $note,
            'montant_verser' => $montantPaye > 0 ? $montantPaye : null,
            'mode_versement' => 'especes',
        ]);
    }

    /** Conserve les montants du fichier dans la préinscription sans les envoyer dans `eleves`. */
    private function ajouterDonneesFinancieresImport(array $donneesEleve, array $ligne): array
    {
        $finances = array_filter([
            'scolarite_due' => $ligne['scolarite_due'] ?? null,
            'scolarite_payee' => $ligne['scolarite_payee'] ?? null,
            'scolarite_remise' => $ligne['scolarite_remise'] ?? null,
            'annee_source' => $ligne['annee_source'] ?? null,
        ], fn($valeur) => $valeur !== null);

        return $finances === [] ? $donneesEleve : [...$donneesEleve, '_import_financier' => $finances];
    }

    private function enregistrerRemiseImport(Eleve $eleve, AnneeScolaire $annee, int $montant, ?int $adminUserId, ?string $anneeSource): void
    {
        $motif = 'Remise import préinscription ' . ($anneeSource ?: 'sans année');

        if (Remise::where('eleve_id', $eleve->id)->where('annee_scolaire_id', $annee->id)->where('motif', $motif)->exists()) {
            return;
        }

        $this->scolarite->enregistrerRemise($eleve, $annee, $montant, $motif, $adminUserId);
    }

    /**
     * Découpe le fichier en lots traités par requêtes séparées — sans quoi
     * un fichier de plusieurs centaines de lignes (chacune ouvrant une
     * transaction, un dossier de scolarité, parfois un tuteur) dépasse
     * largement le délai d'exécution du serveur en un seul appel : la requête
     * finit tuée en plein milieu, l'hébergement mutualisé ne laissant aucune
     * marge pour l'allonger. Le client (`ImportModal`, prop `decoupe`)
     * traite ensuite chaque lot l'un après l'autre — même mécanique que
     * {@see \App\Services\EleveService::preparerImportDecoupe()}, dont
     * l'import massif de préinscriptions partage le format de fichier.
     */
    public function preparerImportDecoupe(UploadedFile $file, string $token, int $tailleLot = 60): int
    {
        $lecteur = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file->getRealPath());
        $lecteur->setReadDataOnly(true);
        $feuille = $lecteur->load($file->getRealPath())->getSheet(0);

        // `formatData: false` : les dates restent leur numéro de série Excel
        // brut, exactement ce que lirait un import non découpé —
        // `PreinscriptionImport::date()` sait déjà convertir cette valeur.
        $lignes = $feuille->toArray(null, true, false, false);
        $entetes = array_shift($lignes) ?? [];

        $dossier = $this->dossierImportDecoupe($token);
        if (! is_dir($dossier)) {
            mkdir($dossier, 0755, true);
        }

        $lots = array_chunk($lignes, max(1, $tailleLot));

        foreach ($lots as $i => $lot) {
            $classeur = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $feuilleLot = $classeur->getActiveSheet();
            $feuilleLot->fromArray($entetes, null, 'A1');
            $feuilleLot->fromArray($lot, null, 'A2');
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($classeur))->save("{$dossier}/{$i}.xlsx");
            $classeur->disconnectWorksheets();
        }

        return count($lots);
    }

    /**
     * Importe un lot préparé par {@see preparerImportDecoupe()}. Le lot
     * traité est supprimé aussitôt, et le dossier avec lui une fois le
     * dernier lot passé — rien ne doit s'accumuler sur le disque au-delà de
     * la durée de l'import.
     *
     * @return array{resultat: array{imported: int, failed: int, erreurs: array}, dernier: bool}
     */
    public function importerChunk(int $schoolId, string $token, int $index, int $adminUserId): array
    {
        $dossier = $this->dossierImportDecoupe($token);
        $chemin = "{$dossier}/{$index}.xlsx";

        if (! is_file($chemin)) {
            throw new RuntimeException("Ce lot est introuvable — il a peut-être déjà été traité, ou l'import a expiré.");
        }

        $import = new PreinscriptionImport($schoolId, $this, $adminUserId);
        Excel::import($import, $chemin);

        @unlink($chemin);
        $dernier = ! is_file("{$dossier}/" . ($index + 1) . '.xlsx');
        if ($dernier) {
            @rmdir($dossier);
        }

        return [
            'resultat' => ['imported' => $import->importees, 'failed' => count($import->erreurs), 'erreurs' => $import->erreurs],
            'dernier' => $dernier,
        ];
    }

    private function dossierImportDecoupe(string $token): string
    {
        // Un UUID généré côté serveur (cf. PreinscriptionAdminController::importPreparer) :
        // jamais de segment de chemin fourni par le client dans `$token`.
        return storage_path('app/private/imports-preinscriptions/' . $token);
    }

    /**
     * Ancien élève ou nouveau : le matricule prime s'il correspond
     * réellement à un élève de l'école (une ligne peut en porter un ancien,
     * périmé) ; à défaut, on rapproche sur nom complet + date de naissance —
     * le même duo qu'utilise déjà {@see soumettre()} pour détecter les
     * doublons d'une nouvelle inscription. Sans les deux, aucun rapprochement
     * n'est tenté : un nom seul rapprocherait trop de monde.
     */
    private function rapprocherEleveExistant(int $schoolId, array $ligne): ?Eleve
    {
        if (! empty($ligne['matricule'])) {
            $parMatricule = Eleve::where('school_id', $schoolId)->where('matricule', $ligne['matricule'])->first();

            if ($parMatricule !== null) {
                return $parMatricule;
            }
        }

        if (empty($ligne['nom_complet']) || empty($ligne['date_naissance'])) {
            return null;
        }

        return Eleve::where('school_id', $schoolId)
            ->whereRaw('LOWER(nom_complet) = ?', [mb_strtolower(trim($ligne['nom_complet']))])
            ->whereDate('date_naissance', $ligne['date_naissance'])
            ->first();
    }

    /**
     * Reprend en dette antérieure ce que le fichier de situation dit encore
     * dû. La colonne « DEBTS » du fichier prime quand elle est renseignée —
     * certaines lignes ne portent pas les trois colonnes de calcul (frais,
     * montant réglé, remise) alors que DEBTS l'est ; à défaut, on retombe sur
     * le calcul (frais - montant réglé - remise), exactement comme
     * `EleveImport::traiterDette()`. Idempotent sur (élève, année source) via
     * le motif, pour qu'un réimport du même fichier ne double pas le report.
     * La dette rejoint `report_dette` du prochain dossier ouvert
     * (`ScolariteService::dossier()`), que {@see valider()} convertit ensuite
     * en ligne de frais annexe « Dette antérieure ».
     */
    private function enregistrerDetteImport(Eleve $eleve, array $ligne): void
    {
        if (($ligne['dette_declaree'] ?? null) !== null) {
            $dette = $ligne['dette_declaree'];
        } else {
            $du = $ligne['scolarite_due'] ?? null;

            if ($du === null) {
                return;
            }

            $dette = $du - ($ligne['scolarite_payee'] ?? 0) - ($ligne['scolarite_remise'] ?? 0);
        }

        if ($dette <= 0) {
            return;
        }

        $motif = 'Report scolarité ' . ($ligne['annee_source'] ?? 'année antérieure') . ' (import préinscription)';

        if (DetteAnterieure::where('eleve_id', $eleve->id)->where('motif', $motif)->exists()) {
            return;
        }

        $this->scolarite->enregistrerDetteAnterieure($eleve, $dette, $motif, null);
    }

    /**
     * Rapprochement insensible à la casse/accents/espaces, comme les
     * libellés de classe d'un fichier de situation. `$candidats` essaie
     * chaque libellé dans l'ordre (nom de classe précis, puis niveau en
     * repli) — le premier qui correspond l'emporte.
     *
     * @param  list<?string>  $candidats
     */
    private function resoudreClasse(int $schoolId, array $candidats): ?int
    {
        $classes = null;

        foreach (array_filter($candidats, fn(?string $c) => $c !== null && trim($c) !== '') as $libelle) {
            $classes ??= Classe::where('school_id', $schoolId)->get(['id', 'nom', 'sigle']);
            $cle = self::cleClasse($libelle);
            $trouvee = $classes->first(fn(Classe $c) => self::cleClasse($c->nom) === $cle || self::cleClasse($c->sigle) === $cle);

            if ($trouvee) {
                return $trouvee->id;
            }
        }

        return null;
    }

    private static function cleClasse(?string $libelle): string
    {
        return preg_replace('/[^A-Z0-9]+/', '', mb_strtoupper(\Illuminate\Support\Str::ascii((string) $libelle))) ?? '';
    }

    /** Même rapprochement par téléphone que {@see synchroniserTuteurs()}, exposé pour l'import massif qui n'a pas encore d'élève à qui rattacher le tuteur. */
    private function resoudreOuCreerTuteur(int $schoolId, array $tuteurData): Tuteur
    {
        $telephone = $tuteurData['telephone'] ?? null;

        return $telephone
            ? Tuteur::updateOrCreate(['school_id' => $schoolId, 'telephone' => $telephone], ['nom_complet' => $tuteurData['nom_complet']])
            : Tuteur::create(['school_id' => $schoolId, 'nom_complet' => $tuteurData['nom_complet']]);
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
                'classe_id',
                'note_admin',
                'montant_verser',
                'mode_versement',
                'reference_externe',
                'rubriques_versement',
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
        unset($donnees['_import_financier']);

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
        unset($donnees['_import_financier']);

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
            $principal = collect($telephones)->first(fn($tel) => ! empty($tel['is_principal'])) ?? $telephones[0];

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

        $aUnPrincipal = collect($telephones)->contains(fn($tel) => ! empty($tel['is_principal']));

        foreach ($telephones as $index => $tel) {
            TuteurTelephone::create([
                'tuteur_id' => $tuteur->id,
                'numero' => $tel['numero'],
                'is_principal' => $aUnPrincipal ? ! empty($tel['is_principal']) : $index === 0,
            ]);
        }
    }
}
