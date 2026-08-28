<?php

namespace App\Services;

use App\Models\DemandeAvanceSalaire;
use App\Models\Personnel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Demandes d'avance sur salaire soumises par un employé lui-même — même
 * logique de proposition/validation que {@see ModificationEleveService},
 * appliquée au personnel : rien n'atteint `avances_salaire` avant que
 * {@see valider()} ne crée réellement l'avance via AvanceSalaireService.
 */
class DemandeAvanceSalaireService extends BaseService
{
    public function __construct(
        private readonly AvanceSalaireService $avances,
        private readonly NotificationService $notifications,
    ) {}

    /** @return Collection<int, DemandeAvanceSalaire> */
    public function pourPersonnel(int $personnelId): Collection
    {
        return DemandeAvanceSalaire::where('personnel_id', $personnelId)->latest()->get();
    }

    public function enAttentePour(int $personnelId): ?DemandeAvanceSalaire
    {
        return DemandeAvanceSalaire::where('personnel_id', $personnelId)->where('statut', 'en_attente')->latest()->first();
    }

    /** @param array{montant: int, mensualite: int, mois_debut_remboursement?: ?string, motif?: ?string} $donnees */
    public function soumettre(Personnel $personnel, array $donnees): DemandeAvanceSalaire
    {
        if ($this->enAttentePour($personnel->id)) {
            throw new RuntimeException('Une demande est déjà en attente de validation.');
        }

        $montant = (int) $donnees['montant'];
        $mensualite = (int) $donnees['mensualite'];
        $moisDebut = $donnees['mois_debut_remboursement'] ?? now()->startOfMonth()->toDateString();

        // Valide déjà le plafond à la soumission : autant prévenir l'employé
        // tout de suite plutôt que de laisser l'admin rejeter une demande
        // vouée à l'échec.
        $this->avances->verifierPlafond($personnel, $montant, $mensualite);

        $demande = DemandeAvanceSalaire::create([
            'school_id' => $personnel->school_id,
            'personnel_id' => $personnel->id,
            'montant' => $montant,
            'mensualite' => $mensualite,
            'nombre_mois' => $this->avances->calculerNombreMois($montant, $mensualite),
            'mois_debut_remboursement' => $moisDebut,
            'motif' => $donnees['motif'] ?? null,
            'statut' => 'en_attente',
        ]);

        $this->notifications->notifierParPermission(
            $personnel->school_id,
            'finance.paie',
            'demande_avance',
            'Demande d\'avance sur salaire',
            "{$personnel->nom_complet} demande une avance de {$montant} F CFA, remboursable à {$mensualite} F CFA/mois à partir de ".Carbon::parse($moisDebut)->translatedFormat('F Y').'.',
        );

        return $demande;
    }

    public function valider(DemandeAvanceSalaire $demande, ?int $adminUserId = null): DemandeAvanceSalaire
    {
        if ($demande->statut !== 'en_attente') {
            throw new RuntimeException('Cette demande a déjà été traitée.');
        }

        return $this->transaction(function () use ($demande, $adminUserId) {
            $avance = $this->avances->accorder($demande->school_id, [
                'personnel_id' => $demande->personnel_id,
                'montant' => $demande->montant,
                'mensualite' => $demande->mensualite,
                'mois_debut_remboursement' => $demande->mois_debut_remboursement?->toDateString(),
                'date_avance' => now()->toDateString(),
                'motif' => $demande->motif,
            ], $adminUserId);

            $demande->update([
                'statut' => 'validee',
                'avance_salaire_id' => $avance->id,
                'traite_par' => $adminUserId,
                'traite_le' => now(),
            ]);

            return $demande->fresh();
        });
    }

    public function rejeter(DemandeAvanceSalaire $demande, string $motif, ?int $adminUserId = null): DemandeAvanceSalaire
    {
        if ($demande->statut !== 'en_attente') {
            throw new RuntimeException('Cette demande a déjà été traitée.');
        }

        $demande->update([
            'statut' => 'rejetee',
            'motif_rejet' => $motif,
            'traite_par' => $adminUserId,
            'traite_le' => now(),
        ]);

        return $demande->fresh();
    }
}
