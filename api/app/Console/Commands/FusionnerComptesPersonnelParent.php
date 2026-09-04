<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Services\FusionComptesPersonnelParentService;
use Illuminate\Console\Command;

/**
 * Fusionne les doublons personnel/parent — cf. {@see FusionComptesPersonnelParentService},
 * seul détenteur de la logique (aussi utilisée par l'écran Personnel).
 *
 * Mode aperçu par défaut (aucune écriture) — `--appliquer` pour exécuter.
 */
class FusionnerComptesPersonnelParent extends Command
{
    protected $signature = 'comptes:fusionner-personnel-parent {--appliquer : Applique réellement la fusion, sinon simple aperçu}';

    protected $description = "Fusionne les comptes personnel et parent d'une même personne, repérés par téléphone commun.";

    public function handle(FusionComptesPersonnelParentService $service): int
    {
        $appliquer = (bool) $this->option('appliquer');
        $schoolIds = School::pluck('id')->all();

        $paires = $service->apercu($schoolIds);

        if ($paires === []) {
            $this->info('Aucun doublon personnel/parent détecté.');

            return self::SUCCESS;
        }

        $this->info(count($paires).' doublon(s) détecté(s)'.($appliquer ? ', fusion en cours…' : ' (aperçu — relancez avec --appliquer pour fusionner) :'));

        foreach ($paires as $paire) {
            $this->line("- {$paire['personnel']} : compte personnel <- compte parent (fiche tuteur #{$paire['tuteur_id']})");
        }

        if (! $appliquer) {
            $this->info('Aperçu terminé — aucune écriture effectuée.');

            return self::SUCCESS;
        }

        $resultat = $service->fusionner($schoolIds);

        $this->info("{$resultat['fusionnes']} compte(s) fusionné(s).");

        return self::SUCCESS;
    }
}
