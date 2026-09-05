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
        return DemandeAvanceSalaire::where('personnel_id', $personnelId)->with('echeances')->latest()->get();
    }

    public function enAttentePour(int $personnelId): ?DemandeAvanceSalaire
    {
        return DemandeAvanceSalaire::where('personnel_id', $personnelId)->where('statut', 'en_attente')->latest()->first();
    }

    /** @param array{montant: int, echeancier: array<int, array{mois: string, montant: int}>, motif?: ?string} $donnees */
    public function soumettre(Personnel $personnel, array $donnees): DemandeAvanceSalaire
    {
        if ($this->enAttentePour($personnel->id)) {
            throw new RuntimeException('Une demande est déjà en attente de validation.');
        }

        $montant = (int) $donnees['montant'];
        $echeancier = $donnees['echeancier'];

        // Valide déjà le plafond à la soumission : autant prévenir l'employé
        // tout de suite plutôt que de laisser l'admin rejeter une demande
        // vouée à l'échec.
        $this->avances->verifierEcheancier($personnel, $montant, $echeancier);

        $moisTries = collect($echeancier)->sortBy('mois')->values();
        $nombreMois = $moisTries->count();
        $mensualite = (int) round($montant / $nombreMois);
        $moisDebut = $moisTries->first()['mois'];

        $demande = $this->transaction(function () use ($personnel, $montant, $mensualite, $nombreMois, $moisDebut, $moisTries, $donnees) {
            $demande = DemandeAvanceSalaire::create([
                'school_id' => $personnel->school_id,
                'personnel_id' => $personnel->id,
                'montant' => $montant,
                'mensualite' => $mensualite,
                'nombre_mois' => $nombreMois,
                'mois_debut_remboursement' => $moisDebut,
                'motif' => $donnees['motif'] ?? null,
                'statut' => 'en_attente',
            ]);

            $demande->echeances()->createMany(
                $moisTries->map(fn (array $ligne) => [
                    'mois' => Carbon::parse($ligne['mois'])->startOfMonth()->toDateString(),
                    'montant_prevu' => (int) $ligne['montant'],
                ])->all(),
            );

            return $demande->fresh(['echeances']);
        });

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
            $echeancier = $demande->echeances->isNotEmpty()
                ? $demande->echeances->map(fn ($e) => ['mois' => $e->mois->toDateString(), 'montant' => $e->montant_prevu])->all()
                : $this->avances->genererEcheancierUniforme(
                    $demande->montant,
                    $demande->nombre_mois,
                    $demande->mois_debut_remboursement?->toDateString() ?? now()->startOfMonth()->toDateString(),
                );

            $avance = $this->avances->accorder($demande->school_id, [
                'personnel_id' => $demande->personnel_id,
                'montant' => $demande->montant,
                'echeancier' => $echeancier,
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
