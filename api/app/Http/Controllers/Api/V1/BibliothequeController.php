<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\BibliothequeDocument;
use App\Services\BibliothequeService;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Bibliothèque numérique : documents déposés par l'administration, visibles
 * par les écoles qui leur sont explicitement rattachées — personnel et
 * parents de ces écoles y accèdent en lecture seule via leurs propres espaces
 * (cf. `PersonnelEspaceController::bibliotheque()`, `ParentEspaceController::bibliotheque()`).
 */
class BibliothequeController extends Controller
{
    public function __construct(private readonly BibliothequeService $service) {}

    public function index(Request $request): JsonResponse
    {
        $documents = $this->service->lister(
            Tenant::schoolIds(),
            ['search' => $request->string('search')->toString() ?: null],
            (int) $request->integer('per_page', 20),
        );

        return ApiResponse::successPaginated(
            $documents->getCollection()->map(fn (BibliothequeDocument $d) => $this->resumer($d))->values(),
            $documents,
        );
    }

    public function store(Request $request): JsonResponse
    {
        $donnees = $request->validate($this->reglesCiblage() + [
            'titre' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'fichier' => ['required', 'file', 'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,jpg,jpeg,png', 'max:20480'],
        ]);

        $schoolIds = $this->schoolIdsAccessibles($donnees['school_ids']);

        if ($schoolIds === null) {
            return ApiResponse::error("Aucune des écoles sélectionnées n'est accessible à votre compte.", 422);
        }

        $document = $this->service->uploader(
            [
                'titre' => $donnees['titre'],
                'description' => $donnees['description'] ?? null,
                'school_ids' => $schoolIds,
                'classe_ids' => $donnees['classe_ids'] ?? [],
                'cibles' => $donnees['cibles'] ?? null,
            ],
            $request->file('fichier'),
            $request->user()?->id,
        );

        return ApiResponse::created($this->resumer($document), 'Document ajouté à la bibliothèque.');
    }

    /** Ciblage plus fin d'un document déjà déposé (écoles, classes, destinataires) — pas de remplacement de fichier. */
    public function update(Request $request, int $id): JsonResponse
    {
        $document = BibliothequeDocument::visiblePour(Tenant::schoolIds())->findOrFail($id);

        $donnees = $request->validate($this->reglesCiblage(sometimes: true) + [
            'titre' => ['sometimes', 'required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        if (array_key_exists('school_ids', $donnees)) {
            $schoolIds = $this->schoolIdsAccessibles($donnees['school_ids']);

            if ($schoolIds === null) {
                return ApiResponse::error("Aucune des écoles sélectionnées n'est accessible à votre compte.", 422);
            }

            $donnees['school_ids'] = $schoolIds;
        }

        $document = $this->service->modifier($document, $donnees);

        return ApiResponse::success($this->resumer($document), 'Document mis à jour.');
    }

    /**
     * Import massif : un document par fichier déposé, même ciblage d'écoles
     * et même description pour tous — cf. `BibliothequeService::importer()`
     * pour la déduction du titre à partir du nom de fichier.
     */
    public function importer(Request $request): JsonResponse
    {
        $donnees = $request->validate($this->reglesCiblage() + [
            'fichiers' => ['required', 'array', 'min:1'],
            'fichiers.*' => ['file', 'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,jpg,jpeg,png', 'max:20480'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $schoolIds = $this->schoolIdsAccessibles($donnees['school_ids']);

        if ($schoolIds === null) {
            return ApiResponse::error("Aucune des écoles sélectionnées n'est accessible à votre compte.", 422);
        }

        $documents = $this->service->importer(
            $donnees['fichiers'],
            $schoolIds,
            $donnees['description'] ?? null,
            $request->user()?->id,
            $donnees['classe_ids'] ?? [],
            $donnees['cibles'] ?? null,
        );

        return ApiResponse::created(
            $documents->map(fn (BibliothequeDocument $d) => $this->resumer($d))->values(),
            $documents->count().' document(s) ajouté(s) à la bibliothèque.',
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $document = BibliothequeDocument::visiblePour(Tenant::schoolIds())->findOrFail($id);
        $this->service->supprimer($document);

        return ApiResponse::success();
    }

    /**
     * Un admin ne peut cibler que les écoles de son propre périmètre — même
     * garde-fou que pour l'octroi d'une avance ou le ciblage d'une annonce :
     * lister une école hors périmètre serait arbitraire. `null` si aucune
     * des écoles demandées n'est accessible.
     *
     * @param  array<int>  $schoolIdsDemandes
     * @return array<int>|null
     */
    private function schoolIdsAccessibles(array $schoolIdsDemandes): ?array
    {
        $accessibles = array_values(array_intersect($schoolIdsDemandes, Tenant::schoolIds()));

        return empty($accessibles) ? null : $accessibles;
    }

    /**
     * Règles de ciblage communes au dépôt, à l'import et à la modification :
     * écoles obligatoires (sauf en modification, `sometimes`), classes et
     * destinataires toujours optionnels — sans eux, le document reste visible
     * par toute l'école et tout profil, comme avant l'ajout de ce ciblage.
     *
     * @return array<string, array<int, mixed>>
     */
    private function reglesCiblage(bool $sometimes = false): array
    {
        $requis = $sometimes ? ['sometimes', 'required'] : ['required'];

        return [
            'school_ids' => [...$requis, 'array', 'min:1'],
            'school_ids.*' => ['integer', Rule::exists('schools', 'id')],
            'classe_ids' => ['sometimes', 'array'],
            'classe_ids.*' => ['integer', Rule::exists('classes', 'id')->whereIn('school_id', Tenant::schoolIds())],
            'cibles' => ['sometimes', 'nullable', 'array'],
            'cibles.*' => [Rule::in(BibliothequeDocument::CIBLES)],
        ];
    }

    /**
     * Aperçu PDF d'un document Word (cf. BibliothequeDocument::apercu_url).
     * Route sans compte mais signée : l'URL, temporaire, n'est distribuée
     * qu'avec la liste des documents que le demandeur voit déjà.
     */
    public function apercu(int $id): BinaryFileResponse
    {
        $document = BibliothequeDocument::findOrFail($id);
        abort_unless(in_array($document->extension(), BibliothequeDocument::EXTENSIONS_CONVERTIBLES, true), 404);

        try {
            $chemin = $this->service->apercuPdf($document);
        } catch (Throwable $e) {
            report($e);
            abort(422, "Ce document n'a pas pu être converti pour l'aperçu.");
        }

        return response()->file($chemin, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.Str::slug($document->titre).'.pdf"',
        ]);
    }

    /** @return array<string, mixed> */
    private function resumer(BibliothequeDocument $document): array
    {
        $document->loadMissing(['ecoles', 'classes', 'uploadePar']);

        return [
            'id' => $document->id,
            'titre' => $document->titre,
            'description' => $document->description,
            'fichier_url' => $document->fichier_url,
            'apercu_url' => $document->apercu_url,
            'fichier_nom_original' => $document->fichier_nom_original,
            'taille' => $document->taille,
            'type_mime' => $document->type_mime,
            'ecoles' => $document->ecoles->map(fn ($e) => ['id' => $e->id, 'name' => $e->name])->values(),
            'classes' => $document->classes->map(fn ($c) => ['id' => $c->id, 'nom' => $c->nom])->values(),
            'cibles' => $document->cibles,
            'uploade_par' => $document->uploadePar?->name,
            'created_at' => $document->created_at->format('Y-m-d H:i'),
        ];
    }
}
