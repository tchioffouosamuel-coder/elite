<?php

namespace App\Services;

use App\Models\AnneeScolaire;
use App\Models\ClasseMatiere;
use App\Models\PresencePersonnelJournaliere;
use App\Models\ProgressionItem;
use App\Models\Seance;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Vue admin transverse de ce que fait déjà `MaJourneeService::heuresCouverture()`
 * pour un seul enseignant : prévu vs réalisé, mais pour tout le personnel et
 * ventilé par période — de quoi tracer l'activité et rapprocher la paie.
 */
class SuiviActiviteService
{
    public function __construct(private readonly EmploiDuTempsService $emploiDuTemps) {}

    /**
     * @param array{personnel_id?: ?int, sous_systeme_id?: ?int, departement_id?: ?int} $filtres
     * @return Collection<int, array{
     *     personnel_id: int, nom_complet: string, fonction: ?string,
     *     periodes: Collection<int, array<string, mixed>>,
     *     totaux: array<string, mixed>,
     * }>
     */
    public function parPersonnel(int $schoolId, CarbonImmutable $debut, CarbonImmutable $fin, string $granularite, array $filtres = []): Collection
    {
        $personnelId = $filtres['personnel_id'] ?? null;
        $sousSystemeId = $filtres['sous_systeme_id'] ?? null;
        $departementId = $filtres['departement_id'] ?? null;

        // On part des affectations, pas des séances : un enseignant qui n'a
        // rien déclaré du tout n'a aucune séance et disparaissait donc
        // purement et simplement du suivi — l'angle mort exact que ce suivi
        // est censé éclairer.
        $affectations = ClasseMatiere::forSchool($schoolId)
            ->where('statut', 'actif')
            ->where(fn($q) => $q
                // Un enseignant du secondaire est nommé sur l'affectation ; au
                // primaire/maternelle, c'est le titulaire de la classe qui
                // couvre toutes les matières sans y être nommé lui-même.
                ->whereNotNull('personnel_id')
                ->orWhereHas('classe', fn($c) => $c->whereNotNull('titulaire_id')))
            ->when($personnelId, fn($q) => $q->where(fn($q2) => $q2
                ->where('personnel_id', $personnelId)
                ->orWhereHas('classe', fn($c) => $c->where('titulaire_id', $personnelId))))
            ->when($sousSystemeId, fn($q) => $q->whereHas('classe', fn($c) => $c->where('sous_systeme_id', $sousSystemeId)))
            ->when($departementId, fn($q) => $q->where(fn($q2) => $q2
                ->whereHas('enseignant', fn($p) => $p->where('departement_id', $departementId))
                ->orWhereHas('classe.titulaire', fn($p) => $p->where('departement_id', $departementId))))
            ->with(['enseignant', 'classe.titulaire'])
            ->get()
            ->filter(fn(ClasseMatiere $cm) => ($cm->enseignant ?? $cm->classe?->titulaire) !== null)
            ->groupBy(fn(ClasseMatiere $cm) => $cm->personnel_id ?? $cm->classe->titulaire_id);

        $seances = Seance::forSchool($schoolId)
            ->whereBetween('date_seance', [$debut, $fin])
            ->whereIn('classe_matiere_id', $affectations->flatten()->pluck('id'))
            ->get(['id', 'classe_matiere_id', 'date_seance', 'heure_debut', 'heure_fin', 'statut'])
            ->groupBy('classe_matiere_id');

        return $affectations
            ->map(fn(Collection $cours) => $this->ligne(
                $cours,
                $cours->flatMap(fn(ClasseMatiere $cm) => $seances->get($cm->id) ?? collect()),
                $debut,
                $fin,
                $granularite,
            ))
            ->sortBy('nom_complet')
            ->values();
    }

