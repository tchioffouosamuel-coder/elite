<?php

namespace App\Support\Pdf;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Réponse d'un document PDF affiché dans l'application (reçus de caisse).
 *
 * Porte un ETag et exige une revalidation à chaque ouverture : rouvrir le
 * même reçu ne retransfère pas les 45 Ko du ticket, le navigateur réutilise
 * sa copie sur un 304. La revalidation reste obligatoire (`max-age=0,
 * must-revalidate`) pour deux raisons : un reçu annulé doit cesser d'être
 * servi tel quel, et le poste du guichet est partagé — chaque ouverture doit
 * repasser par le contrôle de privilèges du serveur, jamais être servie
 * depuis le cache du navigateur sans requête.
 */
class ReponsePdf
{
    public static function enLigne(Request $request, string $pdf, string $nomFichier): Response
    {
        $reponse = response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$nomFichier.'"',
        ]);

        $reponse->setEtag(hash('xxh128', $pdf));
        $reponse->setPrivate();
        $reponse->setMaxAge(0);
        $reponse->headers->addCacheControlDirective('must-revalidate');
        // Renvoie un 304 sans corps si le navigateur a déjà cette version.
        $reponse->isNotModified($request);

        return $reponse;
    }
}
