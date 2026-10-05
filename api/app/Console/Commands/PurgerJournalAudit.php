<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;

/**
 * Supprime les lignes du journal d'audit plus anciennes que la durée de
 * conservation (`audit.retention_jours`), par lots pour ne pas verrouiller
 * la table pendant une longue suppression.
 */
class PurgerJournalAudit extends Command
{
    protected $signature = 'audit:purger {--jours= : Durée de conservation, en jours (défaut : config audit.retention_jours)}';

    protected $description = "Purge les lignes du journal d'audit au-delà de la durée de conservation";

    public function handle(): int
    {
        $jours = (int) ($this->option('jours') ?? config('audit.retention_jours'));

        if ($jours <= 0) {
            $this->info('Conservation illimitée : rien à purger.');

            return self::SUCCESS;
        }

        $limite = now()->subDays($jours);
        $total = 0;

        do {
            $supprimees = AuditLog::where('created_at', '<', $limite)->limit(5000)->delete();
            $total += $supprimees;
        } while ($supprimees > 0);

        $this->info("{$total} ligne(s) d'audit antérieure(s) au {$limite->format('d/m/Y')} supprimée(s).");

        return self::SUCCESS;
    }
}
