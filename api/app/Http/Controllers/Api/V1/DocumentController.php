<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\ListeClasseModele;
use App\Models\ListePersonnaliseeModele;
use App\Models\Niveau;
use App\Models\Personnel;
use App\Models\School;
use App\Models\SousSysteme;
use App\Models\Trimestre;
use App\Services\PaquetDocumentsService;
use App\Support\Documents\CatalogueDocuments;
use App\Support\Documents\PlanificateurDocuments;
use App\Support\ListeClasseColonnes;
use App\Support\ListeEnseignantColonnes;
use App\Support\ListeTransportColonnes;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Centre de documents : génère n'importe quel document de la plateforme,
 * à l'unité ou en paquet ZIP, sur un périmètre choisi dans la hiérarchie
 * Toutes les écoles › École › Sous-système › Niveau › Classe.
 *
 * Inventaire des documents : {@see CatalogueDocuments}. Production : chaque
 * fichier est obtenu de la route qui le génère déjà (cf. ExecuteurDocument),
 * donc avec les mêmes contrôles d'accès et le même rendu qu'ailleurs.
 */
class DocumentController extends Controller
{
    public function __construct(private readonly PaquetDocumentsService $paquets) {}

    /** Inventaire des documents + tout ce qu'il faut pour remplir le formulaire. */
    public function catalogue(Request $request): JsonResponse
    {
        $schoolIds = Tenant::schoolIds();

        $documents = collect(CatalogueDocuments::documents())->map(fn (array $d, string $code) => [
            'code' => $code,
            'libelle' => $d['libelle'],
            'categorie' => $d['categorie'],
            'unite' => $d['unite'],
            'unite_libelle' => CatalogueDocuments::UNITES[$d['unite']],
            'formats' => array_keys($d['formats']),
            'parametres' => $d['parametres'] ?? [],
            'filtres' => array_keys($d['filtres'] ?? []),
            'types_ecole' => $d['types_ecole'] ?? null,
        ])->values();

        $classes = Classe::whereIn('school_id', $schoolIds)->get(['id', 'school_id', 'sous_systeme_id', 'niveau_id', 'nom']);
        $niveaux = Niveau::whereIn('id', $classes->pluck('niveau_id')->filter()->unique())->get(['id', 'name_fr']);

        $ecoles = School::whereIn('id', $schoolIds)->orderBy('name')->get(['id', 'name', 'type'])
            ->map(fn (School $ecole) => [
                'id' => $ecole->id,
                'nom' => $ecole->name,
                'type' => $ecole->type,
                'sous_systemes' => SousSysteme::where('school_id', $ecole->id)->orderBy('nom')->get(['id', 'nom']),
                'niveaux' => $classes->where('school_id', $ecole->id)->whereNotNull('niveau_id')
                    ->groupBy('niveau_id')
                    ->map(fn ($groupe, $niveauId) => [
                        'id' => (int) $niveauId,
                        'nom' => $niveaux->firstWhere('id', $niveauId)?->name_fr ?? "Niveau {$niveauId}",
                        'sous_systeme_ids' => $groupe->pluck('sous_systeme_id')->filter()->unique()->values(),
                    ])->sortBy('nom', SORT_NATURAL)->values(),
                'classes' => $classes->where('school_id', $ecole->id)->sortBy('nom', SORT_NATURAL)->values()
                    ->map(fn (Classe $c) => ['id' => $c->id, 'nom' => $c->nom, 'sous_systeme_id' => $c->sous_systeme_id, 'niveau_id' => $c->niveau_id]),
            ]);

        $annees = AnneeScolaire::whereIn('school_id', $schoolIds)->orderByDesc('date_debut')->get(['libelle', 'is_active', 'archivee_le']);
        $trimestres = Trimestre::whereHas('anneeScolaire', fn ($q) => $q->whereIn('school_id', $schoolIds)->where('is_active', true))
            ->orderBy('ordre')->get(['ordre', 'libelle'])->unique('ordre')->values();

        $user = $request->user();

        return ApiResponse::success([
            'categories' => CatalogueDocuments::CATEGORIES,
            'formats' => CatalogueDocuments::FORMATS,
            'documents' => $documents,
            'ecoles' => $ecoles,
            'annees' => $annees->unique('libelle')->map(fn ($a) => ['libelle' => $a->libelle, 'active' => (bool) $a->is_active, 'archivee' => $a->archivee_le !== null])->values(),
            'trimestres' => $trimestres->map(fn ($t) => ['ordre' => $t->ordre, 'libelle' => $t->libelle]),
            'colonnes' => [
                'liste_classe' => $this->colonnes(ListeClasseColonnes::DEFINITIONS),
                'liste_personnel' => $this->colonnes(ListeEnseignantColonnes::DEFINITIONS),
                'liste_transport' => $this->colonnes(ListeTransportColonnes::DEFINITIONS),
            ],
            'modeles_listes' => [
                'liste_classe' => ListeClasseModele::forSchool($schoolIds)->where('user_id', $user->id)->latest('id')->get(['id', 'titre_fr', 'titre_en', 'colonnes', 'moyenne_type']),
                'liste_personnel' => ListePersonnaliseeModele::forSchool($schoolIds)->where('user_id', $user->id)->where('domaine', 'enseignants')->latest('id')->get(['id', 'titre_fr', 'titre_en', 'colonnes']),
                'liste_transport' => ListePersonnaliseeModele::forSchool($schoolIds)->where('user_id', $user->id)->where('domaine', 'transport')->latest('id')->get(['id', 'titre_fr', 'titre_en', 'colonnes']),
            ],
        ]);
    }