    /**
     * @param  Collection<int, ClasseMatiere>  $cours
     * @param  Collection<int, Seance>  $seances
     */
    private function ligne(Collection $cours, Collection $seances, CarbonImmutable $debut, CarbonImmutable $fin, string $granularite): array
    {
        $premier = $cours->first();
        $personnel = $premier->enseignant ?? $premier->classe->titulaire;
        $ids = $cours->pluck('id');

        $prevuParDate = $this->emploiDuTemps->creneauxPrevusParDate(
            $ids,
            Carbon::parse($debut->toDateString()),
            Carbon::parse($this->borneHaute($fin)->toDateString()),
        );

        $realiseParDate = $seances->groupBy(fn(Seance $s) => $s->date_seance->format('Y-m-d'));

        // L'union des deux : une période sans aucun créneau prévu mais avec
        // une séance déclarée (rattrapage, cours ajouté hors grille) doit
        // apparaître, et l'inverse aussi.
        $periodes = $prevuParDate->keys()->merge($realiseParDate->keys())
            ->groupBy(fn(string $date) => $this->cle(Carbon::parse($date), $granularite))
            ->map(fn(Collection $dates, string $cle) => $this->resume(
                $dates->flatMap(fn(string $d) => $realiseParDate->get($d) ?? collect()),
                $dates->sum(fn(string $d) => $prevuParDate->get($d)['heures'] ?? 0.0),
                $dates->sum(fn(string $d) => $prevuParDate->get($d)['creneaux'] ?? 0),
            ) + ['periode' => $cle])
            ->sortKeys()
            ->values();

        return [
            'personnel_id' => $personnel->id,
            'nom_complet' => $personnel->nom_complet,
            'fonction' => $personnel->fonction,
            'periodes' => $periodes,
            'totaux' => $this->resume($seances, (float) $prevuParDate->sum('heures'), (int) $prevuParDate->sum('creneaux')),
        ];
    }

    /**
     * Prévu/réalisé d'un groupe de séances. Le prévu vient de l'emploi du
     * temps (cf. `EmploiDuTempsService::creneauxPrevusParDate()`), jamais du
     * nombre de séances matérialisées : sans génération préalable, celles-ci
     * se réduisent à ce que l'enseignant a lui-même déclaré, donc à un taux
     * de couverture artificiellement parfait.
     *
     * @param  Collection<int, Seance>  $groupe
     */
    private function resume(Collection $groupe, float $heuresPrevues, int $creneauxPrevus): array
    {
        $realisees = (float) $groupe->where('statut', 'effectuee')->sum(fn(Seance $s) => $s->dureeHeures());
        $faites = $groupe->where('statut', 'effectuee')->count();

        return [
            'heures_prevues' => round($heuresPrevues, 1),
            'heures_realisees' => round($realisees, 1),
            'taux' => $heuresPrevues > 0 ? round($realisees / $heuresPrevues * 100, 1) : 0.0,
            'seances_prevues' => $creneauxPrevus,
            'seances_realisees' => $faites,
            'seances_annulees' => $groupe->where('statut', 'annulee')->count(),
            // En creux : un créneau que personne n'a jamais ouvert n'a pas de
            // ligne dans `seances` et échappait au comptage par statut.
            'seances_en_retard' => max(0, $creneauxPrevus - $faites),
        ];
    }

    /** Pas de « prévu » au-delà d'aujourd'hui : un cours à venir n'est pas en retard. */
    private function borneHaute(CarbonImmutable $fin): CarbonImmutable
    {
        $aujourdhui = CarbonImmutable::now()->endOfDay();

        return $fin->greaterThan($aujourdhui) ? $aujourdhui : $fin;
    }

