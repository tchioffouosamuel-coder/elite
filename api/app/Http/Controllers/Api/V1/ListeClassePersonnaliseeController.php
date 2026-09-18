<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\ListeClassePersonnaliseeExport;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Classe;
use App\Models\ListeClasseModele;
use App\Services\ListeClassePersonnaliseeService;
use App\Support\ListeClasseColonnes;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Liste personnalisée de classe : l'utilisateur choisit une classe, un titre
 * bilingue et un jeu de colonnes (cf. ListeClasseColonnes), puis génère le
 * résultat en PDF, Word ou Excel. La configuration (titre + colonnes) peut
 * être enregistrée comme modèle réutilisable — la classe elle-même ne l'est
 * jamais, un modèle devant servir à n'importe quelle classe.
 */
class ListeClassePersonnaliseeController extends Controller
{
    public function __construct(private readonly ListeClassePersonnaliseeService $service) {}

    public function modeles(): JsonResponse
    {
        $modeles = ListeClasseModele::forSchool(Tenant::schoolIds())
            ->where('user_id', request()->user()->id)
            ->orderByDesc('id')
            ->get();

        return ApiResponse::success($modeles);
    }

    public function storeModele(Request $request): JsonResponse
    {
        $data = $this->validerModele($request);

        $modele = ListeClasseModele::create([
            ...$data,
            'school_id' => Tenant::schoolId(),
            'user_id' => $request->user()->id,
        ]);

        return ApiResponse::created($modele, 'Modèle enregistré.');
    }

    public function updateModele(Request $request, int $id): JsonResponse
    {
        $modele = ListeClasseModele::forSchool(Tenant::schoolIds())
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        $modele->update($this->validerModele($request));

        return ApiResponse::success($modele, 'Modèle mis à jour.');
    }

    public function destroyModele(Request $request, int $id): JsonResponse
    {
        $modele = ListeClasseModele::forSchool(Tenant::schoolIds())
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        $modele->delete();

        return ApiResponse::success(null, 'Modèle supprimé.');
    }

    public function pdf(Request $request, int $classeId): Response
    {
        [$classe, $titreFr, $titreEn, $colonnes, $lignes] = $this->preparer($request, $classeId);

        $pdf = $this->service->genererPdf($classe, $titreFr, $titreEn, $colonnes, $lignes);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="liste-personnalisee-'.Str::slug($classe->nom).'.pdf"',
        ]);
    }

    public function word(Request $request, int $classeId): BinaryFileResponse
    {
        [$classe, $titreFr, $titreEn, $colonnes, $lignes] = $this->preparer($request, $classeId);

        $path = $this->service->genererWord($classe, $titreFr, $titreEn, $colonnes, $lignes);
        $nomFichier = 'liste-personnalisee-'.Str::slug($classe->nom).'.docx';

        return response()->download($path, $nomFichier)->deleteFileAfterSend();
    }

    public function excel(Request $request, int $classeId): BinaryFileResponse
    {
        [$classe, $titreFr, , $colonnes, $lignes] = $this->preparer($request, $classeId);

        return Excel::download(
            new ListeClassePersonnaliseeExport($colonnes, $lignes, $titreFr),
            'liste-personnalisee-'.Str::slug($classe->nom).'.xlsx',
        );
    }

    /**
     * @return array{0: Classe, 1: string, 2: string, 3: list<string>, 4: list<array<string, string>>}
     */
    private function preparer(Request $request, int $classeId): array
    {
        $classe = Classe::forSchool(Tenant::schoolIds())->with('school')->findOrFail($classeId);

        $data = $request->validate([
            'titre_fr' => ['required', 'string', 'max:255'],
            'titre_en' => ['required', 'string', 'max:255'],
            'colonnes' => ['required', 'string'],
            'moyenne_type' => ['nullable', 'string', Rule::in(['trimestre', 'sequence', 'annuelle'])],
            'moyenne_reference_id' => ['nullable', 'integer'],
        ]);

        $colonnes = $this->colonnesValidees($data['colonnes']);

        if (in_array('moyenne', $colonnes, true) && in_array($data['moyenne_type'] ?? null, ['trimestre', 'sequence'], true) && empty($data['moyenne_reference_id'])) {
            throw ValidationException::withMessages(['moyenne_reference_id' => ['La période exacte (trimestre ou séquence) est requise.']]);
        }

        $lignes = $this->service->construireLignes(
            $classe,
            $colonnes,
            $data['moyenne_type'] ?? null,
            $data['moyenne_reference_id'] ?? null,
        );

        return [$classe, $data['titre_fr'], $data['titre_en'], $colonnes, $lignes];
    }

    /** @return list<string> */
    private function colonnesValidees(string $colonnesBrutes): array
    {
        $colonnes = array_values(array_filter(array_map('trim', explode(',', $colonnesBrutes))));
        $valides = ListeClasseColonnes::clesValides();

        abort_if(empty($colonnes), 422, 'Au moins une colonne doit être choisie.');
        abort_if(array_diff($colonnes, $valides) !== [], 422, 'Colonne inconnue.');

        return $colonnes;
    }

    private function validerModele(Request $request): array
    {
        $data = $request->validate([
            'titre_fr' => ['required', 'string', 'max:255'],
            'titre_en' => ['required', 'string', 'max:255'],
            'colonnes' => ['required', 'array', 'min:1'],
            'colonnes.*' => [Rule::in(ListeClasseColonnes::clesValides())],
            'moyenne_type' => ['nullable', 'string', Rule::in(['trimestre', 'sequence', 'annuelle'])],
        ]);

        return $data;
    }
}