    /** Élèves d'une classe ou agents d'une école, pour viser un document individuel. */
    public function cibles(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['eleve', 'personnel'])],
            'classe_id' => ['required_if:type,eleve', 'nullable', 'integer'],
            'school_id' => ['required_if:type,personnel', 'nullable', 'integer'],
        ]);

        $schoolIds = Tenant::schoolIds();

        $cibles = $data['type'] === 'eleve'
            ? Eleve::whereIn('school_id', $schoolIds)->where('classe_id', $data['classe_id'])->where('statut', 'actif')
                ->orderBy('nom_complet')->get(['id', 'nom_complet as nom', 'matricule'])
            : Personnel::whereIn('school_id', $schoolIds)->where('school_id', $data['school_id'])->where('statut', 'actif')
                ->orderBy('nom_complet')->get(['id', 'nom_complet as nom', 'matricule']);

        return ApiResponse::success($cibles);
    }

    /**
     * Prépare un paquet. Avec `simulation`, ne fait que compter : l'écran
     * annonce combien de fichiers seront produits avant de lancer quoi que
     * ce soit.
     */
    public function preparer(Request $request): JsonResponse
    {
        $codes = array_keys(CatalogueDocuments::documents());

        $data = $request->validate([
            'documents' => ['required', 'array', 'min:1'],
            'documents.*.code' => ['required', 'string', Rule::in($codes)],
            'documents.*.formats' => ['required', 'array', 'min:1'],
            'documents.*.formats.*' => ['string', Rule::in(array_keys(CatalogueDocuments::FORMATS))],
            'perimetre' => ['nullable', 'array'],
            'perimetre.school_id' => ['nullable', 'integer'],
            'perimetre.sous_systeme_id' => ['nullable', 'integer'],
            'perimetre.niveau_id' => ['nullable', 'integer'],
            'perimetre.classe_id' => ['nullable', 'integer'],
            'perimetre.eleve_id' => ['nullable', 'integer'],
            'perimetre.personnel_id' => ['nullable', 'integer'],
            'parametres' => ['nullable', 'array'],
            'parametres.trimestre' => ['nullable'],
            'parametres.annee' => ['nullable', 'string'],
            'parametres.du' => ['nullable', 'date'],
            'parametres.au' => ['nullable', 'date', 'after_or_equal:parametres.du'],
            'parametres.mois' => ['nullable', 'integer', 'min:1', 'max:12'],
            'parametres.annee_paie' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'parametres.date' => ['nullable', 'date'],
            'parametres.semaine' => ['nullable', 'date'],
            'parametres.liste_classe' => ['nullable', 'array'],
            'parametres.liste_personnel' => ['nullable', 'array'],
            'parametres.liste_transport' => ['nullable', 'array'],
            'simulation' => ['nullable', 'boolean'],
        ]);

        $items = (new PlanificateurDocuments(Tenant::schoolIds(), $data['perimetre'] ?? [], $data['parametres'] ?? []))
            ->planifier($data['documents']);

        $resume = [
            'total' => count($items),
            'par_document' => collect($items)->countBy('code'),
            'apercu' => collect($items)->take(15)->map(fn ($i) => trim($i['dossier'].'/'.$i['nom'], '/').' ('.CatalogueDocuments::FORMATS[$i['format']].')')->values(),
        ];

        if ($request->boolean('simulation') || $items === []) {
            return ApiResponse::success($resume);
        }

        return ApiResponse::success([...$resume, 'token' => $this->paquets->creer($request->user(), $items)]);
    }

    public function traiter(Request $request, string $token): JsonResponse
    {
        return ApiResponse::success($this->paquets->traiter($token, $request->user()));
    }

    public function telecharger(Request $request, string $token): Response
    {
        try {
            $fichier = $this->paquets->telechargement($token, $request->user());
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        if ($fichier['contenu'] !== null) {
            $this->paquets->supprimer($token, $request->user());

            return response($fichier['contenu'], 200, [
                'Content-Type' => 'application/octet-stream',
                'Content-Disposition' => 'attachment; filename="'.addslashes($fichier['nom']).'"; filename*=UTF-8\'\''.rawurlencode($fichier['nom']),
            ]);
        }

        // Le dossier reste jusqu'à la purge automatique : un téléchargement
        // interrompu peut être relancé.
        return response()->download($fichier['chemin'], $fichier['nom'], ['Content-Type' => 'application/zip']);
    }

    public function supprimer(Request $request, string $token): JsonResponse
    {
        $this->paquets->supprimer($token, $request->user());

        return ApiResponse::success(message: 'Paquet supprimé.');
    }

    /** @param  array<string, array{fr: string, en: string}>  $definitions */
    private function colonnes(array $definitions): array
    {
        return collect($definitions)->map(fn ($libelles, $cle) => ['cle' => $cle, 'libelle' => $libelles['fr'] ?? $cle])->values()->all();
    }
}
