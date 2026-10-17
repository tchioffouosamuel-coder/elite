<?php

namespace App\Services;

use App\Models\Classe;
use App\Models\ClasseCompetence;
use App\Models\ClasseMatiere;
use App\Models\Matiere;
use App\Models\Note;
use App\Models\Sequence;
use App\Support\Progression\ResolveurFicheProgression;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Point 15 de la punch-list : tableau de suivi transverse pour la direction —
 * notes manquantes et leçons en retard, classe par classe, matière (ou
 * compétence) par matière, enseignant par enseignant — pour la séquence
 * actuellement active de l'école.
 *
 * « Séquence active » reprend la même lecture que
 * `ClasseMatiereController::mesAffectations()` : la première séquence du
 * trimestre actif, faute d'une colonne `is_active` portée par `Sequence`
 * elle-même.
 *
 * Le secondaire note par matière (`classe_matieres` + `Note.classe_matiere_id`),
 * le primaire et la maternelle par compétence (`classe_competences` +
 * `Note.classe_competence_id`, cf. {@see NotePrimaireService}) — une école a
 * un seul `type` (`School::estSecondaire()`), jamais les deux mélangés.
 *
 * Les leçons, elles, restent toujours portées par l'affectation
 * classe↔matière ({@see ProgressionItem}), y compris au primaire où une
 * compétence installe une `ClasseMatiere` par matière qu'elle regroupe (cf.
 * `CompetenceAttributionService::installerMatieres()`) : une ligne de
 * compétence agrège donc les leçons de toutes ses matières dans la classe.
 */
