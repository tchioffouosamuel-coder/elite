<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AvanceSalaire;
use App\Models\BudgetPersonnel;
use App\Models\DemandeAvanceSalaire;
use App\Models\Personnel;
use App\Services\AvanceSalaireService;
use App\Services\BudgetPersonnelService;
use App\Services\DemandeAvanceSalaireService;
use App\Support\Pdf\BudgetPersonnelBilanGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Espace personnel : ce qu'un employé peut faire lui-même sur ses propres
 * avances sur salaire — consulter, demander. Contrairement au portail
 * parent, aucun rôle dédié ne porte ce périmètre (le personnel garde son
 * rôle fonctionnel — enseignant, économe…) : l'accès se fonde uniquement
 * sur la présence d'une fiche `Personnel` liée au compte connecté.
 */
class PersonnelEspaceController extends Controller
{
    public function __construct(
        private readonly AvanceSalaireService $avances,
        private readonly DemandeAvanceSalaireService $demandes,
        private readonly BudgetPersonnelService $budgets,
    ) {}

    /** Mes avances déjà accordées, et mes demandes en cours ou passées. */
    public function mesAvances(Request $request): JsonResponse
    {
        $personnel = $this->moi($request);

        $avances = $this->avances->lister($personnel->school_id, ['personnel_id' => $personnel->id]);
        $demandes = $this->demandes->pourPersonnel($personnel->id);

        return ApiResponse::success([
            /*
             * Bornes de l'échéancier, livrées avec la liste : le formulaire de
             * demande annonce la mensualité maximale au lieu de laisser
             * l'employé découvrir le plafond par un refus.
             */
            'plafond' => $this->avances->plafond($personnel) ?? ['salaire_brut' => null, 'plafond_mensualite' => null],
            'avances' => $avances->map(fn (AvanceSalaire $a) => [
                'id' => $a->id,
                'montant' => $a->montant,
                'nombre_mois' => $a->nombre_mois,
                'mensualite' => $a->mensualite,
                'mois_debut_remboursement' => $a->mois_debut_remboursement?->format('Y-m-d'),
                'date_avance' => $a->date_avance->format('Y-m-d'),
                'motif' => $a->motif,
                'montant_rembourse' => $a->montant_rembourse,
                'solde' => $a->solde,
                'statut' => $a->statut,
            ])->values(),
            'demandes' => $demandes->map(fn (DemandeAvanceSalaire $d) => [
                'id' => $d->id,
                'montant' => $d->montant,
                'nombre_mois' => $d->nombre_mois,
                'mensualite' => $d->mensualite,
                'mois_debut_remboursement' => $d->mois_debut_remboursement?->format('Y-m-d'),
                'motif' => $d->motif,
                'statut' => $d->statut,
                'motif_rejet' => $d->motif_rejet,
                'created_at' => $d->created_at->format('Y-m-d H:i'),
            ])->values(),
        ]);
    }

    public function soumettreDemandeAvance(Request $request): JsonResponse
    {
        $personnel = $this->moi($request);

        $data = $request->validate([
            'montant' => ['required', 'integer', 'min:1'],
            'mensualite' => ['required', 'integer', 'min:1'],
            'mois_debut_remboursement' => ['nullable', 'date'],
            'motif' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $demande = $this->demandes->soumettre($personnel, $data);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::created($demande, "Demande transmise, en attente de validation par l'établissement.");
    }

    /** Mes budgets alloués, avec ce qui reste disponible sur chacun. */
    public function mesBudgets(Request $request): JsonResponse
    {
        $personnel = $this->moi($request);

        $budgets = $this->budgets->lister($personnel->school_id, $personnel->id);

        return ApiResponse::success([
            'budgets' => $budgets->map(fn (BudgetPersonnel $b) => [
                'id' => $b->id,
                'libelle' => $b->libelle,
                'montant_alloue' => $b->montant_alloue,
                'montant_depense' => $b->montant_depense,
                'solde' => $b->solde,
                'statut' => $b->statut,
                'date_allocation' => $b->date_allocation?->format('Y-m-d'),
                'note_gestion' => $b->note_gestion,
            ])->values(),
        ]);
    }

    /**
     * L'intéressé précise ici comment il compte gérer son enveloppe — un
     * budget qui n'est pas le sien reste invisible : `mesBudgets()` ne remonte
     * déjà que les siens, ici on revérifie avant d'écrire quoi que ce soit.
     */
    public function modifierNoteGestionBudget(Request $request, int $id): JsonResponse
    {
        $budget = $this->monBudget($request, $id);

        $donnees = $request->validate(['note_gestion' => ['required', 'string', 'max:2000']]);

        $budget = $this->budgets->modifierNoteGestion($budget, $donnees['note_gestion']);

        return ApiResponse::success(['note_gestion' => $budget->note_gestion], 'Note de gestion mise à jour.');
    }

    public function bilanBudgetPdf(Request $request, int $id): Response
    {
        $budget = $this->monBudget($request, $id);
        $du = $request->string('du')->toString() ?: null;
        $au = $request->string('au')->toString() ?: null;

        $bilan = $this->budgets->bilan($budget, $du, $au);
        $pdf = (new BudgetPersonnelBilanGenerator)->build($budget, $bilan, $du, $au);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="bilan-budget-' . $budget->id . '.pdf"',
        ]);
    }

    private function monBudget(Request $request, int $id): BudgetPersonnel
    {
        $personnel = $this->moi($request);

        return BudgetPersonnel::where('personnel_id', $personnel->id)->with('personnel', 'school')->findOrFail($id);
    }

    private function moi(Request $request): Personnel
    {
        $personnel = $request->user()->personnel;

        if (! $personnel) {
            throw new NotFoundHttpException('Aucune fiche personnel associée à ce compte.');
        }

        return $personnel;
    }
}
