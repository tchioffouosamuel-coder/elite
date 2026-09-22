<?php

namespace App\Services;

use App\Models\AnneeScolaire;
use App\Models\CalendrierScolaire;
use App\Models\Classe;
use App\Models\ProgressionItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CalendrierScolaireService extends BaseService
{
    public function estOuvert(Classe $classe, Carbon $date, ?int $anneeId = null): bool
    {
        $annee = $anneeId
            ? AnneeScolaire::whereKey($anneeId)->first()
            : AnneeScolaire::where('school_id', $classe->school_id)
                ->whereDate('date_debut', '<=', $date->toDateString())
                ->whereDate('date_fin', '>=', $date->toDateString())
                ->orderByDesc('is_active')->first();

        if (! $annee || $date->lt($annee->date_debut) || $date->gt($annee->date_fin)) {
            return false;
        }

        $regles = CalendrierScolaire::where('annee_scolaire_id', $annee->id)
            ->where('date', $date->toDateString())
            ->where(fn ($q) => $q->where('classe_id', $classe->id)
                ->orWhere(fn ($q) => $q->whereNull('classe_id')->where('niveau_id', $classe->niveau_id))
                ->orWhere(fn ($q) => $q->whereNull('classe_id')->whereNull('niveau_id')->where('sous_systeme_id', $classe->sous_systeme_id))
                ->orWhere(fn ($q) => $q->whereNull('classe_id')->whereNull('niveau_id')->whereNull('sous_systeme_id')))
            ->get();

        $regle = $regles->sortByDesc(fn (CalendrierScolaire $r) => $r->classe_id ? 3 : ($r->niveau_id ? 2 : ($r->sous_systeme_id ? 1 : 0)))->first();

        return $regle ? $regle->est_ouvert : true;
    }

    /** Recalcule les prochaines leçons à partir des créneaux ouverts de chaque classe. */
    public function recalculerDates(AnneeScolaire $annee, Collection $classes): int
    {
        $modifiees = 0;

        foreach ($classes as $classe) {
            $creneaux = $classe->emploiDuTemps()
                ->whereNotNull('classe_matiere_id')
                ->orderBy('jour')->orderBy('heure_debut')->get()->groupBy('jour');

            foreach ($classe->classeMatieres()->where('statut', 'actif')->get() as $classeMatiere) {
                $lecons = ProgressionItem::where('classe_matiere_id', $classeMatiere->id)
                    ->lecons()->whereNull('date_realisee')->orderBy('ordre')->orderBy('id')->get();
                $slots = $creneaux->filter(fn (Collection $liste) => $liste->contains('classe_matiere_id', $classeMatiere->id));
                if ($lecons->isEmpty() || $slots->isEmpty()) {
                    continue;
                }

                $dates = [];
                for ($date = Carbon::parse((string) $annee->date_debut); $date->lte(Carbon::parse((string) $annee->date_fin)) && count($dates) < $lecons->count(); $date->addDay()) {
                    if (! $this->estOuvert($classe, $date, $annee->id)) {
                        continue;
                    }
                    foreach ($slots->get($date->dayOfWeekIso, []) as $_slot) {
                        $dates[] = $date->toDateString();
                        if (count($dates) >= $lecons->count()) {
                            break 2;
                        }
                    }
                }

                foreach ($lecons as $index => $lecon) {
                    if (! isset($dates[$index]) || ($lecon->date_prevue ? Carbon::parse((string) $lecon->date_prevue)->toDateString() : null) === $dates[$index]) {
                        continue;
                    }
                    $lecon->update(['date_prevue' => $dates[$index]]);
                    $modifiees++;
                }
            }
        }

        return $modifiees;
    }
}