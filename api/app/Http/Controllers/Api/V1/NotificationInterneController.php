<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\NotificationInterne;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Cloche de notifications internes : chacun ne voit et ne marque que les
 * siennes, aucun privilège particulier n'est requis au-delà d'être connecté.
 */
class NotificationInterneController extends Controller
{
    public function __construct(private readonly NotificationService $service) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success($this->service->pourUtilisateur($request->user()->id));
    }

    public function nonLues(Request $request): JsonResponse
    {
        $total = NotificationInterne::pourUtilisateur($request->user()->id)->where('lu', false)->count();

        return ApiResponse::success(['total' => $total]);
    }

    public function marquerLue(Request $request, int $id): JsonResponse
    {
        $notification = NotificationInterne::pourUtilisateur($request->user()->id)->findOrFail($id);
        $notification->update(['lu' => true, 'lu_le' => now()]);

        return ApiResponse::success($notification);
    }

    public function marquerToutesLues(Request $request): JsonResponse
    {
        NotificationInterne::pourUtilisateur($request->user()->id)
            ->where('lu', false)
            ->update(['lu' => true, 'lu_le' => now()]);

        return ApiResponse::success();
    }
}
