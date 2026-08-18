<?php

namespace App\Observers;

use App\Models\SyncTombstone;
use App\Support\Sync\RegistreSync;
use Illuminate\Database\Eloquent\Model;

/**
 * Enregistre la disparition d'une ligne répliquée sur mobile.
 *
 * Limite connue et assumée : une suppression en cascade au niveau SQL
 * (`cascadeOnDelete`) ne passe pas par Eloquent et ne déclenche donc pas cet
 * observer. Supprimer un élève efface ses notes et ses présences sans qu'aucune
 * pierre tombale ne soit posée pour elles.
 *
 * Ce n'est pas un trou : le client reçoit la pierre tombale du parent
 * (`eleves/4573`) et applique la même cascade dans sa base locale — les clés
 * étrangères y sont déclarées à l'identique. Poser une pierre tombale par
 * ligne fille alourdirait la charge utile sans rien apprendre de plus au
 * client.
 */
class TombstoneObserver
{
    public function deleted(Model $modele): void
    {
        $entite = RegistreSync::cleDuModele($modele);

        if ($entite === null) {
            return;
        }

        SyncTombstone::create([
            'entite' => $entite,
            'entite_id' => $modele->getKey(),
            'school_id' => RegistreSync::ecoleDe($entite, $modele),
            'supprime_le' => now(),
        ]);
    }
}
