<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Services\AbsenceNonEnregistreeService;
use Illuminate\Console\Command;

/**
 * Chaque jour, repère les élèves sans le moindre pointage depuis
 * {@see AbsenceNonEnregistreeService::SEUIL_JOURS} jours de cours consécutifs
 * — ni présent, ni marqué absent — et déclenche l'alerte administration +
 * famille + blocage de l'accès parent.
 */
class AlerteAbsenceNonEnregistreeCommand extends Command
{
    protected $signature = 'absences:alerter-non-enregistrees';

    protected $description = "Alerte et bloque l'accès parent d'un élève sans aucun pointage depuis plusieurs jours de cours consécutifs.";

    public function __construct(private readonly AbsenceNonEnregistreeService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $total = 0;

        School::where('is_active', true)->get()->each(function (School $school) use (&$total) {
            $total += $this->service->detecterEtAlerter($school);
        });

        $this->info("{$total} élève(s) signalé(s) pour absence sans pointage.");

        return self::SUCCESS;
    }
}
