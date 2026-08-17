<?php

namespace App\Console\Commands;

use App\Models\Annonce;
use App\Models\Eleve;
use App\Models\Note;
use App\Models\Presence;
use App\Models\Sanction;
use App\Models\School;
use App\Services\Sms\SmsService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Rapport hebdomadaire aux parents (cahier des charges §5.5) : chaque famille
 * reçoit par SMS un résumé de la semaine de son enfant — absences, notes,
 * comportement, annonces clés. Rien à signaler cette semaine ⇒ pas de SMS :
 * un message inutile use la confiance dans les envois suivants.
 */
class EnvoyerRapportHebdomadaireParents extends Command
{
    protected $signature = 'rapport:hebdomadaire-parents';

    protected $description = "Envoie à chaque tuteur principal un résumé SMS hebdomadaire de la situation de son enfant.";

    public function __construct(private readonly SmsService $sms)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $debut = now()->subDays(7)->startOfDay();
        $fin = now()->endOfDay();
        $envoyes = 0;

        School::where('is_active', true)->get()->each(function (School $school) use ($debut, $fin, &$envoyes) {
            // Deux annonces au plus : le SMS reste un résumé, pas le bulletin complet.
            $annonces = Annonce::forSchool($school->id)
                ->whereBetween('publiee_le', [$debut, $fin])
                ->orderByDesc('publiee_le')
                ->limit(2)
                ->pluck('titre');

            Eleve::forSchool($school->id)
                ->where('statut', 'actif')
                ->with('tuteurs')
                ->chunkById(100, function (Collection $eleves) use ($debut, $fin, $annonces, &$envoyes) {
                    foreach ($eleves as $eleve) {
                        if ($this->envoyerRapport($eleve, $debut, $fin, $annonces)) {
                            $envoyes++;
                        }
                    }
                });
        });

        $this->info("{$envoyes} rapport(s) envoyé(s).");

        return self::SUCCESS;
    }

    private function envoyerRapport(Eleve $eleve, Carbon $debut, Carbon $fin, Collection $annonces): bool
    {
        $tuteur = $eleve->tuteurs->firstWhere('pivot.is_principal', true) ?? $eleve->tuteurs->first();

        if (! $tuteur?->telephone) {
            return false;
        }

        $absences = Presence::where('eleve_id', $eleve->id)
            ->where('statut', 'absent')
            ->whereHas('seance', fn ($q) => $q->whereBetween('date_seance', [$debut, $fin]))
            ->get();

        $totalAbsences = $absences->count();
        $absencesNonJustifiees = $absences->where('justifie', false)->count();

        $nombreNotes = Note::where('eleve_id', $eleve->id)
            ->whereBetween('created_at', [$debut, $fin])
            ->count();

        $nombreSanctions = Sanction::where('eleve_id', $eleve->id)
            ->whereBetween('date_sanction', [$debut, $fin])
            ->count();

        // Rien à raconter cette semaine : un SMS vide n'apporte rien et use
        // la confiance des familles dans les envois suivants.
        if ($totalAbsences === 0 && $nombreNotes === 0 && $nombreSanctions === 0 && $annonces->isEmpty()) {
            return false;
        }

        $parties = [];

        $parties[] = $totalAbsences > 0
            ? "Absences: {$totalAbsences}".($absencesNonJustifiees > 0 ? " dont {$absencesNonJustifiees} non justifiée(s)" : '')
            : 'Absences: aucune';

        if ($nombreNotes > 0) {
            $parties[] = "{$nombreNotes} nouvelle(s) note(s)";
        }

        if ($nombreSanctions > 0) {
            $parties[] = "{$nombreSanctions} sanction(s)";
        }

        if ($annonces->isNotEmpty()) {
            $parties[] = 'Annonces: '.$annonces->implode(', ');
        }

        $message = "Bilan de la semaine — {$eleve->nom_complet} : ".implode(' | ', $parties).'.';

        return $this->sms->envoyer($tuteur->telephone, $message);
    }
}
