<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\JustificationAbsence;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Consultation, côté établissement, des justifications d'absence déposées
 * par les parents — en lecture seule : le statut (`en_attente`/`appliquee`)
 * n'est pas décidé ici mais par le rapprochement automatique avec l'appel
 * réel, cf. {@see \App\Services\JustificationAbsenceService::marquerAppliquee()}.
 */
class JustificationAbsenceAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $justifications = JustificationAbsence::forSchool(Tenant::schoolIds())
            ->with(['tuteur:id,nom_complet,telephone,email', 'eleve:id,nom_complet,matricule,classe_id', 'eleve.classe:id,nom'])
            ->when($request->string('statut')->toString(), fn ($q, $s) => $q->where('statut', $s))
            ->latest('date_debut')
            ->get();

        return ApiResponse::success($justifications->map(fn (JustificationAbsence $j) => $this->resume($j)));
    }

    public function show(int $id): JsonResponse
    {
        $j = JustificationAbsence::forSchool(Tenant::schoolIds())
            ->with(['tuteur:id,nom_complet,telephone,email', 'eleve:id,nom_complet,matricule,classe_id', 'eleve.classe:id,nom'])
            ->findOrFail($id);

        return ApiResponse::success([
            ...$this->resume($j),
            'description' => $j->description,
        ]);
    }

    private function resume(JustificationAbsence $j): array
    {
        return [
            'id' => $j->id,
            'statut' => $j->statut,
            'motif' => $j->motif,
            'date_debut' => $j->date_debut->format('Y-m-d'),
            'date_fin' => $j->date_fin->format('Y-m-d'),
            'tuteur' => $j->tuteur ? ['id' => $j->tuteur->id, 'nom_complet' => $j->tuteur->nom_complet, 'telephone' => $j->tuteur->telephone, 'email' => $j->tuteur->email] : null,
            'eleve' => $j->eleve ? ['id' => $j->eleve->id, 'nom_complet' => $j->eleve->nom_complet, 'matricule' => $j->eleve->matricule, 'classe' => $j->eleve->classe?->nom] : null,
            'created_at' => $j->created_at->format('Y-m-d H:i'),
        ];
    }
}
