<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\InventaireArticle;
use App\Services\InventaireService;
use App\Support\Pdf\EtiquettesArticlesGenerator;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InventaireController extends Controller
{
    public function __construct(private readonly InventaireService $service) {}

    public function index(Request $request): JsonResponse
    {
        $filtres = [
            'categorie' => $request->string('categorie')->toString() ?: null,
            'etat' => $request->string('etat')->toString() ?: null,
            'search' => $request->string('search')->toString() ?: null,
        ];

        $articles = $this->service->lister(Tenant::schoolIds(), $filtres);

        return ApiResponse::success([
            'articles' => $articles->map(fn (InventaireArticle $a) => $this->resumer($a))->values(),
            'stats' => $this->service->stats(Tenant::schoolIds()),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $donnees = $this->valider($request);
        $partage = (bool) ($donnees['toutes_ecoles'] ?? false);
        unset($donnees['school_id'], $donnees['toutes_ecoles']);

        /*
         * « Toutes les écoles » : un seul article sans école, donc un seul
         * stock où les trois établissements puisent ensemble.
         */
        $schoolId = $partage ? null : Tenant::resolveWriteSchoolId($request->input('school_id'));
        $article = $this->service->creer($schoolId, $donnees);

        return ApiResponse::created(
            $this->resumer($article->load('school:id,name,code,type')),
            $partage ? 'Article ajouté, partagé par toutes les écoles.' : 'Article ajouté.'
        );
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $article = $this->service->trouver(Tenant::schoolIds(), $id);
        $donnees = $this->valider($request);
        unset($donnees['school_id']);

        $article = $this->service->modifier($article, $donnees);

        return ApiResponse::success($this->resumer($article->load('school:id,name,code,type')), 'Article mis à jour.');
    }

    public function destroy(int $id): JsonResponse
    {
        $article = $this->service->trouver(Tenant::schoolIds(), $id);
        $this->service->supprimer($article);

        return ApiResponse::success(null, 'Article supprimé.');
    }

    /**
     * Attribue son code-barres à l'article — l'action « générer le code-barres »
     * de la fiche inventaire. Idempotente : un article déjà étiqueté conserve
     * son code, sans quoi les étiquettes déjà collées deviendraient muettes.
     */
    public function codeBarre(int $id): JsonResponse
    {
        $article = $this->service->trouver(Tenant::schoolIds(), $id);
        $dejaAttribue = $article->code_barre !== null;

        $article = $this->service->attribuerCodeBarre($article);

        return ApiResponse::success(
            $this->resumer($article->load('school:id,name,code,type')),
            $dejaAttribue ? 'Cet article porte déjà ce code-barres.' : 'Code-barres attribué.',
        );
    }

    /**
     * Planche d'étiquettes à découper et coller. Les articles qui n'ont pas
     * encore de code en reçoivent un au passage : imprimer une étiquette et
     * l'attribuer sont le même geste pour l'économe.
     */
    public function etiquettes(Request $request): Response
    {
        $donnees = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            // Impose le même tirage à tous les articles sélectionnés (ex. un
            // carton de 50 cahiers identiques). Sans lui, chaque article tire
            // autant d'étiquettes que sa quantité en stock — l'économe n'a
            // pas à ressaisir un nombre qu'il vient de saisir sur la fiche.
            'exemplaires' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $articles = $this->service->attribuerCodesBarres(Tenant::schoolIds(), $donnees['ids']);

        $tirages = $articles->mapWithKeys(fn (InventaireArticle $a) => [
            // Plafonné comme le tirage explicite : une quantité en stock mal
            // saisie ne doit pas produire une planche de plusieurs centaines
            // de pages.
            $a->id => $donnees['exemplaires'] ?? min(200, max(1, $a->quantite)),
        ])->all();

        $pdf = (new EtiquettesArticlesGenerator)->build($articles, $articles->first()?->school, $tirages);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="etiquettes-articles.pdf"',
        ]);
    }

    /** @return array<string, mixed> */
    private function valider(Request $request): array
    {
        return $request->validate([
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            // Exclusif de school_id : l'article n'appartient à aucune école en
            // particulier, les trois partagent son stock.
            'toutes_ecoles' => ['nullable', 'boolean'],
            'nom' => ['required', 'string', 'max:150'],
            'categorie' => ['required', 'in:mobilier,informatique,pedagogique,sport,medical,autre'],
            'quantite' => ['required', 'integer', 'min:1'],
            'etat' => ['required', 'in:bon,moyen,mauvais,hors_service'],
            'localisation' => ['nullable', 'string', 'max:150'],
            'valeur_unitaire' => ['nullable', 'integer', 'min:0'],
            // Renseigner un prix suffit à mettre l'article au comptoir : pas de
            // drapeau « en vente » séparé, qui pourrait le contredire.
            'prix_vente' => ['nullable', 'integer', 'min:0'],
            'date_acquisition' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);
    }

    /** @return array<string, mixed> */
    private function resumer(InventaireArticle $article): array
    {
        return [
            'id' => $article->id,
            'nom' => $article->nom,
            'code_barre' => $article->code_barre,
            'categorie' => $article->categorie,
            'quantite' => $article->quantite,
            'etat' => $article->etat,
            'localisation' => $article->localisation,
            'valeur_unitaire' => $article->valeur_unitaire,
            'valeur_totale' => $article->valeur_totale,
            'prix_vente' => $article->prix_vente,
            'valeur_vente' => $article->prix_vente === null ? null : $article->valeur_vente,
            'date_acquisition' => $article->date_acquisition?->format('Y-m-d'),
            'notes' => $article->notes,
            'school' => $article->school ? [
                'id' => $article->school->id,
                'name' => $article->school->name,
                'code' => $article->school->code,
                'type' => $article->school->type,
            ] : null,
        ];
    }
}
