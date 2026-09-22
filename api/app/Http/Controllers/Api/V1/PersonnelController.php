<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\ModeleGenerique;
use App\Exports\PersonnelExport;
use App\Exports\PresencePersonnelJournaliereExport;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CreateLoginAccountRequest;
use App\Http\Requests\Api\V1\StorePersonnelRequest;
use App\Http\Requests\Api\V1\UpdatePersonnelRequest;
use App\Http\Resources\Api\V1\PersonnelResource;
use App\Imports\PresencePersonnelJournaliereImport;
use App\Models\ActivityLog;
use App\Models\AnneeScolaire;
use App\Models\FonctionReferentiel;
use App\Models\Personnel;
use App\Models\School;
use App\Services\AttestationEmployeurService;
use App\Services\CompteAgentService;
use App\Services\FicheIdentitePersonnelService;
use App\Services\FusionComptesPersonnelParentService;
use App\Services\PersonnelService;
use App\Support\Pdf\FicheIdentitePersonnelGenerator;
use App\Support\Pdf\FichePresencePersonnelGenerator;
use App\Support\Pdf\IdentifiantsGenerator;
use App\Support\Pdf\PersonnelFichierGenerator;
use App\Support\Ocr\ConfirmationImportOcrPresence;
use App\Support\Ocr\PresenceOcrExtractor;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class PersonnelController extends Controller
{
    public function __construct(
        private readonly PersonnelService $service,
        private readonly CompteAgentService $comptes,
        private readonly FusionComptesPersonnelParentService $fusionComptes,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->service->list(
            Tenant::schoolIds(),
            $request->only(['search', 'departement_id', 'fonction_id', 'fonction_label', 'banque_id', 'statut', 'attribution']),
            (int) $request->integer('per_page', 20),
        );

        return ApiResponse::paginated($paginator, PersonnelResource::class);
    }

    public function store(StorePersonnelRequest $request): JsonResponse
    {
        $data = $request->validated();
        $schoolId = Tenant::resolveWriteSchoolId($data['school_id'] ?? null);
        unset($data['school_id']);

        $personnel = $this->service->create($schoolId, $data);

        ActivityLog::enregistrer($request->user(), 'personnel.cree', "Ajout de {$personnel->nom_complet}.", $personnel);

        return ApiResponse::created(new PersonnelResource($personnel), 'Membre du personnel créé.');
    }

    public function show(int $id): JsonResponse
    {
        $personnel = $this->service->find(Tenant::schoolIds(), $id);

        return ApiResponse::success(new PersonnelResource($personnel));
    }

    public function update(UpdatePersonnelRequest $request, int $id): JsonResponse
    {
        $personnel = $this->service->find(Tenant::schoolIds(), $id);

        $data = $request->validated();

        // Une école soumise est celle où l'agent doit désormais être rattaché.
        // Elle est vérifiée contre les écoles accessibles au compte, et non
        // contre `Tenant::schoolIds()` : muter un agent suppose de désigner
        // une école *autre* que celle consultée, ce qu'un compte en mode
        // "focus" (X-School-Id) ne pourrait jamais faire autrement. Même
        // règle que le transfert d'un élève, cf. EleveController::transfert().
        if (($data['school_id'] ?? null) !== null
            && ! $request->user()->ecolesAccessibles()->contains('id', (int) $data['school_id'])) {
            return ApiResponse::forbidden("Cet établissement n'est pas accessible à votre compte.");
        }

        $personnel = $this->service->update($personnel, $data);

        return ApiResponse::success(new PersonnelResource($personnel), 'Membre du personnel mis à jour.');
    }

    public function archive(int $id): JsonResponse
    {
        $personnel = $this->service->find(Tenant::schoolIds(), $id);
        $personnel = $this->service->archive($personnel);

        return ApiResponse::success(new PersonnelResource($personnel), 'Membre du personnel archivé.');
    }

    public function reactivate(int $id): JsonResponse
    {
        $personnel = $this->service->find(Tenant::schoolIds(), $id);
        $personnel = $this->service->reactivate($personnel);

        return ApiResponse::success(new PersonnelResource($personnel), 'Membre du personnel réactivé.');
    }

    public function createAccount(CreateLoginAccountRequest $request, int $id): JsonResponse
    {
        $personnel = $this->service->find(Tenant::schoolIds(), $id);
        $user = $this->service->createLoginAccount(
            $personnel,
            $request->string('email')->toString() ?: null,
            $request->string('password')->toString() ?: null,
        );

        return ApiResponse::created(
            ['user_id' => $user->id, 'email' => $user->email],
            "Accès ouvert : {$user->email}.",
        );
    }

    /**
     * Rattrapage pour les comptes ouverts avant que la connexion par
     * téléphone n'existe : cf. CompteAgentService::rattraperTelephones().
     */
    public function rattraperTelephones(): JsonResponse
    {
        $resultat = $this->comptes->rattraperTelephones(Tenant::schoolIds());

        $message = $resultat['maj'] > 0
            ? "{$resultat['maj']} compte(s) mis à jour avec leur numéro de téléphone."
            : 'Aucun compte à mettre à jour — tous les agents avec un numéro valide en ont déjà un sur leur compte.';

        return ApiResponse::success($resultat, $message);
    }

    /**
     * Doublons personnel/parent détectés (même téléphone, comptes
     * différents) — aperçu affiché avant confirmation, cf. {@see fusionnerComptesParent()}.
     */
    public function apercuFusionComptesParent(): JsonResponse
    {
        $paires = $this->fusionComptes->apercu(Tenant::schoolIds());

        return ApiResponse::success(['paires' => $paires, 'total' => count($paires)]);
    }

    /**
     * Fusionne les doublons personnel/parent détectés : rattache chaque
     * fiche tuteur au compte personnel correspondant et supprime le compte
     * parent devenu superflu — cf. FusionComptesPersonnelParentService.
     */
    public function fusionnerComptesParent(): JsonResponse
    {
        $resultat = $this->fusionComptes->fusionner(Tenant::schoolIds());

        $message = $resultat['fusionnes'] > 0
            ? "{$resultat['fusionnes']} compte(s) fusionné(s) avec leur compte parent."
            : 'Aucun doublon personnel/parent à fusionner.';

        return ApiResponse::success($resultat, $message);
    }

    public function destroy(int $id): JsonResponse
    {
        $personnel = $this->service->find(Tenant::schoolIds(), $id);
        $this->service->delete($personnel);

        return ApiResponse::success(null, 'Membre du personnel supprimé.');
    }

    public function batchDelete(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $schoolId = Tenant::schoolIds();
        $deleted = 0;

        foreach ($data['ids'] as $id) {
            $personnel = $this->service->find($schoolId, $id);
            $this->service->delete($personnel);
            $deleted++;
        }

        return ApiResponse::success(['deleted' => $deleted], "{$deleted} membre(s) du personnel supprimé(s).");
    }

    /**
     * Change la fonction de plusieurs agents d'un coup.
     *
     * La fonction porte les privilèges : la reprise d'un fichier laisse
     * souvent des dizaines d'agents sans fonction, et les doter un par un
     * demande autant d'allers-retours qu'il y a d'enseignants.
     *
     * Les agents hors du périmètre du compte sont ignorés plutôt que de faire
     * échouer le lot — `find()` borne déjà à l'école, et une sélection peut
     * couvrir plusieurs écoles en mode agrégé.
     */
    public function batchFonction(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'fonction_id' => ['required', 'integer', 'exists:fonction_referentiel,id'],
        ]);

        $fonction = FonctionReferentiel::forSchool(Tenant::schoolIds())->find($data['fonction_id']);

        if ($fonction === null) {
            return ApiResponse::error("Cette fonction n'appartient pas à votre établissement.", 422);
        }

        $modifies = Personnel::forSchool(Tenant::schoolIds())
            ->whereIn('id', $data['ids'])
            // Une fonction appartient à une école : l'appliquer à un agent
            // d'une autre école le rattacherait à un référentiel étranger.
            ->where('school_id', $fonction->school_id)
            ->update(['fonction_id' => $fonction->id]);

        $ignores = count($data['ids']) - $modifies;

        return ApiResponse::success(
            ['modifies' => $modifies, 'ignores' => $ignores],
            $ignores > 0
                ? "{$modifies} agent(s) mis à jour, {$ignores} ignoré(s) : la fonction « {$fonction->label_fr} » appartient à une autre école."
                : "{$modifies} agent(s) rattaché(s) à la fonction « {$fonction->label_fr} ».",
        );
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv']]);

        $result = $this->service->importFromExcel(app('tenant.school_id'), $request->file('file'));

        return ApiResponse::success($result, "{$result['imported']} ligne(s) importée(s).");
    }

    public function export(): BinaryFileResponse
    {
        return Excel::download(new PersonnelExport(Tenant::schoolIds()), 'personnel.xlsx');
    }

    public function modele(): BinaryFileResponse
    {
        // Ligne 3 : les deux premières portent le titre et les totaux, comme dans le
        // tableau de mise en place du personnel attendu par PersonnelImport::headingRow().
        return Excel::download(
            new ModeleGenerique(\App\Imports\PersonnelImport::enTetes(), 3),
            'modele-personnel.xlsx',
        );
    }

    public function fichePresenceJournaliere(Request $request): Response
    {
        abort_if(Tenant::isAggregate(), 422, "Veuillez sélectionner un établissement avant de tirer la fiche.");

        $data = $request->validate(['date' => ['nullable', 'date']]);
        $date = isset($data['date']) ? date('Y-m-d', strtotime($data['date'])) : now()->format('Y-m-d');
        $school = School::findOrFail(Tenant::schoolId());
        $personnels = Personnel::forSchool($school->id)->where('statut', 'actif')->orderBy('nom_complet')->get();

        $pdf = (new FichePresencePersonnelGenerator)->build($school, $date, $personnels);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="fiche-presence-personnel-' . $date . '.pdf"',
        ]);
    }

    public function modelePresenceJournaliere(Request $request): BinaryFileResponse
    {
        abort_if(Tenant::isAggregate(), 422, "Veuillez sélectionner un établissement avant de télécharger le modèle.");

        $data = $request->validate(['date' => ['nullable', 'date']]);
        $date = isset($data['date']) ? date('Y-m-d', strtotime($data['date'])) : now()->format('Y-m-d');

        return Excel::download(
            new PresencePersonnelJournaliereExport(Tenant::schoolId(), date: $date, modeleDuJour: true),
            'modele-presence-personnel-' . $date . '.xlsx',
        );
    }

    public function exportPresenceJournaliere(Request $request): BinaryFileResponse
    {
        abort_if(Tenant::isAggregate(), 422, "Veuillez sélectionner un établissement avant d'exporter les présences.");

        $data = $request->validate([
            'date' => ['nullable', 'date'],
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
        ]);

        return Excel::download(
            new PresencePersonnelJournaliereExport(
                Tenant::schoolId(),
                $data['date'] ?? null,
                $data['date_debut'] ?? null,
                $data['date_fin'] ?? null,
            ),
            'presences-personnel.xlsx',
        );
    }

    public function importPresenceJournaliere(Request $request): JsonResponse
    {
        abort_if(Tenant::isAggregate(), 422, "Veuillez sélectionner un établissement avant d'importer les présences.");

        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
            'date' => ['nullable', 'date'],
        ]);

        $import = new PresencePersonnelJournaliereImport(
            Tenant::schoolId(),
            isset($data['date']) ? date('Y-m-d', strtotime($data['date'])) : null,
        );
        Excel::import($import, $request->file('file'));

        return ApiResponse::success(
            [
                'imported' => $import->importedCount,
                'updated' => $import->updatedCount,
                'failed' => count($import->failures()),
                'errors' => $import->failures(),
            ],
            "{$import->importedCount} présence(s) créée(s), {$import->updatedCount} mise(s) à jour.",
        );
    }

    /**
     * Lit une photo de la fiche de présence papier signée à la main et
     * renvoie, ligne par ligne, ce que l'OCR y a reconnu — sans rien
     * enregistrer. Le résultat alimente une modale de prévisualisation où
     * chaque ligne (agent, heure d'arrivée, heure de départ) reste
     * modifiable avant confirmation (`importOcrPresenceJournaliere`).
     */
    public function apercuOcrPresenceJournaliere(Request $request): JsonResponse
    {
        abort_if(Tenant::isAggregate(), 422, "Veuillez sélectionner un établissement avant d'importer une photo de présence.");

        $data = $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,bmp', 'max:15360'],
            'date' => ['nullable', 'date'],
        ]);

        $resultat = (new PresenceOcrExtractor)->extraire($request->file('image'), Tenant::schoolId());

        return ApiResponse::success([
            'date' => isset($data['date']) ? date('Y-m-d', strtotime($data['date'])) : now()->format('Y-m-d'),
            'lignes' => $resultat['lignes'],
            'personnels' => $resultat['personnels'],
        ]);
    }

    /**
     * Enregistre les lignes relues/corrigées par l'utilisateur dans la
     * modale de prévisualisation OCR — même résultat qu'un import Excel
     * (`importPresenceJournaliere`), mais à partir d'un tableau déjà résolu
     * plutôt que d'une feuille à parser.
     */
    public function importOcrPresenceJournaliere(Request $request): JsonResponse
    {
        abort_if(Tenant::isAggregate(), 422, "Veuillez sélectionner un établissement avant d'importer les présences.");

        $data = $request->validate([
            'date' => ['required', 'date'],
            'lignes' => ['required', 'array', 'min:1'],
            'lignes.*.personnel_id' => ['required', 'integer'],
            'lignes.*.nom_complet' => ['nullable', 'string'],
            'lignes.*.heure_arrivee' => ['nullable', 'date_format:H:i'],
            'lignes.*.heure_depart' => ['nullable', 'date_format:H:i'],
        ]);

        $confirmation = new ConfirmationImportOcrPresence(Tenant::schoolId(), date('Y-m-d', strtotime($data['date'])));
        $confirmation->traiter($data['lignes']);

        return ApiResponse::success(
            [
                'imported' => $confirmation->importedCount,
                'updated' => $confirmation->updatedCount,
                'failed' => count($confirmation->erreurs),
                'errors' => $confirmation->erreurs,
            ],
            "{$confirmation->importedCount} présence(s) créée(s), {$confirmation->updatedCount} mise(s) à jour.",
        );
    }

    /**
     * Fichier du personnel : registre nominatif des agents en poste, à signer
     * et archiver. L'export Excel voisin sert au retraitement des données ;
     * celui-ci est le document officiel.
     */
    public function fichier(): Response
    {
        $schoolIds = Tenant::schoolIds();
        $schools = School::whereIn('id', $schoolIds)->orderBy('name')->get();
        $donnees = $this->service->fichier($schoolIds);

        if (Tenant::isAggregate()) {
            $documents = $schools->map(fn(School $school) => [
                'donnees' => $this->service->fichier($school->id),
                'school' => $school,
                'annee' => AnneeScolaire::where('school_id', $school->id)->where('is_active', true)->first(),
            ])->all();
            $pdf = (new PersonnelFichierGenerator)->buildMany($documents);
            $nom = 'fichier-personnel-toutes-les-ecoles';
        } else {
            $school = $schools->firstOrFail();
            $annee = AnneeScolaire::where('school_id', $school->id)->where('is_active', true)->first();
            $pdf = (new PersonnelFichierGenerator)->build($donnees, $school, $annee);
            $nom = 'fichier-personnel-' . Str::slug($school->name);
        }

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $nom . '.pdf"',
        ]);
    }

    /**
     * Rapport « mise en place du personnel » de rentrée MINEDUB : ventilation
     * par grade, par type de contrat et par statut, et listes des agents
     * absents au poste, décédés ou admis à faire valoir leur droit à la
     * retraite dans l'année (tableaux 10 à 17 du canevas).
     */
    public function rapportMiseEnPlace(): JsonResponse
    {
        return ApiResponse::success($this->service->rapportMiseEnPlace(Tenant::schoolIds()));
    }

    /**
     * Identifiants de connexion de tous les comptes actifs de l'établissement
     * — le mot de passe n'y figure que pour les comptes qui portent encore
     * celui distribué à l'ouverture de l'accès (cf. CompteAgentService::identifiants).
     * Document confidentiel : à remettre en main propre plutôt qu'à archiver.
     */
    public function identifiants(): Response
    {
        $schoolIds = Tenant::schoolIds();
        $schools = School::whereIn('id', $schoolIds)->orderBy('name')->get();

        if (Tenant::isAggregate()) {
            $documents = $schools->map(fn(School $school) => [
                'donnees' => $this->comptes->identifiants($school->id),
                'school' => $school,
            ])->all();
            $pdf = (new IdentifiantsGenerator)->buildMany($documents);
            $nom = 'identifiants-toutes-les-ecoles';
        } else {
            $school = $schools->firstOrFail();
            $pdf = (new IdentifiantsGenerator)->build($this->comptes->identifiants($school->id), $school);
            $nom = 'identifiants-' . Str::slug($school->name);
        }

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $nom . '.pdf"',
        ]);
    }

    /**
     * Attestation de l'employeur, avec periode d'absence facultative.
     */
    public function attestationEmployeur(Request $request, int $id): BinaryFileResponse
    {
        $personnel = $this->service->find(Tenant::schoolIds(), $id);

        $conge = $request->validate([
            'debut' => ['nullable', 'date'],
            'fin' => ['nullable', 'date', 'after_or_equal:debut'],
            'prolongation' => ['nullable', 'date', 'after_or_equal:fin'],
            'motif' => ['nullable', 'string', 'max:120'],
        ]);

        $path = app(AttestationEmployeurService::class)->generer($personnel, $conge, $request->user()?->id);

        return response()
            ->download($path, 'attestation-employeur-' . Str::slug($personnel->nom_complet) . '.docx')
            ->deleteFileAfterSend();
    }

    /**
     * Fiche d'identification du personnel, version PDF (ouverte dans un
     * nouvel onglet côté front, comme le fichier du personnel).
     */
    public function fichePdf(int $id): Response
    {
        $personnel = $this->service->find(Tenant::schoolIds(), $id);

        $pdf = (new FicheIdentitePersonnelGenerator)->build($personnel);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="fiche-identification-' . Str::slug($personnel->nom_complet) . '.pdf"',
        ]);
    }

    /**
     * Fiche d'identification du personnel, version .docx (téléchargée,
     * comme l'attestation de l'employeur).
     */
    public function ficheWord(int $id): BinaryFileResponse
    {
        $personnel = $this->service->find(Tenant::schoolIds(), $id);

        $path = app(FicheIdentitePersonnelService::class)->generer($personnel);

        return response()
            ->download($path, 'fiche-identification-' . Str::slug($personnel->nom_complet) . '.docx')
            ->deleteFileAfterSend();
    }
}
