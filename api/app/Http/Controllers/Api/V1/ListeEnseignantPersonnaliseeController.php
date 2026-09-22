<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\ListePersonnaliseeExport;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ListePersonnaliseeModele;
use App\Models\Personnel;
use App\Models\School;
use App\Services\ListePersonnaliseeDocumentService;
use App\Support\ListeEnseignantColonnes;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/** Listes personnalisées des enseignants : modèles sauvegardés + exports PDF/Word/Excel. */
class ListeEnseignantPersonnaliseeController extends Controller
{
    private const DOMAINE = 'enseignants';

    public function __construct(private readonly ListePersonnaliseeDocumentService $documents) {}

    public function modeles(Request $request): JsonResponse
    {
        $modeles = ListePersonnaliseeModele::forSchool(Tenant::schoolIds())
            ->where('user_id', $request->user()->id)
            ->where('domaine', self::DOMAINE)
            ->orderByDesc('id')
            ->get();

        return ApiResponse::success($modeles);
    }

    public function storeModele(Request $request): JsonResponse
    {
        $modele = ListePersonnaliseeModele::create([
            ...$this->validerModele($request),
            'domaine' => self::DOMAINE,
            'school_id' => Tenant::schoolId(),
            'user_id' => $request->user()->id,
        ]);

        return ApiResponse::created($modele, 'Modèle enregistré.');
    }

    public function updateModele(Request $request, int $id): JsonResponse
    {
        $modele = $this->modele($request, $id);
        $modele->update($this->validerModele($request));

        return ApiResponse::success($modele, 'Modèle mis à jour.');
    }

    public function destroyModele(Request $request, int $id): JsonResponse
    {
        $this->modele($request, $id)->delete();

        return ApiResponse::success(null, 'Modèle supprimé.');
    }