class SuiviSaisiesService
{
    /**
     * Première séquence du trimestre actif de l'école — faute d'un marqueur
     * `is_active` propre à `Sequence`, c'est elle qui fait référence partout
     * ailleurs dans l'application (cf. docblock de la classe).
     */
    public function sequenceActive(int $schoolId): ?Sequence
    {
        return Sequence::whereHas(
            'trimestre',
            fn ($q) => $q->where('is_active', true)
                ->whereHas('anneeScolaire', fn ($aq) => $aq->where('school_id', $schoolId))
        )->orderBy('ordre')->first();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function pourSequenceActive(int $schoolId, ?int $classeId = null, ?int $matiereId = null): Collection
    {
        $sequence = $this->sequenceActive($schoolId);

        if (! $sequence) {
            return collect();
        }

        $classes = Classe::forSchool($schoolId)
            ->when($classeId, fn ($q) => $q->where('id', $classeId))
            ->with('school', 'titulaire')
            ->get();

        return $classes
            ->flatMap(fn (Classe $classe) => $classe->school?->estSecondaire()
                ? $this->lignesSecondaire($classe, $sequence, $matiereId)
                : $this->lignesPrimaire($classe, $sequence, $matiereId))
            ->sortBy(['classe', 'matiere'])
            ->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    private function lignesSecondaire(Classe $classe, Sequence $sequence, ?int $matiereId): Collection
    {
        return ClasseMatiere::where('classe_id', $classe->id)
            ->where('statut', 'actif')
            ->when($matiereId, fn ($q) => $q->where('matiere_id', $matiereId))
            ->with('matiere', 'enseignant')
            ->get()
            ->map(function (ClasseMatiere $cm) use ($classe, $sequence) {
                $effectif = $classe->eleves()->where('statut', 'actif')->inscritAnneeActive()->count();
                $attendues = $effectif; // une seule composante ('unique') au secondaire
                $saisies = Note::where('classe_matiere_id', $cm->id)
                    ->where('sequence_id', $sequence->id)
                    ->where('composante', 'unique')
                    ->whereNotNull('valeur')
                    ->count();

                $lecons = $this->lecons($cm, $sequence);

                return [
                    'classe_id' => $classe->id,
                    'classe' => $classe->nom,
                    'matiere_id' => $cm->matiere_id,
                    'matiere' => $cm->matiere->nom,
                    'enseignant_id' => $cm->personnel_id,
                    'enseignant' => $cm->enseignant?->nom_complet ?? '—',
                    'notes_attendues' => $attendues,
                    'notes_saisies' => $saisies,
                    'notes_taux' => $attendues > 0 ? round($saisies / $attendues * 100, 1) : 0.0,
                    ...$lecons,
                ];
            });
    }

    /** @return Collection<int, array<string, mixed>> */
    private function lignesPrimaire(Classe $classe, Sequence $sequence, ?int $matiereId): Collection
    {
        return $classe->classeCompetences()->where('statut', 'actif')
            ->with('competence')
            ->get()
            // `matiere_id` filtre par matière : on ne garde que les compétences
            // qui installent cette matière précise dans la classe.
            ->when($matiereId, fn (Collection $q) => $q->filter(
                fn (ClasseCompetence $cc) => Matiere::where('competence_id', $cc->competence_id)->where('id', $matiereId)->exists()
            ))
            ->map(function (ClasseCompetence $cc) use ($classe, $sequence) {
                $effectif = $classe->eleves()->where('statut', 'actif')->inscritAnneeActive()->count();
                $maternelle = (bool) $classe->school?->estMaternelle();
                $volets = count($cc->voletsNotes());
                $attendues = $effectif * max($volets, 1);

                $saisies = Note::where('classe_competence_id', $cc->id)
                    ->where('sequence_id', $sequence->id)
                    ->get()
                    ->filter(fn (Note $n) => $maternelle ? $n->appreciation_id !== null : $n->valeur !== null)
                    ->count();

                $cmIds = ClasseMatiere::where('classe_id', $classe->id)
                    ->whereIn('matiere_id', Matiere::where('competence_id', $cc->competence_id)->pluck('id'))
                    ->get();

                $lecons = $cmIds->map(fn (ClasseMatiere $cm) => $this->lecons($cm, $sequence))
                    ->reduce(fn (array $acc, array $l) => [
                        'lecons_attendues' => $acc['lecons_attendues'] + $l['lecons_attendues'],
                        'lecons_faites' => $acc['lecons_faites'] + $l['lecons_faites'],
                        'lecons_taux' => 0.0,
                    ], ['lecons_attendues' => 0, 'lecons_faites' => 0, 'lecons_taux' => 0.0]);
                $lecons['lecons_taux'] = $lecons['lecons_attendues'] > 0
                    ? round($lecons['lecons_faites'] / $lecons['lecons_attendues'] * 100, 1)
                    : 0.0;

                return [
                    'classe_id' => $classe->id,
                    'classe' => $classe->nom,
                    'matiere_id' => $cc->competence_id,
                    'matiere' => $cc->competence->label_fr,
                    'enseignant_id' => $cc->enseignantEffectifId(),
                    'enseignant' => $classe->titulaire?->nom_complet
                        ?? $cc->enseignant?->nom_complet ?? '—',
                    'notes_attendues' => $attendues,
                    'notes_saisies' => $saisies,
                    'notes_taux' => $attendues > 0 ? round($saisies / $attendues * 100, 1) : 0.0,
                    ...$lecons,
                ];
            })
            ->values();
    }

    /**
     * Leçons prévues (`date_prevue` déjà passée) et réellement données
     * (`date_realisee` renseignée), pour cette affectation et cette séquence.
     *
     * @return array{lecons_attendues:int, lecons_faites:int, lecons_taux:float}
     */
    private function lecons(ClasseMatiere $classeMatiere, Sequence $sequence): array
    {
        $aujourdhui = Carbon::today();

        $base = ResolveurFicheProgression::itemsScope($classeMatiere)
            ->lecons()
            ->where('sequence_id', $sequence->id)
            ->whereNotNull('date_prevue')
            ->whereDate('date_prevue', '<=', $aujourdhui);

        $attendues = (clone $base)->count();
        $faites = (clone $base)->whereNotNull('date_realisee')->count();

        return [
            'lecons_attendues' => $attendues,
            'lecons_faites' => $faites,
            'lecons_taux' => $attendues > 0 ? round($faites / $attendues * 100, 1) : 0.0,
        ];
    }
}