    /**
     * Prévu vs réalisé d'un seul personnel, pour le jour, la semaine, le mois,
     * le trimestre et l'année scolaires — le pendant personnel de `parPersonnel()`, pour son
     * propre tableau de bord plutôt que la vue transverse admin.
     *
     * @return array<string, array<string, mixed>|null>
     */
    public function resumePersonnel(int $schoolId, int $personnelId, CarbonImmutable $maintenant): array
    {
        $date = $maintenant->toDateString();
        $annees = AnneeScolaire::where('school_id', $schoolId);
        $annee = (clone $annees)->whereDate('date_debut', '<=', $date)
            ->whereDate('date_fin', '>=', $date)->orderByDesc('is_active')->first()
            ?? (clone $annees)->where('is_active', true)->first();
        $trimestre = $annee?->trimestres()->whereDate('date_debut', '<=', $date)
            ->whereDate('date_fin', '>=', $date)->first();

        $classeMatiereIds = ClasseMatiere::forSchool($schoolId)
            ->where('statut', 'actif')
            ->where(fn($q) => $q
                ->where('personnel_id', $personnelId)
                ->orWhereHas('classe', fn($c) => $c->where('titulaire_id', $personnelId)))
            ->pluck('id');

        $bornes = [
            'jour' => [$maintenant->startOfDay(), $maintenant->endOfDay()],
            'semaine' => [$maintenant->startOfWeek(), $maintenant->endOfWeek()],
            'mois' => [$maintenant->startOfMonth(), $maintenant->endOfMonth()],
            'trimestre' => $trimestre ? [$trimestre->date_debut, $trimestre->date_fin] : null,
            'annee' => $annee ? [$annee->date_debut, $annee->date_fin] : [$maintenant->startOfYear(), $maintenant->endOfYear()],
        ];

        return collect($bornes)->map(function (?array $borne, string $periode) use ($schoolId, $classeMatiereIds, $annee, $trimestre) {
            if ($borne === null) {
                return null;
            }
            $dates = array_map(fn ($d) => $d->format('Y-m-d'), $borne);
            $seances = Seance::forSchool($schoolId)->whereIn('classe_matiere_id', $classeMatiereIds)
                ->whereDate('date_seance', '>=', $dates[0])->whereDate('date_seance', '<=', $dates[1])
                ->get(['statut', 'heure_debut', 'heure_fin', 'date_seance']);

            // Le prévu vient de la grille, arrêté à aujourd'hui : sur le
            // trimestre ou l'année, compter les créneaux encore à venir
            // afficherait un retard permanent dès la rentrée.
            $prevu = $this->emploiDuTemps->totalPrevu(
                $classeMatiereIds,
                Carbon::parse($dates[0]),
                Carbon::parse($dates[1])->min(Carbon::now())->endOfDay(),
            );

            $lecons = ProgressionItem::whereIn('classe_matiere_id', $classeMatiereIds)->lecons();
            $prevues = (clone $lecons)->where(function ($q) use ($dates, $periode, $annee, $trimestre) {
                $q->whereDate('date_prevue', '>=', $dates[0])->whereDate('date_prevue', '<=', $dates[1]);
                // Les programmes non datés restent prévus sur leur période scolaire,
                // sans inventer une planification au jour, à la semaine ou au mois.
                if ($periode === 'trimestre') {
                    $q->orWhere(fn ($p) => $p->whereNull('date_prevue')
                        ->whereHas('sequence', fn ($s) => $s->where('trimestre_id', $trimestre->id)));
                } elseif ($periode === 'annee' && $annee) {
                    $q->orWhere(fn ($p) => $p->whereNull('date_prevue')->where(fn ($s) => $s
                        ->whereNull('sequence_id')->orWhereHas('sequence.trimestre', fn ($t) => $t->where('annee_scolaire_id', $annee->id))));
                }
            })->count();
            // Une leçon validée sur plusieurs séances ne compte qu'une fois.
            $faites = (clone $lecons)->where(fn ($q) => $q
                ->where(fn ($r) => $r->whereDate('date_realisee', '>=', $dates[0])->whereDate('date_realisee', '<=', $dates[1]))
                ->orWhereHas('seances', fn ($s) => $s->forSchool($schoolId)
                    ->whereIn('classe_matiere_id', $classeMatiereIds)
                    ->where('statut', 'effectuee')
                    ->whereDate('date_seance', '>=', $dates[0])->whereDate('date_seance', '<=', $dates[1])))
                ->count();

            return $this->resume($seances, (float) $prevu['heures'], (int) $prevu['creneaux']) + [
                'lecons_prevues' => $prevues,
                'lecons_realisees' => $faites,
                'date_debut' => $dates[0],
                'date_fin' => $dates[1],
            ];
        })->all();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function incoherencesPresence(int $schoolId, CarbonImmutable $debut, CarbonImmutable $fin, array $filtres = []): Collection
    {
        $personnelId = $filtres['personnel_id'] ?? null;

        $seances = Seance::forSchool($schoolId)
            ->where('statut', 'effectuee')
            ->whereBetween('date_seance', [$debut, $fin])
            ->whereHas('classeMatiere', function ($q) use ($personnelId) {
                if ($personnelId) {
                    $q->where(fn ($q2) => $q2
                        ->where('personnel_id', $personnelId)
                        ->orWhereHas('classe', fn ($c) => $c->where('titulaire_id', $personnelId)));
                }
            })
            ->with(['classeMatiere.enseignant', 'classeMatiere.matiere', 'classeMatiere.classe.titulaire', 'classe'])
            ->orderByDesc('date_seance')
            ->orderBy('heure_debut')
            ->get();

        $presences = PresencePersonnelJournaliere::forSchool($schoolId)
            ->whereBetween('date_presence', [$debut, $fin])
            ->get()
            ->keyBy(fn (PresencePersonnelJournaliere $p) => $p->personnel_id.'|'.$p->date_presence->format('Y-m-d'));

        return $seances
            ->map(function (Seance $seance) use ($presences) {
                $personnel = $seance->classeMatiere?->enseignant ?? $seance->classeMatiere?->classe?->titulaire;
                if (! $personnel) {
                    return null;
                }

                $date = $seance->date_seance->format('Y-m-d');
                $presence = $presences->get($personnel->id.'|'.$date);
                $motifs = [];

                if (! $presence || ! $presence->heure_arrivee) {
                    $motifs[] = 'presence_absente';
                } else {
                    $debut = substr((string) $seance->heure_debut, 0, 5);
                    $fin = substr((string) $seance->heure_fin, 0, 5);
                    $arrivee = substr((string) $presence->heure_arrivee, 0, 5);
                    $depart = $presence->heure_depart ? substr((string) $presence->heure_depart, 0, 5) : null;

                    if ($debut < $arrivee) {
                        $motifs[] = 'cours_avant_arrivee';
                    }
                    if ($depart !== null && $fin > $depart) {
                        $motifs[] = 'cours_apres_depart';
                    }
                }

                if ($motifs === []) {
                    return null;
                }

                return [
                    'seance_id' => $seance->id,
                    'date' => $date,
                    'heure_debut' => substr((string) $seance->heure_debut, 0, 5),
                    'heure_fin' => substr((string) $seance->heure_fin, 0, 5),
                    'personnel_id' => $personnel->id,
                    'personnel' => $personnel->nom_complet,
                    'classe' => $seance->classe?->nom,
                    'matiere' => $seance->classeMatiere?->matiere?->nom,
                    'heure_arrivee' => $presence?->heure_arrivee ? substr((string) $presence->heure_arrivee, 0, 5) : null,
                    'heure_depart' => $presence?->heure_depart ? substr((string) $presence->heure_depart, 0, 5) : null,
                    'motifs' => $motifs,
                ];
            })
            ->filter()
            ->values();
    }

    private function cle(\Illuminate\Support\Carbon $date, string $granularite): string
    {
        return match ($granularite) {
            'semaine' => $date->format('o') . '-S' . $date->format('W'),
            'mois' => $date->format('Y-m'),
            'annee' => $date->format('Y'),
            default => $date->format('Y-m-d'),
        };
    }
}
