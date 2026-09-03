<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Eleve;
use App\Models\Observation;
use App\Services\ObservationService;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Côté établissement, le fil d'observations est consulté par élève (un
 * même fil, alimenté des deux côtés — cf. {@see Observation}), pas comme une
 * liste plate de messages : `index()` renvoie un fil par élève concerné,
 * avec son dernier message, `show()` le détail complet.
 */
class ObservationAdminController extends Controller
{
    public function __construct(private readonly ObservationService $service) {}

    public function index(Request $request): JsonResponse
    {
        $observations = Observation::forSchool(Tenant::schoolIds())
            ->with(['eleve:id,nom_complet,matricule', 'user:id,name'])
            // `id` en second critère : deux messages tombant dans la même
            // seconde (une réponse tapée juste après le message reçu) ne
            // doivent pas se départager au hasard du moteur de base de données.
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $fils = $observations->groupBy('eleve_id')
            ->map(function ($groupe) {
                /** @var Observation $dernier */
                $dernier = $groupe->first();

                return [
                    'eleve' => $dernier->eleve ? ['id' => $dernier->eleve->id, 'nom_complet' => $dernier->eleve->nom_complet, 'matricule' => $dernier->eleve->matricule] : null,
                    'dernier_message' => $dernier->contenu,
                    'derniere_origine' => $dernier->user?->hasRole('parent') ? 'parent' : 'ecole',
                    'total' => $groupe->count(),
                    'derniere_date' => $dernier->created_at->format('Y-m-d H:i'),
                ];
            })
            ->filter(fn ($fil) => $fil['eleve'] !== null)
            ->sortByDesc('derniere_date')
            ->values();

        return ApiResponse::success($fils);
    }

    public function show(int $eleveId): JsonResponse
    {
        $eleve = Eleve::forSchool(Tenant::schoolIds())->findOrFail($eleveId);

        $observations = Observation::where('eleve_id', $eleve->id)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Observation $o) => [
                'id' => $o->id,
                'contenu' => $o->contenu,
                'auteur' => $o->user?->name,
                'origine' => $o->user?->hasRole('parent') ? 'parent' : 'ecole',
                'date' => $o->created_at->format('Y-m-d H:i'),
            ]);

        return ApiResponse::success([
            'eleve' => ['id' => $eleve->id, 'nom_complet' => $eleve->nom_complet, 'matricule' => $eleve->matricule],
            'observations' => $observations,
        ]);
    }

    /** Réponse de l'établissement dans le fil — ne notifie pas (seul un message parent notifie, cf. ObservationService::creer()). */
    public function repondre(Request $request, int $eleveId): JsonResponse
    {
        $eleve = Eleve::forSchool(Tenant::schoolIds())->findOrFail($eleveId);
        $data = $request->validate(['contenu' => ['required', 'string', 'max:2000']]);

        $observation = $this->service->creer($eleve, $request->user(), $data['contenu']);

        return ApiResponse::created($observation, 'Réponse envoyée.');
    }
}
