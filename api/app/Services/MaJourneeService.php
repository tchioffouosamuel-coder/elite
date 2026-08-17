<?php

namespace App\Services;

use App\Models\ChampPersonnalise;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\EmploiDuTemps;
use App\Models\ProgressionItem;
use App\Models\Seance;
use App\Models\Trimestre;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Journée de l'enseignant : ce qu'il a enseigné et qui était là.
 *
 * Le point d'entrée diffère selon le cycle. Au secondaire l'enseignant
 * intervient dans plusieurs classes et doit désigner celle où il vient de
 * passer ; au primaire et en maternelle il est titulaire d'une seule classe,
 * qu'il n'y a donc pas lieu de lui faire choisir.
 */
class MaJourneeService extends BaseService
{
    public function __construct(private readonly EmploiDuTempsService $emploiDuTemps) {}

    /**
     * Affectations sur lesquelles l'enseignant peut travailler aujourd'hui.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function mesAffectations(User $user, int $schoolId): Collection
    {
        $personnelId = $user->personnel?->id;

        if ($personnelId === null) {
            return collect();
        }

        return ClasseMatiere::forSchool($schoolId)
            ->where('statut', 'actif')
            ->where(fn ($q) => $q
                ->where('personnel_id', $personnelId)
                // Le titulaire du primaire enseigne toutes les matières de sa
                // classe sans être nommé sur chaque affectation.
                ->orWhereHas('classe', fn ($c) => $c->where('titulaire_id', $personnelId)))
            ->with(['classe', 'matiere'])
            ->get()
            ->sortBy(fn (ClasseMatiere $cm) => $cm->classe->nom.' '.$cm->matiere->nom)
            ->map(fn (ClasseMatiere $cm) => [
                'classe_matiere_id' => $cm->id,
                'classe_id' => $cm->classe->id,
                'classe' => $cm->classe->nom,
                'matiere' => $cm->matiere->nom,
            ])
            ->values();
    }

    /**
     * Séance du jour pour une affectation, créée à la volée si l'enseignant
     * n'en a pas encore ouvert une : il déclare ce qu'il vient de faire, il
     * n'a pas à planifier d'abord.
     */
    public function seanceDuJour(ClasseMatiere $classeMatiere, string $date): Seance
    {
        $classe = $classeMatiere->classe;

        return Seance::firstOrCreate(
            [
                'classe_matiere_id' => $classeMatiere->id,
                'date_seance' => $date,
            ],
            [
                'school_id' => $classe->school_id,
                'classe_id' => $classe->id,
                'trimestre_id' => $this->trimestreDe($classe, $date)?->id,
                'heure_debut' => '08:00',
                'heure_fin' => '09:00',
                'statut' => 'prevue',
            ]
        );
    }

    /** Trimestre couvrant la date, sinon celui qui est actif. */
    private function trimestreDe(Classe $classe, string $date): ?Trimestre
    {
        $query = Trimestre::whereHas(
            'anneeScolaire',
            fn ($q) => $q->where('school_id', $classe->school_id)
        );

        return (clone $query)->whereDate('date_debut', '<=', $date)->whereDate('date_fin', '>=', $date)->first()
            ?? (clone $query)->where('is_active', true)->first();
    }

    /**
     * Feuille du jour : leçons du programme à cocher et appel de la classe.
     */
    public function feuilleDuJour(ClasseMatiere $classeMatiere, Seance $seance): array
    {
        $faites = $seance->lecons()->pluck('progression_items.id')->flip();

        $lecons = ProgressionItem::where('classe_matiere_id', $classeMatiere->id)
            ->lecons()
            ->with('parent.parent', 'sequence')
            ->withCount('seances')
            ->orderBy('ordre')->orderBy('id')
            ->get()
            ->map(fn (ProgressionItem $lecon) => [
                'id' => $lecon->id,
                'titre' => $lecon->titre,
                // Le chemin situe la leçon dans le programme : sans lui, une
                // liste plate de titres est illisible dès qu'ils se ressemblent.
                'chemin' => collect([$lecon->parent?->parent?->titre, $lecon->parent?->titre])
                    ->filter()->implode(' › '),
                'sequence' => $lecon->sequence?->libelle,
                'faite_aujourdhui' => $faites->has($lecon->id),
                'deja_traitee' => $lecon->seances_count > 0,
            ]);

        return [
            'seance' => [
                'id' => $seance->id,
                'date' => $seance->date_seance->format('Y-m-d'),
                'heure_debut' => $seance->heure_debut,
                'heure_fin' => $seance->heure_fin,
                'statut' => $seance->statut,
                'observations' => $seance->observations,
                'donnees_personnalisees' => $seance->donnees_personnalisees ?? [],
            ],
            'lecons' => $lecons,
            'appel' => $this->emploiDuTemps->feuilleAppel($seance)->map(fn ($ligne) => [
                'eleve_id' => $ligne['eleve']->id,
                'nom_complet' => $ligne['eleve']->nom_complet,
                'matricule' => $ligne['eleve']->matricule,
                'statut' => $ligne['statut'],
                'motif' => $ligne['motif'],
                'pointe' => $ligne['pointe'],
            ])->values(),
            // Tableaux d'informations spécifiques à la matière : définis une fois
            // par l'enseignant, remplis à chaque séance.
            'champs_personnalises' => ChampPersonnalise::where('classe_matiere_id', $classeMatiere->id)
                ->orderBy('ordre')->orderBy('id')
                ->get(['id', 'libelle', 'type']),
        ];
    }

