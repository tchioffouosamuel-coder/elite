<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreRevendicationRequest;
use App\Http\Requests\Api\V1\UpdateRevendicationRequest;
use App\Http\Resources\Api\V1\RevendicationResource;
use App\Models\ClasseMatiere;
use App\Models\Eleve;
use App\Models\Revendication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contestations de notes ou de décisions remontées par un tuteur ou un
 * élève : faute de portail dédié, c'est l'administration qui les enregistre
 * pour son compte et suit leur traitement jusqu'à la décision motivée.
 */
class RevendicationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $revendications = Revendication::forSchool(app('tenant.school_id'))
            ->with(['eleve.classe', 'classeMatiere.matiere', 'trimestre', 'enregistrePar', 'traitePar'])
            ->when($request->integer('eleve_id'), fn ($q, $id) => $q->where('eleve_id', $id))
            ->when($request->string('statut')->toString(), fn ($q, $statut) => $q->where('statut', $statut))
            ->when($request->string('type')->toString(), fn ($q, $type) => $q->where('type', $type))
            ->when($request->integer('classe_id'), fn ($q, $id) => $q->whereHas(
                'eleve', fn ($qq) => $qq->where('classe_id', $id)
            ))
            ->latest('date_reception')
            ->get();

        return ApiResponse::success(RevendicationResource::collection($revendications));
    }

    public function store(StoreRevendicationRequest $request): JsonResponse
    {
        $eleve = Eleve::forSchool(app('tenant.school_id'))->findOrFail($request->integer('eleve_id'));

        $donnees = $request->validated();

        if (! empty($donnees['classe_matiere_id'])) {
            // Vérifie la portée par l'établissement : classe_matieres n'a pas
            // de colonne school_id directe (cf. StoreRevendicationRequest).
            ClasseMatiere::forSchool(app('tenant.school_id'))->findOrFail($donnees['classe_matiere_id']);
        }

        $revendication = Revendication::create([
            ...$donnees,
            'eleve_id' => $eleve->id,
            // Le défaut SQL de la colonne ne se reflète pas sur l'instance
            // Eloquent tant qu'elle n'est pas rechargée — autant le fixer ici.
            'statut' => 'en_attente',
            'enregistre_par' => $request->user()->personnel?->id,
        ])->load(['eleve.classe', 'classeMatiere.matiere', 'trimestre', 'enregistrePar']);

        return ApiResponse::created(new RevendicationResource($revendication), 'Réclamation enregistrée.');
    }

    public function update(UpdateRevendicationRequest $request, int $id): JsonResponse
    {
        $revendication = Revendication::forSchool(app('tenant.school_id'))->findOrFail($id);

        $donnees = $request->validated();

        // La date et l'auteur du traitement se déduisent du geste lui-même :
        // personne ne devrait avoir à les ressaisir à la main.
        if (in_array($donnees['statut'], ['resolue', 'rejetee'], true)) {
            $donnees['date_traitement'] = now()->toDateString();
            $donnees['traite_par'] = $request->user()->personnel?->id;
        }

        $revendication->update($donnees);

        return ApiResponse::success(
            new RevendicationResource($revendication->fresh(['eleve.classe', 'classeMatiere.matiere', 'trimestre', 'enregistrePar', 'traitePar'])),
            'Réclamation mise à jour.'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        Revendication::forSchool(app('tenant.school_id'))->findOrFail($id)->delete();

        return ApiResponse::success(message: 'Réclamation supprimée.');
    }
}
