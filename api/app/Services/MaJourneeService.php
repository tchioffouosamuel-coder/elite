<?php

namespace App\Services;

use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\ProgressionItem;
use App\Models\Seance;
use App\Models\Trimestre;
use App\Models\User;
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
            ],
            'lecons' => $lecons,
            'appel' => $this->emploiDuTemps->feuilleAppel($seance)->map(fn ($ligne) => [
                'eleve_id' => $ligne['eleve']->id,
                'nom_complet' => $ligne['eleve']->nomComplet(),
                'matricule' => $ligne['eleve']->matricule,
                'statut' => $ligne['statut'],
                'motif' => $ligne['motif'],
                'pointe' => $ligne['pointe'],
            ])->values(),
        ];
    }

    /**
     * Enregistre la journée : leçons traitées et appel.
     *
     * @param  array<int, int>  $leconIds
     * @param  array<int, array<string, mixed>>  $appel
     * @return array{lecons: int, eleves: int}
     */
    public function enregistrer(ClasseMatiere $classeMatiere, Seance $seance, array $leconIds, array $appel): array
    {
        return $this->transaction(function () use ($classeMatiere, $seance, $leconIds, $appel) {
            // Une leçon d'un autre programme n'a rien à faire dans cette séance.
            $valides = ProgressionItem::where('classe_matiere_id', $classeMatiere->id)
                ->lecons()
                ->whereIn('id', $leconIds)
                ->pluck('id');

            $seance->lecons()->sync($valides);

            $eleves = $appel === [] ? 0 : $this->emploiDuTemps->enregistrerAppel($seance, $appel);

            $seance->update(['statut' => 'effectuee']);

            return ['lecons' => $valides->count(), 'eleves' => $eleves];
        });
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
