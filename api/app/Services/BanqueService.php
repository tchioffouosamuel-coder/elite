<?php

namespace App\Services;

use App\Models\Banque;
use App\Models\BanqueMouvement;
use App\Models\BulletinPaie;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BanqueService
{
    public function deposer(
        int $banqueId,
        int $montant,
        ?string $date,
        string $libelle,
        ?string $reference,
        ?int $auteurId,
        ?string $cleIdempotence = null,
    ): BanqueMouvement {
        return $this->enregistrer(
            $banqueId,
            'depot',
            $montant,
            $date,
            $libelle,
            $reference,
            $auteurId,
            null,
            $cleIdempotence,
        );
    }

    public function retirer(
        int $banqueId,
        int $montant,
        ?string $date,
        string $libelle,
        ?string $reference,
        ?int $auteurId,
        ?string $cleIdempotence = null,
    ): BanqueMouvement {
        return $this->enregistrer(
            $banqueId,
            'retrait',
            $montant,
            $date,
            $libelle,
            $reference,
            $auteurId,
            null,
            $cleIdempotence,
        );
    }

    /**
     * Corrige un dépôt manuel (montant, date, libellé, référence). Le solde
     * est réajusté de l'écart de montant seulement — les mouvements survenus
     * depuis restent intacts. Une baisse qui ferait passer le solde sous zéro
     * est refusée : l'argent a déjà été retiré ou versé en salaires.
     * Seuls les dépôts se modifient : un retrait ou un salaire payé se
     * corrige par un mouvement inverse, pas par réécriture.
     */
    public function modifierDepot(
        int $banqueId,
        int $mouvementId,
        int $montant,
        ?string $date,
        string $libelle,
        ?string $reference,
    ): BanqueMouvement {
        return DB::transaction(function () use ($banqueId, $mouvementId, $montant, $date, $libelle, $reference) {
            $banque = Banque::query()->lockForUpdate()->findOrFail($banqueId);
            $mouvement = $banque->mouvements()->lockForUpdate()->findOrFail($mouvementId);

            if ($mouvement->type !== 'depot') {
                throw ValidationException::withMessages([
                    'mouvement' => 'Seuls les dépôts peuvent être modifiés.',
                ]);
            }

            $ecart = $montant - $mouvement->montant;

            if ($banque->solde + $ecart < 0) {
                throw ValidationException::withMessages([
                    'montant' => "Montant trop faible : le solde de {$banque->nom} deviendrait négatif ({$banque->solde} FCFA disponible(s), dépôt initial de {$mouvement->montant} FCFA).",
                ]);
            }

            $banque->solde += $ecart;
            $banque->save();

            $mouvement->update([
                'montant' => $montant,
                'date_mouvement' => $date ?? $mouvement->date_mouvement,
                'libelle' => $libelle,
                'reference' => $reference,
            ]);

            return $mouvement;
        });
    }

    /** Débite la banque domiciliataire quand le salaire part par le circuit bancaire. */
    public function payerBulletin(BulletinPaie $bulletin): ?BanqueMouvement
    {
        if (! in_array($bulletin->mode_paiement, ['virement', 'cheque', 'depot_bancaire'], true)) {
            return null;
        }
        if ($bulletin->net_a_payer <= 0) {
            return null;
        }

        $personnel = $bulletin->personnel()->first();
        if (! $personnel?->banque_id) {
            return null;
        }

        return $this->enregistrer(
            $personnel->banque_id,
            'paie',
            (int) $bulletin->net_a_payer,
            $bulletin->date_paiement?->toDateString(),
            "Salaire {$bulletin->numero} — {$personnel->nom_complet}",
            $bulletin->numero,
            null,
            $bulletin->id,
            'paie:' . $bulletin->id,
        );
    }

    private function enregistrer(
        int $banqueId,
        string $type,
        int $montant,
        ?string $date,
        string $libelle,
        ?string $reference,
        ?int $auteurId,
        ?int $bulletinId = null,
        ?string $cleIdempotence = null,
    ): BanqueMouvement {
        return DB::transaction(function () use ($banqueId, $type, $montant, $date, $libelle, $reference, $auteurId, $bulletinId, $cleIdempotence) {
            if ($cleIdempotence !== null) {
                $existante = BanqueMouvement::where('cle_idempotence', $cleIdempotence)->first();
                if ($existante) {
                    if ($existante->banque_id !== $banqueId || $existante->type !== $type || $existante->montant !== $montant) {
                        throw ValidationException::withMessages([
                            'cle_idempotence' => 'Cette clé a déjà servi pour un mouvement bancaire différent.',
                        ]);
                    }

                    return $existante;
                }
            }

            $banque = Banque::query()->lockForUpdate()->findOrFail($banqueId);
            $debit = $type !== 'depot';

            if ($debit && $banque->solde < $montant) {
                throw ValidationException::withMessages([
                    'montant' => "Solde insuffisant dans {$banque->nom} : {$banque->solde} FCFA disponible(s).",
                ]);
            }

            $banque->solde += $debit ? -$montant : $montant;
            $banque->save();

            return $banque->mouvements()->create([
                'type' => $type,
                'montant' => $montant,
                'date_mouvement' => $date ?? Carbon::today()->toDateString(),
                'libelle' => $libelle,
                'reference' => $reference,
                'cle_idempotence' => $cleIdempotence,
                'bulletin_paie_id' => $bulletinId,
                'effectue_par' => $auteurId,
            ]);
        });
    }
}
