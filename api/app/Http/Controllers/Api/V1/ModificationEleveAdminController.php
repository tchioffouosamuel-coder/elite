<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ModificationEleve;
use App\Services\ModificationEleveService;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/** File d'attente des révisions d'identité/santé proposées par les parents, à valider ou rejeter. */
class ModificationEleveAdminController extends Controller
{
    public function __construct(private readonly ModificationEleveService $service) {}

    public function index(Request $request): JsonResponse
    {
        $modifications = ModificationEleve::forSchool(Tenant::schoolIds())
            ->with(['tuteur:id,nom_complet,telephone,email', 'eleve:id,nom_complet,matricule'])
            ->when($request->string('statut')->toString(), fn ($q, $s) => $q->where('statut', $s))
            ->latest()
            ->get();

        return ApiResponse::success($modifications->map(fn (ModificationEleve $m) => $this->resume($m)));
    }

    public function show(int $id): JsonResponse
    {
        $m = ModificationEleve::forSchool(Tenant::schoolIds())
            ->with(['tuteur:id,nom_complet,telephone,email', 'eleve:id,nom_complet,matricule'])
            ->findOrFail($id);

        $donnees = $m->donnees;
        $valeursActuelles = collect($donnees)->keys()->mapWithKeys(fn ($cle) => [$cle => $m->eleve->{$cle}])->all();

        // `photo_path` est un chemin de stockage, pas une valeur à afficher
        // telle quelle : on le résout en URL des deux côtés (actuelle et
        // proposée) pour que l'admin voie les deux photos, pas deux chemins.
        if (isset($donnees['photo_path'])) {
            $donnees['photo_path'] = asset('storage/' . $donnees['photo_path']);
            $valeursActuelles['photo_path'] = $valeursActuelles['photo_path']
                ? asset('storage/' . $valeursActuelles['photo_path'])
                : null;
        }

        return ApiResponse::success([
            ...$this->resume($m),
            'donnees' => $donnees,
            'valeurs_actuelles' => $valeursActuelles,
        ]);
    }

    public function valider(Request $request, int $id): JsonResponse
    {
        $m = ModificationEleve::forSchool(Tenant::schoolIds())->findOrFail($id);

        try {
            $m = $this->service->valider($m, $request->user()?->id);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success($this->resume($m), 'Modification validée et appliquée.');
    }

    public function rejeter(Request $request, int $id): JsonResponse
    {
        $m = ModificationEleve::forSchool(Tenant::schoolIds())->findOrFail($id);
        $data = $request->validate(['motif' => ['required', 'string', 'min:3', 'max:255']]);

        try {
            $m = $this->service->rejeter($m, $data['motif'], $request->user()?->id);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success($this->resume($m), 'Modification rejetée.');
    }

    private function resume(ModificationEleve $m): array
    {
        return [
            'id' => $m->id,
            'statut' => $m->statut,
            'tuteur' => $m->tuteur ? ['id' => $m->tuteur->id, 'nom_complet' => $m->tuteur->nom_complet, 'telephone' => $m->tuteur->telephone, 'email' => $m->tuteur->email] : null,
            'eleve' => $m->eleve ? ['id' => $m->eleve->id, 'nom_complet' => $m->eleve->nom_complet, 'matricule' => $m->eleve->matricule] : null,
            'motif_rejet' => $m->motif_rejet,
            'created_at' => $m->created_at->format('Y-m-d H:i'),
            'traite_le' => $m->traite_le?->format('Y-m-d H:i'),
        ];
    }
}