    public function pdf(Request $request): Response
    {
        [$school, $titreFr, $titreEn, $colonnes, $lignes, $meta] = $this->preparer($request);

        $pdf = $this->documents->genererPdf($school, $titreFr, $titreEn, $colonnes, $lignes, ListeEnseignantColonnes::DEFINITIONS, $meta);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="liste-personnalisee-enseignants.pdf"',
        ]);
    }

    public function word(Request $request): BinaryFileResponse
    {
        [$school, $titreFr, $titreEn, $colonnes, $lignes, $meta] = $this->preparer($request);
        $path = $this->documents->genererWord($school, $titreFr, $titreEn, $colonnes, $lignes, ListeEnseignantColonnes::DEFINITIONS, $meta);

        return response()->download($path, 'liste-personnalisee-enseignants.docx')->deleteFileAfterSend();
    }

    public function excel(Request $request): BinaryFileResponse
    {
        [, $titreFr, , $colonnes, $lignes] = $this->preparer($request);

        return Excel::download(
            new ListePersonnaliseeExport($colonnes, $lignes, $this->entetes(), $titreFr),
            'liste-personnalisee-enseignants.xlsx',
        );
    }

    /**
     * @return array{0: School, 1: string, 2: string, 3: list<string>, 4: list<array<string, string>>, 5: array<string, string>}
     */
    private function preparer(Request $request): array
    {
        $data = $request->validate([
            'titre_fr' => ['required', 'string', 'max:255'],
            'titre_en' => ['required', 'string', 'max:255'],
            'colonnes' => ['required', 'string'],
            'search' => ['nullable', 'string', 'max:120'],
            'departement_id' => ['nullable', 'integer'],
            'statut' => ['nullable', Rule::in(['actif', 'ex_employe'])],
        ]);

        $colonnes = $this->colonnesValidees($data['colonnes']);
        $lignes = $this->lignes($request, $colonnes);
        $schoolIds = Tenant::schoolIds();
        $school = School::whereIn('id', $schoolIds)->orderBy('name')->firstOrFail();
        $meta = Tenant::isAggregate() ? ['Écoles / Schools' => (string) count($schoolIds)] : ['École / School' => $school->name];

        return [$school, $data['titre_fr'], $data['titre_en'], $colonnes, $lignes, $meta];
    }

    /** @return list<array<string, string>> */
    private function lignes(Request $request, array $colonnes): array
    {
        $personnels = Personnel::forSchool(Tenant::schoolIds())
            ->with(['departement', 'fonctionReference', 'school:id,name,code,type'])
            ->whereHas('fonctionReference', fn ($query) => $query->whereRaw('LOWER(label_fr) = ?', ['enseignant']))
            ->when($request->string('search')->toString(), fn ($query, $search) => $query->where('nom_complet', 'like', "%{$search}%"))
            ->when($request->integer('departement_id') ?: null, fn ($query, $id) => $query->where('departement_id', $id))
            ->when($request->string('statut')->toString(), fn ($query, $statut) => $query->where('statut', $statut))
            ->orderBy('nom_complet')
            ->get();

        return $personnels->map(function (Personnel $personnel, int $index) use ($colonnes) {
            $ligne = [];
            foreach ($colonnes as $colonne) {
                $ligne[$colonne] = $this->valeurColonne($colonne, $personnel, $index + 1);
            }

            return $ligne;
        })->values()->all();
    }

    private function valeurColonne(string $colonne, Personnel $personnel, int $rang): string
    {
        return match ($colonne) {
            'numero' => (string) $rang,
            'matricule' => $personnel->matricule ?: '—',
            'nom_prenom' => $personnel->nom_complet,
            'fonction' => $personnel->fonction ?: '—',
            'departement' => $personnel->departement?->nom ?: '—',
            'telephone' => $personnel->telephone ?: '—',
            'telephone_2' => $personnel->telephone_2 ?: '—',
            'email' => $personnel->email ?: '—',
            'sexe' => $personnel->sexe ?: '—',
            'date_naissance' => $personnel->date_naissance?->format('d/m/Y') ?? '—',
            'anciennete' => $personnel->anciennete !== null ? $personnel->anciennete.' an(s)' : '—',
            'type_contrat' => $personnel->type_contrat ?: '—',
            'statut_contrat' => $personnel->statut_contrat ?: '—',
            'grade_minedub' => $personnel->grade_minedub ?: '—',
            'categorie_echelon' => $personnel->categorie_echelon ?: '—',
            'diplome_professionnel' => $personnel->diplome_professionnel ?: '—',
            'diplome_academique' => $personnel->diplome_academique ?: '—',
            'residence' => $personnel->residence ?: '—',
            'affectation' => $personnel->affectation ?: '—',
            'compte' => $personnel->user_id ? 'Oui' : 'Non',
            'statut' => $personnel->statut === 'actif' ? 'Actif' : 'Ex-employé',
            'ecole' => $personnel->school?->name ?: '—',
            default => '—',
        };
    }

    /** @return list<string> */
    private function colonnesValidees(string $colonnesBrutes): array
    {
        $colonnes = array_values(array_filter(array_map('trim', explode(',', $colonnesBrutes))));

        abort_if($colonnes === [], 422, 'Au moins une colonne doit être choisie.');
        abort_if(array_diff($colonnes, ListeEnseignantColonnes::clesValides()) !== [], 422, 'Colonne inconnue.');

        return $colonnes;
    }

    private function validerModele(Request $request): array
    {
        return $request->validate([
            'titre_fr' => ['required', 'string', 'max:255'],
            'titre_en' => ['required', 'string', 'max:255'],
            'colonnes' => ['required', 'array', 'min:1'],
            'colonnes.*' => [Rule::in(ListeEnseignantColonnes::clesValides())],
        ]);
    }

    private function modele(Request $request, int $id): ListePersonnaliseeModele
    {
        return ListePersonnaliseeModele::forSchool(Tenant::schoolIds())
            ->where('user_id', $request->user()->id)
            ->where('domaine', self::DOMAINE)
            ->findOrFail($id);
    }

    /** @return array<string, string> */
    private function entetes(): array
    {
        return collect(ListeEnseignantColonnes::clesValides())
            ->mapWithKeys(fn (string $cle) => [$cle => ListeEnseignantColonnes::libelle($cle)])
            ->all();
    }
}
