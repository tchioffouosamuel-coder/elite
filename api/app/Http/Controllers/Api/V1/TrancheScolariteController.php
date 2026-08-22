<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\TrancheScolarite;
use App\Services\EcheancierService;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Échéancier de scolarité : le découpage de l'année en tranches et leurs dates
 * d'exigibilité.
 *
 * L'échéancier se remplace en bloc plutôt que tranche par tranche : la somme
 * des pourcentages doit valoir 100, et une écriture ligne à ligne laisserait
 * l'établissement dans un état intermédiaire incohérent — 40 % enregistrés
 * pendant qu'on saisit les 60 % suivants, avec des relances fausses entre les
 * deux.
 */
class TrancheScolariteController extends Controller
{
    public function __construct(private readonly EcheancierService $service) {}

    public function index(Request $request): JsonResponse
    {
        $schoolIds = Tenant::schoolIds();

        $anneeId = $request->integer('annee_scolaire_id')
            ?: AnneeScolaire::whereIn('school_id', $schoolIds)->where('is_active', true)->value('id');

        if (! $anneeId) {
            return ApiResponse::error("Aucune année scolaire active pour cet établissement.", 422);
        }

        $tranches = TrancheScolarite::forSchool($schoolIds)
            ->where('annee_scolaire_id', $anneeId)
            ->orderBy('ordre')
            ->get();

        return ApiResponse::success([
            'annee_scolaire_id' => (int) $anneeId,
            'delai_grace' => $this->service->delaiGrace((int) (Tenant::schoolId() ?? $schoolIds[0])),
            'tranches' => $tranches->map(fn (TrancheScolarite $t) => $this->resumer($t))->values(),
        ]);
    }

    /**
     * Remplace l'échéancier de l'année. Un tableau vide le supprime — c'est le
     * moyen de revenir à une scolarité exigible en une fois.
     */
    public function remplacer(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'annee_scolaire_id' => ['required', 'integer', 'exists:annee_scolaires,id'],
            'tranches' => ['present', 'array', 'max:12'],
            'tranches.*.libelle' => ['required', 'string', 'max:100'],
            'tranches.*.pourcentage' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'tranches.*.date_echeance' => ['required', 'date'],
        ]);

        $schoolId = Tenant::resolveWriteSchoolId($donnees['school_id'] ?? null);

        // L'année doit appartenir à l'école visée : sans ce contrôle, un
        // échéancier pourrait être posé sur l'année d'un autre établissement.
        $annee = AnneeScolaire::whereKey($donnees['annee_scolaire_id'])->where('school_id', $schoolId)->first();

        if ($annee === null) {
            return ApiResponse::error("Cette année scolaire n'appartient pas à l'établissement visé.", 422);
        }

        $tranches = collect($donnees['tranches']);

        if ($tranches->isNotEmpty()) {
            $somme = round($tranches->sum(fn (array $t) => (float) $t['pourcentage']), 2);

            if (abs($somme - 100) > 0.01) {
                return ApiResponse::validationError([
                    'tranches' => ["La somme des tranches ({$somme} %) doit valoir 100 %."],
                ]);
            }

            $dates = $tranches->pluck('date_echeance')->map(fn ($d) => (string) $d);

            if ($dates->unique()->count() !== $dates->count()) {
                return ApiResponse::validationError([
                    'tranches' => ['Deux tranches ne peuvent pas partager la même date d\'échéance.'],
                ]);
            }
        }

        // Les dates ordonnent l'échéancier : `ordre` en découle plutôt que
        // d'être saisi, pour qu'il ne puisse pas contredire le calendrier.
        $ordonnees = $tranches->sortBy(fn (array $t) => $t['date_echeance'])->values();

        TrancheScolarite::where('school_id', $schoolId)
            ->where('annee_scolaire_id', $annee->id)
            ->delete();

        foreach ($ordonnees as $index => $tranche) {
            TrancheScolarite::create([
                'school_id' => $schoolId,
                'annee_scolaire_id' => $annee->id,
                'libelle' => $tranche['libelle'],
                'pourcentage' => $tranche['pourcentage'],
                'date_echeance' => $tranche['date_echeance'],
                'ordre' => $index + 1,
            ]);
        }

        $enregistrees = TrancheScolarite::where('school_id', $schoolId)
            ->where('annee_scolaire_id', $annee->id)
            ->orderBy('ordre')
            ->get();

        return ApiResponse::success(
            $enregistrees->map(fn (TrancheScolarite $t) => $this->resumer($t))->values(),
            $enregistrees->isEmpty()
                ? 'Échéancier supprimé : la scolarité redevient exigible en une fois.'
                : $enregistrees->count().' tranche(s) enregistrée(s).',
        );
    }

    /** @return array<string, mixed> */
    private function resumer(TrancheScolarite $tranche): array
    {
        return [
            'id' => $tranche->id,
            'libelle' => $tranche->libelle,
            'pourcentage' => (float) $tranche->pourcentage,
            'date_echeance' => $tranche->date_echeance?->format('Y-m-d'),
            'ordre' => $tranche->ordre,
            'annee_scolaire_id' => $tranche->annee_scolaire_id,
            'school_id' => $tranche->school_id,
        ];
    }
}
