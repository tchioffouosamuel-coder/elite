<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\SettingsCatalog;
use App\Support\Pdf\EnTeteHtml;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index(): JsonResponse
    {
        $schoolId = app('tenant.school_id');
        $current = Setting::where('school_id', $schoolId)->pluck('value', 'key');

        $settings = collect(SettingsCatalog::definitions())->map(fn ($def) => [
            ...$def,
            'value' => $current->has($def['key']) ? $current->get($def['key']) : $def['default'],
        ]);

        return ApiResponse::success($settings->values());
    }

    public function update(Request $request): JsonResponse
    {
        $schoolId = app('tenant.school_id');
        $types = collect(SettingsCatalog::definitions())->pluck('type', 'key');

        $data = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*' => ['nullable'],
        ]);

        foreach ($data['settings'] as $key => $value) {
            if (! $types->has($key) || $value === null || $value === '') {
                continue;
            }

            // Comme header_fr/header_en : le HTML saisi via l'éditeur WYSIWYG
            // est filtré à la sauvegarde plutôt que d'être imprimé tel quel
            // dans un PDF généré côté serveur.
            if ($types->get($key) === 'richtext') {
                $value = EnTeteHtml::nettoyer((string) $value);
                if ($value === null) {
                    continue;
                }
            }

            Setting::set($schoolId, $key, $value);
        }

        return ApiResponse::success(message: 'Préférences mises à jour.');
    }
}
