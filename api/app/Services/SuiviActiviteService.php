<?php

namespace App\Services;

use App\Models\AnneeScolaire;
use App\Models\ClasseMatiere;
use App\Models\PresencePersonnelJournaliere;
use App\Models\ProgressionItem;
use App\Models\Seance;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Vue admin transverse de ce que fait déjà `MaJourneeService::heuresCouverture()`
 * pour un seul enseignant : prévu vs réalisé, mais pour tout le personnel et
 * ventilé par période — de quoi tracer l'activité et rapprocher la paie.
 */
class SuiviActiviteService
{
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

        $seances = Seance::forSchool($schoolId)
            ->whereBetween('date_seance', [$debut, $fin])
            ->whereHas('classeMatiere', function ($q) use ($personnelId, $sousSystemeId, $departementId) {
                // Un enseignant du secondaire est nommé sur l'affectation ; au
                // primaire/maternelle, c'est le titulaire de la classe qui
                // couvre toutes les matières sans y être nommé lui-même.
                $q->where(fn($q2) => $q2
                    ->whereNotNull('personnel_id')
                    ->orWhereHas('classe', fn($c) => $c->whereNotNull('titulaire_id')));

                if ($personnelId) {
                    $q->where(fn($q2) => $q2
                        ->where('personnel_id', $personnelId)
                        ->orWhereHas('classe', fn($c) => $c->where('titulaire_id', $personnelId)));
                }

                if ($sousSystemeId) {
                    $q->whereHas('classe', fn($c) => $c->where('sous_systeme_id', $sousSystemeId));
                }

                if ($departementId) {
                    $q->where(fn($q2) => $q2
                        ->whereHas('enseignant', fn($p) => $p->where('departement_id', $departementId))
                        ->orWhereHas('classe.titulaire', fn($p) => $p->where('departement_id', $departementId)));
                }
            })
            ->with(['classeMatiere.enseignant', 'classeMatiere.classe.titulaire'])
            ->get(['id', 'classe_matiere_id', 'date_seance', 'heure_debut', 'heure_fin', 'statut']);

        return $seances
            ->groupBy(fn(Seance $s) => $s->classeMatiere->personnel_id ?? $s->classeMatiere->classe->titulaire_id)
            ->map(fn(Collection $seancesPersonnel) => $this->ligne($seancesPersonnel, $granularite))
            ->sortBy('nom_complet')
            ->values();
    }

    /** @param Collection<int, Seance> $seances */
    private function ligne(Collection $seances, string $granularite): array
    {
        $classeMatiere = $seances->first()->classeMatiere;
        $personnel = $classeMatiere->enseignant ?? $classeMatiere->classe->titulaire;

        $periodes = $seances
            ->groupBy(fn(Seance $s) => $this->cle($s->date_seance, $granularite))
            ->map(fn(Collection $groupe, string $cle) => $this->resume($groupe) + ['periode' => $cle])
            ->sortBy('periode')
            ->values();

        return [
            'personnel_id' => $personnel->id,
            'nom_complet' => $personnel->nom_complet,
            'fonction' => $personnel->fonction,
            'periodes' => $periodes,
            'totaux' => $this->resume($seances),
        ];
    }

    /** @param Collection<int, Seance> $groupe */
    private function resume(Collection $groupe): array
    {
        $prevues = (float) $groupe->sum(fn(Seance $s) => $s->dureeHeures());
        $realisees = (float) $groupe->where('statut', 'effectuee')->sum(fn(Seance $s) => $s->dureeHeures());

        return [
            'heures_prevues' => round($prevues, 1),
            'heures_realisees' => round($realisees, 1),
            'taux' => $prevues > 0 ? round($realisees / $prevues * 100, 1) : 0.0,
            'seances_prevues' => $groupe->count(),
            'seances_realisees' => $groupe->where('statut', 'effectuee')->count(),
            'seances_annulees' => $groupe->where('statut', 'annulee')->count(),
            'seances_en_retard' => $groupe->where('statut', 'prevue')
                ->filter(fn(Seance $s) => $s->date_seance->lt(now()->startOfDay()))
                ->count(),
        ];
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

            return $this->resume($seances) + [
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
