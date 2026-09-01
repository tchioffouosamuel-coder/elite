<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreTrimestreRequest;
use App\Http\Requests\Api\V1\UpdateTrimestreRequest;
use App\Http\Resources\Api\V1\TrimestreResource;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Setting;
use App\Models\Trimestre;
use App\Services\EmploiDuTempsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TrimestreController extends Controller
{
    public function index(): JsonResponse
    {
        $trimestres = Trimestre::whereHas(
            'anneeScolaire',
            fn ($q) => $q->where('school_id', app('tenant.school_id'))
        )->with('anneeScolaire')->orderBy('ordre')->get();

        // Les séquences excédentaires (réglage `num_sequences` baissé après
        // coup) restent en base pour ne rien perdre, mais ne doivent jamais
        // apparaître dans les écrans de saisie : cf. `sequencesRetenues()`.
        $trimestres->each(fn (Trimestre $t) => $t->setRelation('sequences', $t->sequencesRetenues()));

        return ApiResponse::success(TrimestreResource::collection($trimestres));
    }

    public function store(StoreTrimestreRequest $request): JsonResponse
    {
        $schoolId = app('tenant.school_id');
        AnneeScolaire::where('school_id', $schoolId)->findOrFail($request->integer('annee_scolaire_id'));

        $trimestre = DB::transaction(function () use ($request, $schoolId) {
            $trimestre = Trimestre::create($request->validated());

            $numSequences = (int) Setting::get($schoolId, 'num_sequences', 2);
            for ($i = 1; $i <= $numSequences; $i++) {
                $trimestre->sequences()->create(['ordre' => $i, 'libelle' => "Séquence {$i}"]);
            }

            return $trimestre;
        });

        return ApiResponse::created(new TrimestreResource($trimestre->load('sequences')), 'Trimestre créé.');
    }

    public function activate(int $id): JsonResponse
    {
        $schoolId = app('tenant.school_id');
        $trimestre = Trimestre::whereHas('anneeScolaire', fn ($q) => $q->where('school_id', $schoolId))->findOrFail($id);

        DB::transaction(function () use ($trimestre) {
            Trimestre::where('annee_scolaire_id', $trimestre->annee_scolaire_id)->update(['is_active' => false]);
            $trimestre->update(['is_active' => true]);
        });

        $trimestre->refresh()->load('anneeScolaire');
        $trimestre->setRelation('sequences', $trimestre->sequencesRetenues());

        return ApiResponse::success(new TrimestreResource($trimestre), 'Trimestre activé.');
    }

    public function update(int $id, UpdateTrimestreRequest $request): JsonResponse
    {
        $schoolId = app('tenant.school_id');
        $trimestre = Trimestre::whereHas('anneeScolaire', fn ($q) => $q->where('school_id', $schoolId))->findOrFail($id);

        $trimestre->update($request->validated());
        $trimestre->load('anneeScolaire');
        $trimestre->setRelation('sequences', $trimestre->sequencesRetenues());

        return ApiResponse::success(new TrimestreResource($trimestre), 'Trimestre mis à jour.');
    }

    /** Matérialise les séances de toutes les classes de l'année, pour ce trimestre, en un coup. */
    public function genererSeances(int $id, Request $request, EmploiDuTempsService $service): JsonResponse
    {
        $schoolId = app('tenant.school_id');
        $trimestre = Trimestre::whereHas('anneeScolaire', fn ($q) => $q->where('school_id', $schoolId))->findOrFail($id);

        // Une classe est un gabarit permanent de l'établissement (cf. migration
        // 2026_08_23_150000) : elle ne se rattache pas à une année précise, donc
        // « les classes de ce trimestre » sont simplement celles de l'école,
        // bornées au périmètre de l'agent.
        $classes = Classe::forSchool($schoolId)
            ->dansPerimetre($request->user())
            ->get();

        $resultat = $service->genererSeancesPourClasses($classes, $trimestre->date_debut, $trimestre->date_fin, $trimestre);

        return ApiResponse::success($resultat, "{$resultat['creees']} séance(s) générée(s) sur {$resultat['classes']} classe(s).");
    }
}
