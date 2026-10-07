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
            ->where(fn($q) => $q->where('classe_id', $classe->id)
                ->orWhere(fn($q) => $q->whereNull('classe_id')->where('niveau_id', $classe->niveau_id))
                ->orWhere(fn($q) => $q->whereNull('classe_id')->whereNull('niveau_id')->where('sous_systeme_id', $classe->sous_systeme_id))
                ->orWhere(fn($q) => $q->whereNull('classe_id')->whereNull('niveau_id')->whereNull('sous_systeme_id')))
            ->get();

        return self::appliquer(self::plusSpecifique($regles));
    }

    /**
     * Même réponse que {@see estOuvert()}, mais pour tout un intervalle d'un
     * coup : deux requêtes au total au lieu de deux par jour et par classe.
     *
     * Indispensable dès qu'on parcourt un trimestre ou une année créneau par
     * créneau — c'est ce que fait le calcul des heures prévues, qui balaie
     * chaque jour ouvré de la période pour chaque classe d'un enseignant.
     *
     * @return \Closure(Classe, Carbon): bool
     */
    public function resolveurOuverture(int $schoolId, Carbon $debut, Carbon $fin): \Closure
    {
        // Les années qui recouvrent, même partiellement, l'intervalle : hors
        // de leurs bornes, aucun jour n'est ouvert (cf. `estOuvert()`).
        $annees = AnneeScolaire::where('school_id', $schoolId)
            ->whereDate('date_debut', '<=', $fin->toDateString())
            ->whereDate('date_fin', '>=', $debut->toDateString())
            ->orderByDesc('is_active')
            ->get();

        $regles = CalendrierScolaire::whereIn('annee_scolaire_id', $annees->pluck('id'))
            ->whereBetween('date', [$debut->toDateString(), $fin->toDateString()])
            ->get()
            ->groupBy(fn(CalendrierScolaire $r) => Carbon::parse((string) $r->date)->toDateString());

        return function (Classe $classe, Carbon $date) use ($annees, $regles): bool {
            $jour = $date->toDateString();

            $annee = $annees->first(fn(AnneeScolaire $a) => $jour >= Carbon::parse((string) $a->date_debut)->toDateString()
                && $jour <= Carbon::parse((string) $a->date_fin)->toDateString());

            if (! $annee) {
                return false;
            }

            $duJour = ($regles->get($jour) ?? collect())
                ->where('annee_scolaire_id', $annee->id)
                ->filter(fn(CalendrierScolaire $r) => $r->classe_id === $classe->id
                    || ($r->classe_id === null && $r->niveau_id === $classe->niveau_id)
                    || ($r->classe_id === null && $r->niveau_id === null && $r->sous_systeme_id === $classe->sous_systeme_id)
                    || ($r->classe_id === null && $r->niveau_id === null && $r->sous_systeme_id === null));

            return self::appliquer(self::plusSpecifique($duJour));
        };
    }

    /**
     * La règle la plus ciblée l'emporte : classe, puis niveau, puis
     * sous-système, puis école entière.
     *
     * @param  Collection<int, CalendrierScolaire>  $regles
     */
    private static function plusSpecifique(Collection $regles): ?CalendrierScolaire
    {
        return $regles
            ->sortByDesc(fn(CalendrierScolaire $r) => $r->classe_id ? 3 : ($r->niveau_id ? 2 : ($r->sous_systeme_id ? 1 : 0)))
            ->first();
    }

    /** Sans règle pour ce jour, l'école est ouverte — seule une règle ferme. */
    private static function appliquer(?CalendrierScolaire $regle): bool
    {
        return $regle ? (bool) $regle->est_ouvert : true;
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
                $slots = $creneaux->filter(fn(Collection $liste) => $liste->contains('classe_matiere_id', $classeMatiere->id));
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
