<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateSchoolRequest;
use App\Http\Resources\Api\V1\SchoolResource;
use App\Models\School;
use App\Support\Pdf\EnTeteHtml;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SchoolController extends Controller
{
    public function show(): JsonResponse
    {
        $school = School::findOrFail(app('tenant.school_id'));

        return ApiResponse::success(new SchoolResource($school));
    }

    public function update(UpdateSchoolRequest $request): JsonResponse
    {
        $school = School::findOrFail(app('tenant.school_id'));
        $data = $request->validated();

        $school->update([
            'name' => $data['name'],
            'address' => $data['address'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'header_fr' => EnTeteHtml::nettoyer($data['header_fr'] ?? null),
            'header_en' => EnTeteHtml::nettoyer($data['header_en'] ?? null),
            'national_school_code' => $data['national_school_code'] ?? null,
        ]);

        return ApiResponse::success(new SchoolResource($school->refresh()), 'Profil de l\'établissement mis à jour.');
    }

    /**
     * Logo, cachet et signature de l'établissement, apposés sur les documents
     * officiels (bulletins, cartes scolaires, attestations). Redimensionné
     * sans changer de format : convertir en JPEG aplatirait la transparence
     * dont le cachet et la signature ont besoin pour se superposer au
     * document, mais garder une photo brute (parfois 4000px+, quelques Mo une
     * fois décodée en bitmap) fait exploser le memory_limit de mPDF dès qu'un
     * bulletin l'embarque — cf. incident sur un logo 4868×2481.
     */
    public function uploadImage(Request $request, string $type): JsonResponse
    {
        $colonnes = ['logo' => 'logo_path', 'cachet' => 'stamp_path', 'signature' => 'signature_path'];

        if (! isset($colonnes[$type])) {
            return ApiResponse::error('Type d\'image inconnu.', 422);
        }

        $request->validate(['image' => ['required', 'file', 'mimes:jpeg,jpg,png', 'max:5120']]);

        $school = School::findOrFail(app('tenant.school_id'));
        $fichier = $request->file('image');
        $extension = $fichier->extension();
        $chemin = 'ecoles/' . $school->id . '/' . $type . '.' . $extension;

        $ancien = $school->{$colonnes[$type]};
        if ($ancien && $ancien !== $chemin) {
            Storage::disk('public')->delete($ancien);
        }

        Storage::disk('public')->put($chemin, $this->redimensionner($fichier->getContent(), $fichier->getMimeType()));
        $school->update([$colonnes[$type] => $chemin]);

        return ApiResponse::success(
            new SchoolResource($school->refresh()),
            'Image de l\'établissement mise à jour.'
        );
    }

    /** Plafonne le plus grand côté à 1000px (largement suffisant à l'impression) sans recadrer ni changer de format. */
    private function redimensionner(string $contenu, string $mime, int $maxCote = 1000): string
    {
        $info = @getimagesizefromstring($contenu);
        if ($info === false) {
            return $contenu;
        }

        $largeur = $info[0];
        $hauteur = $info[1];

        $this->augmenterMemoirePourImage($largeur, $hauteur);

        $source = @imagecreatefromstring($contenu);
        if ($source === false) {
            return $contenu;
        }

        if (max($largeur, $hauteur) <= $maxCote) {
            imagedestroy($source);

            return $contenu;
        }

        $ratio = $maxCote / max($largeur, $hauteur);
        $nLargeur = max(1, (int) round($largeur * $ratio));
        $nHauteur = max(1, (int) round($hauteur * $ratio));

        $redimensionnee = imagecreatetruecolor($nLargeur, $nHauteur);

        if ($mime === 'image/png') {
            imagealphablending($redimensionnee, false);
            imagesavealpha($redimensionnee, true);
            $transparent = imagecolorallocatealpha($redimensionnee, 0, 0, 0, 127);
            imagefilledrectangle($redimensionnee, 0, 0, $nLargeur, $nHauteur, $transparent);
        }

        imagecopyresampled($redimensionnee, $source, 0, 0, 0, 0, $nLargeur, $nHauteur, $largeur, $hauteur);
        imagedestroy($source);

        ob_start();
        $mime === 'image/png' ? imagepng($redimensionnee) : imagejpeg($redimensionnee, null, 90);
        $sortie = ob_get_clean();
        imagedestroy($redimensionnee);

        return $sortie;
    }

    private function augmenterMemoirePourImage(int $largeur, int $hauteur): void
    {
        $limit = trim((string) ini_get('memory_limit'));
        if ($limit === '-1') {
            return;
        }

        $limitBytes = $this->parseMemoryLimit($limit);
        $estimation = max(64 * 1024 * 1024, $largeur * $hauteur * 12 * 2);

        if ($limitBytes >= $estimation) {
            return;
        }

        $nouvelleLimite = min(512 * 1024 * 1024, max($estimation * 2, 256 * 1024 * 1024));
        ini_set('memory_limit', (string) ((int) ceil($nouvelleLimite / (1024 * 1024))) . 'M');
    }

    private function parseMemoryLimit(string $limit): int
    {
        if ($limit === '') {
            return 0;
        }

        $suffixe = strtolower(substr($limit, -1));
        $valeur = (float) $limit;

        return match ($suffixe) {
            'g' => (int) ($valeur * 1024 * 1024 * 1024),
            'm' => (int) ($valeur * 1024 * 1024),
            'k' => (int) ($valeur * 1024),
            default => (int) $valeur,
        };
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
            new SchoolResource($school->refresh()),
            'Image supprimée.'
        );
    }
}
