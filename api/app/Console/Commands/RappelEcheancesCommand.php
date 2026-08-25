<?php

namespace App\Console\Commands;

use App\Models\AnneeScolaire;
use App\Models\DossierScolarite;
use App\Models\NotificationInterne;
use App\Models\School;
use App\Services\EcheancierService;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Rappelle chaque jour au personnel finance les échéances de scolarité qui
 * tombent bientôt et ne sont pas encore réglées — avant qu'elles ne basculent
 * en retard plutôt qu'après, pour laisser le temps de relancer les familles.
 */
class RappelEcheancesCommand extends Command
{
    /** Fenêtre de rappel, en jours avant l'échéance. */
    private const FENETRE_JOURS = 3;

    protected $signature = 'echeances:rappel';

    protected $description = "Notifie le personnel finance des échéances de scolarité proches et impayées.";

    public function __construct(private readonly EcheancierService $echeancier, private readonly NotificationService $notifications)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $aujourdHui = Carbon::today();
        $limite = $aujourdHui->clone()->addDays(self::FENETRE_JOURS);
        $notifies = 0;

        School::where('is_active', true)->get()->each(function (School $school) use ($aujourdHui, $limite, &$notifies) {
            $annee = AnneeScolaire::where('school_id', $school->id)->where('is_active', true)->first();

            if (! $annee) {
                return;
            }

            $this->echeancier->tranches($school->id, $annee->id)
                ->filter(fn ($tranche) => $tranche->date_echeance && $tranche->date_echeance->between($aujourdHui, $limite))
                ->each(function ($tranche) use ($school, $annee, &$notifies) {
                    if ($this->dejaNotifieAujourdHui($school->id, $tranche->id)) {
                        return;
                    }

                    [$dossiersConcernes, $montantDu] = $this->dossiersEnAttente($school->id, $annee->id, $tranche->id);

                    if ($dossiersConcernes === 0) {
                        return;
                    }

                    $this->notifications->notifierParPermission(
                        $school->id,
                        'finance.view',
                        'echeance_proche',
                        "Échéance proche — {$tranche->libelle}",
                        "{$dossiersConcernes} dossier(s) ont une échéance le {$tranche->date_echeance->format('d/m/Y')}, soit ".number_format($montantDu, 0, ',', ' ')." FCFA à recouvrer.",
                        "tranche:{$tranche->id}",
                    );

                    $notifies++;
                });
        });

        $this->info("{$notifies} rappel(s) d'échéance envoyé(s).");

        return self::SUCCESS;
    }

    /**
     * @return array{0: int, 1: int} nombre de dossiers concernés et montant total dû sur cette tranche
     */
    private function dossiersEnAttente(int $schoolId, int $anneeScolaireId, int $trancheId): array
    {
        $dossiers = 0;
        $montant = 0;

        DossierScolarite::where('school_id', $schoolId)
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->avecTotaux()
            ->chunkById(200, function ($lot) use (&$dossiers, &$montant, $trancheId) {
                foreach ($lot as $dossier) {
                    $ligne = collect($this->echeancier->pourDossier($dossier)['tranches'])
                        ->firstWhere('id', $trancheId);

                    if ($ligne && $ligne['statut'] === EcheancierService::STATUT_A_VENIR) {
                        $dossiers++;
                        $montant += $ligne['reste'];
                    }
                }
            });

        return [$dossiers, $montant];
    }

    private function dejaNotifieAujourdHui(int $schoolId, int $trancheId): bool
    {
        return NotificationInterne::where('school_id', $schoolId)
            ->where('type', 'echeance_proche')
            ->whereDate('created_at', Carbon::today())
            ->where('lien', "tranche:{$trancheId}")
            ->exists();
    }
}
