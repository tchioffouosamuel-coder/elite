<?php

namespace App\Console\Commands;

use App\Support\Pdf\CacheRendus;
use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;

/**
 * Supprime du cache de rendus (cf. {@see CacheRendus}) les entrées qui n'ont
 * plus été lues depuis la durée de conservation.
 *
 * Chaque versement enregistré sur un dossier change l'historique imprimé sur
 * les reçus précédents de ce dossier, donc leur empreinte : les anciennes
 * entrées deviennent inatteignables et s'accumuleraient sans cette purge.
 */
class PurgerCacheRendus extends Command
{
    protected $signature = 'pdf:purger-cache
        {--jours= : Conservation depuis le dernier accès, en jours (défaut : config recu.cache_retention_jours)}
        {--tout : Vide tout le cache, sans regarder les dates}';

    protected $description = 'Purge les rendus PDF mis en cache et non relus depuis la durée de conservation';

    public function handle(): int
    {
        $jours = (int) ($this->option('jours') ?? config('recu.cache_retention_jours'));
        $tout = (bool) $this->option('tout');
        $dossier = CacheRendus::dossier();

        if ((! $tout && $jours <= 0) || ! is_dir($dossier)) {
            $this->info('Rien à purger.');

            return self::SUCCESS;
        }

        // `--tout` sert à écarter le cache d'un diagnostic : si un ticket
        // sort mal imprimé, on le vide et on réimprime pour savoir si le
        // défaut vient du rendu mémorisé ou de la composition elle-même.
        $fichiers = Finder::create()->files()->in($dossier);
        if (! $tout) {
            $limite = now()->subDays($jours);
            $fichiers = $fichiers->date('< '.$limite->toDateTimeString());
        }

        $supprimes = 0;
        $octets = 0;

        foreach ($fichiers as $fichier) {
            $taille = $fichier->getSize();
            if (@unlink($fichier->getPathname())) {
                $supprimes++;
                $octets += $taille;
            }
        }

        $this->info(sprintf('%d rendu(s) purgé(s), %.1f Mo libérés.', $supprimes, $octets / 1048576));

        return self::SUCCESS;
    }
}
