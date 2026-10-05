<?php

namespace App\Support\Documents;

use App\Models\User;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Produit UN fichier du plan en appelant, en sous-requête, la route qui le
 * génère déjà — même mécanique que le rejeu de synchronisation
 * (`SyncController::rejouer()`) : mêmes contrôles d'accès, même
 * journalisation, aucun générateur dupliqué.
 */
class ExecuteurDocument
{
    private const EXTENSIONS = [
        'application/pdf' => 'pdf',
        'application/zip' => 'zip',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'text/csv' => 'csv',
    ];

    private const EXTENSIONS_FORMAT = ['pdf' => 'pdf', 'word' => 'docx', 'excel' => 'xlsx', 'zip' => 'zip'];

    /**
     * @param  array<string, mixed>  $item
     * @return array{contenu: string, extension: string}
     */
    public function executer(array $item, User $user): array
    {
        $sousRequete = Request::create('/api/v1/'.$item['chemin'], 'GET', $item['requete'], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_SCHOOL_ID' => (string) $item['school_id'],
        ]);
        $sousRequete->setUserResolver(fn () => $user);

        $requeteOrigine = app('request');

        try {
            $reponse = app()->handle($sousRequete);
        } finally {
            // La sous-requête a pris la place de la requête courante dans le
            // conteneur : on la rend, pour la suite du lot.
            app()->instance('request', $requeteOrigine);
        }

        if ($reponse->getStatusCode() >= 400) {
            $message = json_decode((string) $reponse->getContent(), true)['message'] ?? null;
            throw new RuntimeException($message ?: 'Erreur HTTP '.$reponse->getStatusCode());
        }

        $contenu = $this->contenu($reponse);

        // Une route qui répond en JSON au lieu d'un fichier (ex. « aucune
        // donnée ») ne doit pas finir dans le ZIP sous un faux nom de PDF.
        if (str_contains((string) $reponse->headers->get('Content-Type'), 'application/json')) {
            $message = json_decode($contenu, true)['message'] ?? 'Réponse sans fichier.';
            throw new RuntimeException($message);
        }

        return ['contenu' => $contenu, 'extension' => $this->extension($reponse, $item['format'])];
    }

    private function contenu(Response $reponse): string
    {
        if ($reponse instanceof BinaryFileResponse) {
            $chemin = $reponse->getFile()->getPathname();
            $contenu = (string) file_get_contents($chemin);

            // `deleteFileAfterSend()` n'agit qu'à l'envoi réel, qui n'aura pas lieu.
            $supprimer = (fn () => $this->deleteFileAfterSend)->call($reponse);
            if ($supprimer) {
                @unlink($chemin);
            }

            return $contenu;
        }

        if ($reponse instanceof StreamedResponse) {
            ob_start();
            $reponse->sendContent();

            return (string) ob_get_clean();
        }

        return (string) $reponse->getContent();
    }

    private function extension(Response $reponse, string $format): string
    {
        $disposition = (string) $reponse->headers->get('Content-Disposition');
        if (preg_match('/filename\*?=(?:UTF-8\'\')?"?([^";]+)"?/i', $disposition, $m)) {
            $extension = strtolower(pathinfo(rawurldecode($m[1]), PATHINFO_EXTENSION));
            if ($extension !== '') {
                return $extension;
            }
        }

        $type = strtolower(trim(explode(';', (string) $reponse->headers->get('Content-Type'))[0]));

        return self::EXTENSIONS[$type] ?? self::EXTENSIONS_FORMAT[$format] ?? 'bin';
    }
}
