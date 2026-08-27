<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\School;
use App\Services\MatriculeNationalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MatriculeNationalController extends Controller
{
    public function __construct(private readonly MatriculeNationalService $service) {}

    /**
     * Recherche du matricule national d'un élève sur cartescolaire.cm —
     * réservée au secondaire, seul cycle où cartescolaire.cm référence les
     * établissements (cf. School::estSecondaire()).
     */
    public function rechercher(Request $request): JsonResponse
    {
        $school = School::findOrFail(app('tenant.school_id'));

        if (! $school->estSecondaire()) {
            return ApiResponse::error("La recherche de matricule national n'est disponible qu'au secondaire.", 422);
        }

        if (! $school->national_school_code) {
            return ApiResponse::error("Le code national de l'établissement n'est pas configuré (Paramètres > École).", 422);
        }

        $data = $request->validate([
            'student_name' => ['required', 'string', 'min:2', 'max:255'],
        ]);

        $students = $this->service->fetchStudents($data['student_name'], $school->national_school_code);

        return ApiResponse::success($students);
    }
}
