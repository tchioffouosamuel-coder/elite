<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AvanceSalaire;
use App\Models\DemandeAvanceSalaire;
use App\Models\Personnel;
use App\Services\AvanceSalaireService;
use App\Services\DemandeAvanceSalaireService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
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
            'nombre_mois' => ['required', 'integer', 'min:1', 'max:36'],
            'motif' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $demande = $this->demandes->soumettre($personnel, $data);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::created($demande, "Demande transmise, en attente de validation par l'établissement.");
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
