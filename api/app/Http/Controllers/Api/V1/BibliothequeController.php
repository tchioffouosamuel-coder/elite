<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\BibliothequeDocument;
use App\Services\BibliothequeService;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Bibliothèque numérique : documents déposés par l'administration, visibles
 * par les écoles qui leur sont explicitement rattachées — personnel et
 * parents de ces écoles y accèdent en lecture seule via leurs propres espaces
 * (cf. `PersonnelEspaceController::bibliotheque()`, `ParentEspaceController::bibliotheque()`).
 */
class BibliothequeController extends Controller
{
    public function __construct(private readonly BibliothequeService $service) {}

    public function index(): JsonResponse
    {
        $documents = $this->service->lister(Tenant::schoolIds());

        return ApiResponse::success($documents->map(fn (BibliothequeDocument $d) => $this->resumer($d))->values());
    }

    public function store(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'titre' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'fichier' => ['required', 'file', 'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,jpg,jpeg,png', 'max:20480'],
            'school_ids' => ['required', 'array', 'min:1'],
            'school_ids.*' => ['integer', Rule::exists('schools', 'id')],
        ]);

        // Un admin ne peut cibler que les écoles de son propre périmètre —
        // même garde-fou que pour l'octroi d'une avance ou le ciblage d'une
        // annonce : lister une école hors périmètre serait arbitraire.
        $schoolIds = array_values(array_intersect($donnees['school_ids'], Tenant::schoolIds()));

        if (empty($schoolIds)) {
            return ApiResponse::error("Aucune des écoles sélectionnées n'est accessible à votre compte.", 422);
        }

        $document = $this->service->uploader(
            ['titre' => $donnees['titre'], 'description' => $donnees['description'] ?? null, 'school_ids' => $schoolIds],
            $request->file('fichier'),
            $request->user()?->id,
        );

        return ApiResponse::created($this->resumer($document), 'Document ajouté à la bibliothèque.');
    }

    public function destroy(int $id): JsonResponse
    {
        $document = BibliothequeDocument::visiblePour(Tenant::schoolIds())->findOrFail($id);
        $this->service->supprimer($document);

        return ApiResponse::success();
    }

    /** @return array<string, mixed> */
    private function resumer(BibliothequeDocument $document): array
    {
        $document->loadMissing(['ecoles', 'uploadePar']);

        return [
            'id' => $document->id,
            'titre' => $document->titre,
            'description' => $document->description,
            'fichier_url' => $document->fichier_url,
            'fichier_nom_original' => $document->fichier_nom_original,
            'taille' => $document->taille,
            'type_mime' => $document->type_mime,
            'ecoles' => $document->ecoles->map(fn ($e) => ['id' => $e->id, 'name' => $e->name])->values(),
            'uploade_par' => $document->uploadePar?->name,
            'created_at' => $document->created_at->format('Y-m-d H:i'),
        ];
    }
}
