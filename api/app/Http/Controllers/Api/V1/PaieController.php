<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\BulletinPaie;
use App\Models\Personnel;
use App\Models\School;
use App\Services\PaieService;
use App\Support\Pdf\BulletinPaieGenerator;
use App\Support\Pdf\EtatEmargementGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class PaieController extends Controller
{
    public function __construct(private readonly PaieService $service) {}

    /** Masse salariale du mois et bulletins associés. */
    public function index(Request $request): JsonResponse
    {
        [$annee, $mois] = $this->periode($request);

        $masse = $this->service->masseSalariale(app('tenant.school_id'), $annee, $mois);

        return ApiResponse::success([
            'periode' => ['annee' => $annee, 'mois' => $mois],
            'totaux' => $masse['totaux'],
            'bulletins' => $masse['bulletins']->map(fn (BulletinPaie $b) => $this->resumer($b))->values(),
        ]);
    }

    /** Prépare la paie de tout le personnel en poste. */
    public function preparerLot(Request $request): JsonResponse
    {
        [$annee, $mois] = $this->periode($request);

        $saisie = $request->validate([
            'jours_ouvrables' => ['nullable', 'integer', 'min:1', 'max:31'],
            'jours_travailles' => ['nullable', 'integer', 'min:0', 'max:31'],
        ]);

        $lot = $this->service->preparerLot(app('tenant.school_id'), $annee, $mois, $saisie);

        return ApiResponse::success([
            'prepares' => $lot['bulletins']->count(),
            'ignores' => $lot['ignores'],
        ], $lot['bulletins']->count().' bulletin(s) préparé(s).');
    }

    /** Prépare — ou recalcule — le bulletin d'un agent. */
    public function preparer(Request $request, int $personnelId): JsonResponse
    {
        $personnel = Personnel::forSchool(app('tenant.school_id'))->findOrFail($personnelId);
        [$annee, $mois] = $this->periode($request);

        $saisie = $request->validate([
            'jours_ouvrables' => ['nullable', 'integer', 'min:1', 'max:31'],
            'jours_travailles' => ['nullable', 'integer', 'min:0', 'max:31'],
            'deduction_raff' => ['nullable', 'integer', 'min:0'],
            'deduction_njangi' => ['nullable', 'integer', 'min:0'],
            'deduction_pret' => ['nullable', 'integer', 'min:0'],
            'deduction_autre' => ['nullable', 'integer', 'min:0'],
        ]);

        return $this->executer(fn () => ApiResponse::success(
            $this->resumer($this->service->preparer($personnel, $annee, $mois, $saisie)),
            'Bulletin préparé.',
        ));
    }

    public function arreter(Request $request, int $id): JsonResponse
    {
        $bulletin = $this->bulletin($id);

        return $this->executer(fn () => ApiResponse::success(
            $this->resumer($this->service->arreter($bulletin, $request->user()?->id)),
            "Bulletin {$bulletin->numero} arrêté.",
        ));
    }

    public function payer(Request $request, int $id): JsonResponse
    {
        $bulletin = $this->bulletin($id);

        $donnees = $request->validate([
            'mode' => ['required', 'in:especes,mobile_money,virement,cheque,depot_bancaire'],
            'date_paiement' => ['nullable', 'date'],
        ]);

        return $this->executer(fn () => ApiResponse::success(
            $this->resumer($this->service->payer($bulletin, $donnees['mode'], $donnees['date_paiement'] ?? null)),
            'Règlement enregistré.',
        ));
    }

    /** L'agent atteste avoir perçu son salaire. */
    public function emarger(Request $request, int $id): JsonResponse
    {
        $bulletin = $this->bulletin($id);

        $donnees = $request->validate(['reference' => ['nullable', 'string', 'max:150']]);

        return $this->executer(fn () => ApiResponse::success(
            $this->resumer($this->service->emarger($bulletin, $donnees['reference'] ?? null)),
            'Émargement enregistré.',
        ));
    }

    public function bulletinPdf(int $id): Response
    {
        $bulletin = $this->bulletin($id);

        return $this->pdf(
            (new BulletinPaieGenerator)->build($bulletin),
            'bulletin-paie-'.$bulletin->numero.'.pdf',
        );
    }

    /** État d'émargement du mois : la pièce que signent les agents. */
    public function etatEmargement(Request $request): Response
    {
        [$annee, $mois] = $this->periode($request);
        $schoolId = app('tenant.school_id');

        $bulletins = BulletinPaie::forSchool($schoolId)
            ->where('annee', $annee)->where('mois', $mois)
            ->arretes()
            ->with('personnel.fonctionReference')
            ->get()
            ->sortBy(fn (BulletinPaie $b) => $b->personnel->nom_complet)
            ->values();

        $periode = $bulletins->first()?->periode_libelle ?? "{$mois}/{$annee}";

        return $this->pdf(
            (new EtatEmargementGenerator)->build(School::findOrFail($schoolId), $bulletins, $periode),
            "etat-emargement-{$annee}-{$mois}.pdf",
        );
    }

    /** @return array<string, mixed> */
    private function resumer(BulletinPaie $bulletin): array
    {
        $bulletin->loadMissing('personnel');

        return [
            'id' => $bulletin->id,
            'numero' => $bulletin->numero,
            'personnel' => [
                'id' => $bulletin->personnel?->id,
                'nom_complet' => $bulletin->personnel?->nom_complet,
                'matricule' => $bulletin->personnel?->matricule,
                'fonction' => $bulletin->personnel?->fonction,
            ],
            'periode' => $bulletin->periode_libelle,
            'jours_ouvrables' => $bulletin->jours_ouvrables,
            'jours_travailles' => $bulletin->jours_travailles,
            'salaire_brut' => $bulletin->salaire_brut,
            'net_taxable' => $bulletin->net_taxable,
            'charges_salariales' => $bulletin->charges_salariales,
            'charges_patronales' => $bulletin->charges_patronales,
            'total_deductions' => $bulletin->total_deductions,
            'net_a_payer' => $bulletin->net_a_payer,
            'cout_employeur' => $bulletin->cout_employeur,
            'statut' => $bulletin->statut,
            'mode_paiement' => $bulletin->mode_paiement,
            'date_paiement' => $bulletin->date_paiement?->format('Y-m-d'),
            'emarge' => $bulletin->emarge_le !== null,
        ];
    }

    /** Les refus métier du service sont des 422, pas des erreurs serveur. */
    private function executer(callable $action): JsonResponse
    {
        try {
            return $action();
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    private function bulletin(int $id): BulletinPaie
    {
        return BulletinPaie::forSchool(app('tenant.school_id'))->with('lignes')->findOrFail($id);
    }

    private function pdf(string $contenu, string $nomFichier): Response
    {
        return response($contenu, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$nomFichier.'"',
        ]);
    }

    /** @return array{0: int, 1: int} */
    private function periode(Request $request): array
    {
        $request->validate([
            'annee' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'mois' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        return [
            $request->integer('annee') ?: (int) now()->year,
            $request->integer('mois') ?: (int) now()->month,
        ];
    }
}
