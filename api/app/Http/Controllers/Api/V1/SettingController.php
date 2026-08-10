<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\SettingsCatalog;
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
        $validKeys = collect(SettingsCatalog::definitions())->pluck('key');

        $data = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*' => ['nullable'],
        ]);

        foreach ($data['settings'] as $key => $value) {
            if (! $validKeys->contains($key) || $value === null || $value === '') {
                continue;
            }
            Setting::set($schoolId, $key, $value);
        }

        return ApiResponse::success(message: 'Préférences mises à jour.');
    }
}
