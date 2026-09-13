<?php

namespace App\Console\Commands;

use App\Models\Moratoire;
use App\Services\Push\PushService;
use App\Services\Sms\SmsService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Prévient les parents le jour où le moratoire accordé à leur enfant expire —
 * par push si le parent a un appareil enregistré, par SMS sinon, pour ne pas
 * laisser la famille découvrir le retard autrement.
 */
class AlerteMoratoireExpireCommand extends Command
{
    protected $signature = 'moratoires:alerte-expiration';

    protected $description = "Notifie les parents dont le moratoire accordé à leur enfant expire aujourd'hui.";

    public function __construct(
        private readonly PushService $push,
        private readonly SmsService $sms,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $moratoires = Moratoire::whereDate('date_expiration', Carbon::today())
            ->whereNull('notifie_expiration_le')
            ->with('eleve.tuteurs')
            ->get();

        $notifies = 0;

        foreach ($moratoires as $moratoire) {
            $eleve = $moratoire->eleve;

            if (! $eleve) {
                continue;
            }

            $tuteur = $eleve->tuteurs->firstWhere('pivot.is_principal', true) ?? $eleve->tuteurs->first();

            if ($tuteur) {
                $titre = 'Moratoire expiré';
                $message = "Le moratoire accordé pour {$eleve->nom_complet} a expiré aujourd'hui. Merci de régulariser la situation de scolarité.";

                $pousse = $tuteur->user_id
                    && $this->push->notifier([$tuteur->user_id], $titre, $message, ['type' => 'moratoire_expire', 'eleve_id' => $eleve->id]) > 0;

                if (! $pousse && $tuteur->telephone) {
                    $this->sms->envoyer($tuteur->telephone, "{$titre} — {$message}");
                }
            }

            $moratoire->update(['notifie_expiration_le' => Carbon::today()]);
            $notifies++;
        }

        $this->info("{$notifies} alerte(s) de moratoire expiré envoyée(s).");

        return self::SUCCESS;
    }
}
