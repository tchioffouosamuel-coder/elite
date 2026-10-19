<?php

namespace App\Support\Pdf;

use App\Console\Commands\PurgerCacheRendus;
use Closure;
use Illuminate\Support\Facades\File;

/**
 * Cache disque des rendus coûteux (PDF composés par mPDF, images de codes QR).
 *
 * Composer un reçu demande à mPDF deux à trois cents millisecondes, dont
 * l'essentiel part dans le rendu des blocs `position:absolute` du ticket —
 * temps payé à l'identique chaque fois qu'un caissier réimprime le même reçu.
 * Or le rendu est une fonction pure de son HTML : à empreinte égale, l'octet
 * produit est le même. On garde donc le résultat sur disque, et la clé est
 * l'empreinte du HTML lui-même plutôt qu'un identifiant de reçu — un reçu
 * dont l'historique a changé (nouveau versement sur le dossier, annulation)
 * produit un HTML différent, donc une autre entrée, sans invalidation à
 * gérer ni risque de servir une version périmée au guichet.
 *
 * Le cache applicatif (`CACHE_STORE=database`) ne convient pas : il
 * stockerait des blobs de 45 Ko par reçu dans MySQL, à relire par le réseau.
 *
 * Les entrées sont purgées par {@see PurgerCacheRendus}.
 */
class CacheRendus
{
    /** Sous-dossier de `storage/app` où vivent les rendus. */
    private const DOSSIER = 'cache-rendus';

    /**
     * Rendu mémorisé pour cette empreinte, composé par `$produire` au premier
     * appel. Toute panne d'écriture (disque plein, droits) est ignorée : le
     * cache est une optimisation, jamais une condition du service.
     *
     * @param  Closure(): string  $produire
     */
    public static function memoriser(string $empreinte, Closure $produire): string
    {
        $chemin = self::chemin($empreinte);

        if (is_file($chemin) && ($contenu = @file_get_contents($chemin)) !== false && $contenu !== '') {
            // Date de dernier accès : la purge garde les reçus qu'on
            // réimprime encore et laisse partir les autres.
            @touch($chemin);

            return $contenu;
        }

        $contenu = $produire();
        self::ecrire($chemin, $contenu);

        return $contenu;
    }

    /** Chemin absolu du dossier de cache, créé à la volée. */
    public static function dossier(): string
    {
        return storage_path('app/'.self::DOSSIER);
    }

    /**
     * Écriture atomique : un fichier temporaire du même dossier renommé par
     * dessus. Deux guichets qui composent le même reçu en même temps ne
     * peuvent donc pas se faire lire un fichier à moitié écrit.
     */
    private static function ecrire(string $chemin, string $contenu): void
    {
        try {
            File::ensureDirectoryExists(dirname($chemin));
            $temporaire = $chemin.'.'.bin2hex(random_bytes(6)).'.tmp';

            if (@file_put_contents($temporaire, $contenu, LOCK_EX) === false) {
                return;
            }

            if (! @rename($temporaire, $chemin)) {
                @unlink($temporaire);
            }
        } catch (\Throwable) {
            // Cache indisponible : on a déjà le rendu en main, il suffit.
        }
    }

    /** Deux niveaux de sous-dossiers : quelques milliers de reçus par dossier, pas des centaines de milliers. */
    private static function chemin(string $empreinte): string
    {
        $cle = hash('xxh128', $empreinte);

        return self::dossier().'/'.substr($cle, 0, 2).'/'.substr($cle, 2, 2).'/'.$cle;
    }
}
