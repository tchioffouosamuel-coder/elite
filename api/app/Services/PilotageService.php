<?php

namespace App\Services;

use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\EmploiDuTemps;
use App\Models\Seance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Pilotage en temps réel de l'établissement, pour le tableau de bord :
 * ce qui se joue « maintenant » (cours en cours, cours à venir), les manques
 * (classes sans enseignant) et l'avancement global du programme.
 *
 * Contrairement à `DashboardService::stats()`, appelé à chaque ouverture du
 * tableau de bord, ce bloc est coûteux (parcourt l'emploi du temps du jour et
 * tout le programme de l'établissement) : il vit derrière son propre point
 * d'entrée, chargé à la demande plutôt qu'au chargement de la page.
 */
class PilotageService extends BaseService
{
    public function __construct(private readonly ProgressionService $progression) {}

    /** @param int|array<int> $schoolId */
    public function pilotage(int|array $schoolId): array
    {
        $maintenant = Carbon::now();

        return [
            'genere_le' => $maintenant->toIso8601String(),
            ...$this->creneauxDuJour($schoolId, $maintenant),
            'classes_sans_enseignant' => $this->classesSansEnseignant($schoolId)->values()->all(),
            'couverture' => $this->couvertureGlobale($schoolId),
        ];
    }

    /**
     * Cours en cours, cours à venir dans la journée, et cours dont l'heure est
     * passée sans que l'appel ait été fait — chacun sur la base de l'emploi du
     * temps du jour, les créneaux annulés (séance du jour au statut « annulée »)
     * étant écartés.
     *
     * @param  int|array<int>  $schoolId
     * @return array{cours_en_cours: list<array<string, mixed>>, cours_a_venir: list<array<string, mixed>>, appels_en_retard: list<array<string, mixed>>}
     */
    private function creneauxDuJour(int|array $schoolId, Carbon $maintenant): array
    {
        $jour = $maintenant->dayOfWeekIso;
        $heure = $maintenant->format('H:i:s');

        $creneaux = EmploiDuTemps::forSchool($schoolId)
            ->where('jour', $jour)
            ->with(['classe.school', 'classe.titulaire', 'classesAssociees', 'classeMatiere.matiere', 'classeMatiere.enseignant'])
            ->orderBy('heure_debut')
            ->get();

        if ($creneaux->isEmpty()) {
            return ['cours_en_cours' => [], 'cours_a_venir' => [], 'appels_en_retard' => []];
        }

        // Les séances déjà matérialisées pour aujourd'hui disent si un créneau
        // a été annulé ou si l'appel y a été fait — sans elles, un créneau du
        // planning type reste une simple prévision.
        $seancesDuJour = Seance::forSchool($schoolId)
            ->whereDate('date_seance', $maintenant->toDateString())
            ->whereNotNull('emploi_du_temps_id')
            ->get()
            ->keyBy('emploi_du_temps_id');

        $enCours = collect();
        $aVenir = collect();
        $enRetard = collect();

        foreach ($creneaux as $creneau) {
            $seance = $seancesDuJour->get($creneau->id);

            if ($seance?->statut === 'annulee') {
                continue;
            }

            $ligne = $this->presenterCreneau($creneau, $seance);

            if ($creneau->heure_debut <= $heure && $creneau->heure_fin >= $heure) {
                $enCours->push($ligne);
            } elseif ($creneau->heure_debut > $heure) {
                $aVenir->push($ligne);
            } elseif ($seance?->statut !== 'effectuee') {
                $enRetard->push($ligne);
            }
        }

        return [
            'cours_en_cours' => $enCours->values()->all(),
            'cours_a_venir' => $aVenir->take(8)->values()->all(),
            'appels_en_retard' => $enRetard->sortByDesc('heure_fin')->take(8)->values()->all(),
        ];
    }

    private function presenterCreneau(EmploiDuTemps $creneau, ?Seance $seance): array
    {
        $classes = $creneau->toutesLesClasses();

        return [
            'emploi_du_temps_id' => $creneau->id,
            'classe' => $classes->pluck('nom')->implode(' + '),
            'ecole' => $creneau->classe?->school?->name,
            'matiere' => $creneau->classeMatiere?->matiere?->nom,
            'enseignant' => $creneau->classeMatiere?->enseignant?->nom_complet ?? $creneau->classe?->titulaire?->nom_complet,
            'salle' => $creneau->salle,
            'heure_debut' => substr($creneau->heure_debut, 0, 5),
            'heure_fin' => substr($creneau->heure_fin, 0, 5),
            'appel_fait' => $seance?->statut === 'effectuee',
        ];
    }

    /**
     * Classes sans enseignant : sans titulaire au primaire/maternelle, sans
     * enseignant affecté à une matière active au secondaire.
     *
     * @param  int|array<int>  $schoolId
     * @return Collection<int, array<string, mixed>>
     */
    private function classesSansEnseignant(int|array $schoolId): Collection
    {
        $matieresSansEnseignant = ClasseMatiere::where('statut', 'actif')
            ->whereNull('personnel_id')
            ->whereHas('classe', fn ($q) => $q->forSchool($schoolId)->whereHas('school', fn ($s) => $s->where('type', 'secondaire')))
            ->with(['classe.school', 'matiere'])
            ->get()
            ->map(fn (ClasseMatiere $cm) => [
                'classe' => $cm->classe->nom,
                'matiere' => $cm->matiere->nom,
                'ecole' => $cm->classe->school->name,
            ]);

        $classesSansTitulaire = Classe::forSchool($schoolId)
            ->whereNull('titulaire_id')
            ->whereHas('school', fn ($s) => $s->whereIn('type', ['primaire', 'maternelle']))
            ->with('school')
            ->get()
            ->map(fn (Classe $classe) => [
                'classe' => $classe->nom,
                'matiere' => null,
                'ecole' => $classe->school->name,
            ]);

        return $matieresSansEnseignant->concat($classesSansTitulaire);
    }

    /**
     * Avancement du programme sur tout le périmètre : taux global (leçons
     * traitées / prévues) et les classes les plus en retard, pour orienter le
     * suivi plutôt que de se contenter d'un pourcentage sans relief.
     *
     * @param  int|array<int>  $schoolId
     */
    private function couvertureGlobale(int|array $schoolId): array
    {
        $parClasse = $this->progression->tauxEtablissement($schoolId);

        $lecons = (int) $parClasse->sum('lecons');
        $traitees = (int) $parClasse->sum('traitees');

        $enRetard = $parClasse->filter(fn (array $c) => $c['lecons'] > 0)
            ->sortBy('taux')
            ->take(5)
            ->map(fn (array $c) => ['classe' => $c['classe'], 'niveau' => $c['niveau'], 'taux' => $c['taux']])
            ->values();

        return [
            'lecons' => $lecons,
            'traitees' => $traitees,
            'taux' => $lecons > 0 ? round($traitees / $lecons * 100, 1) : 0.0,
            'classes_en_retard' => $enRetard->all(),
        ];
    }
}