    /**
     * Enregistre la journée : leçons traitées, appel, observations et champs
     * personnalisés.
     *
     * @param  array<int, int>  $leconIds
     * @param  array<int, array<string, mixed>>  $appel
     * @param  array<string, mixed>  $donneesPersonnalisees
     * @return array{lecons: int, eleves: int}
     */
    public function enregistrer(
        ClasseMatiere $classeMatiere,
        Seance $seance,
        array $leconIds,
        array $appel,
        ?string $observations = null,
        array $donneesPersonnalisees = [],
    ): array {
        return $this->transaction(function () use ($classeMatiere, $seance, $leconIds, $appel, $observations, $donneesPersonnalisees) {
            // Une leçon d'un autre programme n'a rien à faire dans cette séance.
            $valides = ProgressionItem::where('classe_matiere_id', $classeMatiere->id)
                ->lecons()
                ->whereIn('id', $leconIds)
                ->pluck('id');

            $seance->lecons()->sync($valides);

            $eleves = $appel === [] ? 0 : $this->emploiDuTemps->enregistrerAppel($seance, $appel);

            $seance->update([
                'statut' => 'effectuee',
                'observations' => $observations,
                'donnees_personnalisees' => $donneesPersonnalisees ?: null,
            ]);

            return ['lecons' => $valides->count(), 'eleves' => $eleves];
        });
    }

    /**
     * Heures de couverture de l'enseignant depuis le début de l'année : ce qui
     * était prévu à son emploi du temps jusqu'à aujourd'hui, comparé à ce
     * qu'il a réellement déclaré. Une séance « prévue » restée sans appel
     * après sa date signale un cours non couvert.
     *
     * @return array{heures_prevues: float, heures_realisees: float, taux: float, seances_en_retard: int}
     */
    public function heuresCouverture(User $user, int $schoolId): array
    {
        $personnelId = $user->personnel?->id;

        if ($personnelId === null) {
            return ['heures_prevues' => 0.0, 'heures_realisees' => 0.0, 'taux' => 0.0, 'seances_en_retard' => 0];
        }

        $classeMatiereIds = ClasseMatiere::forSchool($schoolId)
            ->where('statut', 'actif')
            ->where(fn ($q) => $q
                ->where('personnel_id', $personnelId)
                ->orWhereHas('classe', fn ($c) => $c->where('titulaire_id', $personnelId)))
            ->pluck('id');

        $seances = Seance::whereIn('classe_matiere_id', $classeMatiereIds)
            ->whereDate('date_seance', '<=', now())
            ->get(['statut', 'heure_debut', 'heure_fin', 'date_seance']);

        $prevues = (float) $seances->sum(fn (Seance $s) => $s->dureeHeures());
        $realisees = (float) $seances->where('statut', 'effectuee')->sum(fn (Seance $s) => $s->dureeHeures());
        $enRetard = $seances->where('statut', 'prevue')
            ->filter(fn (Seance $s) => $s->date_seance->lt(now()->startOfDay()))
            ->count();

        return [
            'heures_prevues' => round($prevues, 1),
            'heures_realisees' => round($realisees, 1),
            'taux' => $prevues > 0 ? round($realisees / $prevues * 100, 1) : 0.0,
            'seances_en_retard' => $enRetard,
        ];
    }

    /**
     * Créneau en cours dans une salle, résolu depuis son emploi du temps —
     * c'est ce que le QR code affiché au mur ouvre en le faisant scanner.
     * Une marge de 10 minutes de part et d'autre couvre le temps d'installation.
     */
    public function creneauActuel(Classe $classe): ?ClasseMatiere
    {
        $maintenant = now();

        $creneau = EmploiDuTemps::where('classe_id', $classe->id)
            ->where('jour', $maintenant->dayOfWeekIso)
            ->get()
            ->first(function (EmploiDuTemps $c) use ($maintenant) {
                $debut = Carbon::parse($c->heure_debut)->subMinutes(10);
                $fin = Carbon::parse($c->heure_fin)->addMinutes(10);
                $heure = Carbon::parse($maintenant->format('H:i:s'));

                return $heure->between($debut, $fin);
            });

        return $creneau?->classeMatiere;
    }

    /** L'enseignant ne peut déclarer que sur ses propres affectations. */
    public function peutIntervenir(User $user, ClasseMatiere $classeMatiere): bool
    {
        if ($user->hasAnyRole(['super_admin', 'admin_etablissement', 'censeur_sg'])) {
            return true;
        }

        $personnelId = $user->personnel?->id;

        return $personnelId !== null && (
            $classeMatiere->personnel_id === $personnelId
            || $classeMatiere->classe->titulaire_id === $personnelId
        );
    }
}
