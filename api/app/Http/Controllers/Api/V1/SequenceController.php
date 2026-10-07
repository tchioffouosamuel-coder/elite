<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SequenceResource;
use App\Models\Sequence;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SequenceController extends Controller
{
    public function saisie(Request $request, int $id): JsonResponse
    {
        abort_if($request->user()->estEnseignant(), 403,
            "Un enseignant ne peut pas ouvrir ou fermer la saisie des notes.");
        $data = $request->validate(['saisie_ouverte' => ['required', 'boolean']]);
        $sequence = DB::transaction(function () use ($id, $data) {
            $sequence = Sequence::whereHas('trimestre.anneeScolaire',
                fn ($q) => $q->whereIn('school_id', Tenant::schoolIds()))
                ->lockForUpdate()->findOrFail($id);
            $sequence->update($data);

            return $sequence;
        });

        return ApiResponse::success(new SequenceResource($sequence));
    }
}
