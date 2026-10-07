<?php

namespace App\Support;

use App\Models\Classe;
use App\Models\User;

/**
 * Preuve qu'un agent était bien dans la salle au moment où il déclare y
 * avoir fait cours — QR code affiché au mur, ou code court équivalent.
 *
 * Point d'application unique : « Ma journée » et l'appel d'une séance
 * exigeaient la même chose avec deux copies du même contrôle, qui pouvaient
 * diverger. Toute écriture qui marque une séance « effectuée » passe par ici.
 */
final class PreuvePresence
{
    /**
     * Exige la preuve réclamée par la règle applicable à `$classe`, et
     * renvoie si elle a été fournie.
     *
     * La règle est résolue *avec la classe* : c'est elle qui porte le
     * sous-système, donc la règle la plus spécifique — la résoudre sans elle
     * ne verrait que la règle « toute l'école » et pourrait exiger autre
     * chose que ce que la direction a défini pour cette section.
     *
     * @return bool la preuve a été fournie et validée — à figer dans `seances.qr_verifie_le`
     */
    public static function exiger(User $user, Classe $classe, ?string $qrToken, ?string $codeSalle): bool
    {
        $fournie = $classe->preuvePresenceValide($qrToken, $codeSalle);

        abort_if(
            $user->methodeValidationSeance($classe) !== 'libre' && ! $fournie,
            403,
            "Scannez le QR code de la salle, ou saisissez son code, avant de valider — c'est ce qui prouve que vous y étiez."
        );

        return $fournie;
    }
}
