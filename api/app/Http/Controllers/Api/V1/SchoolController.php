<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateSchoolRequest;
use App\Http\Resources\Api\V1\SchoolResource;
use App\Models\School;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SchoolController extends Controller
{
    public function show(): JsonResponse
    {
        $school = School::with('niveaux')->findOrFail(app('tenant.school_id'));

        return ApiResponse::success(new SchoolResource($school));
    }

    public function update(UpdateSchoolRequest $request): JsonResponse
    {
        $school = School::findOrFail(app('tenant.school_id'));
        $data = $request->validated();

        DB::transaction(function () use ($school, $data) {
            $school->update([
                'name' => $data['name'],
                'address' => $data['address'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'header_fr' => $data['header_fr'] ?? null,
                'header_en' => $data['header_en'] ?? null,
            ]);
            $school->niveaux()->sync($data['niveau_ids']);
        });

        return ApiResponse::success(new SchoolResource($school->refresh()->load('niveaux')), 'Profil de l\'établissement mis à jour.');
    }

    /**
     * Logo, cachet et signature de l'établissement, apposés sur les documents
     * officiels (bulletins, cartes scolaires, attestations). Le fichier est
     * stocké tel quel : convertir en JPEG aplatirait la transparence dont le
     * cachet et la signature ont besoin pour se superposer au document.
     */
    public function uploadImage(Request $request, string $type): JsonResponse
    {
        $colonnes = ['logo' => 'logo_path', 'cachet' => 'stamp_path', 'signature' => 'signature_path'];

        if (! isset($colonnes[$type])) {
            return ApiResponse::error('Type d\'image inconnu.', 422);
        }

        $request->validate(['image' => ['required', 'file', 'mimes:jpeg,jpg,png', 'max:5120']]);

        $school = School::findOrFail(app('tenant.school_id'));
        $extension = $request->file('image')->extension();
        $chemin = 'ecoles/'.$school->id.'/'.$type.'.'.$extension;

        $ancien = $school->{$colonnes[$type]};
        if ($ancien && $ancien !== $chemin) {
            Storage::disk('public')->delete($ancien);
        }

        Storage::disk('public')->put($chemin, $request->file('image')->getContent());
        $school->update([$colonnes[$type] => $chemin]);

        return ApiResponse::success(
            new SchoolResource($school->refresh()->load('niveaux')),
            'Image de l\'établissement mise à jour.'
        );
    }

    public function deleteImage(string $type): JsonResponse
    {
        $colonnes = ['logo' => 'logo_path', 'cachet' => 'stamp_path', 'signature' => 'signature_path'];

        if (! isset($colonnes[$type])) {
            return ApiResponse::error('Type d\'image inconnu.', 422);
        }

        $school = School::findOrFail(app('tenant.school_id'));

        if ($chemin = $school->{$colonnes[$type]}) {
            Storage::disk('public')->delete($chemin);
        }

        $school->update([$colonnes[$type] => null]);

        return ApiResponse::success(
            new SchoolResource($school->refresh()->load('niveaux')),
            'Image supprimée.'
        );
    }
}
