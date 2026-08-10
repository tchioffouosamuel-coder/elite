<?php

namespace App\Services;

use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\Eleve;
use App\Models\Note;
use App\Models\Setting;
use App\Models\Trimestre;
use Illuminate\Support\Collection;

/**
 * Moteur de notation du primaire et de la maternelle, porté du projet archange
 * (`fr/admin/term_reports.php`, `fr/admin/calculate_marks.php`).
 *
 * Il diffère fondamentalement de celui du secondaire ({@see MoyenneService}) :
 *
 * - chaque matière est évaluée sur trois volets (oral, écrit, savoir-être),
 *   plus un volet pratique pour les matières concernées ;
 * - le total d'une séquence est la SOMME des volets de cette séquence
 *   (`$tof1 = $m1_o + $m2_o + $m3_o [+ $m4_o]`) ;
 * - la note de la matière pour le trimestre est la moyenne des totaux de
 *   séquence (`$tterm = ($tof1 + $tof2 + $tof3) / 3`) ;
 * - la matière n'est pas notée sur 20 avec un coefficient mais sur un barème
 *   propre (`matieres.notation`, `disciplines.cot` chez archange), et la
 *   moyenne générale ramène le total obtenu sur 20 :
 *   `$av = ($total * 20) / $sum` où `$sum = Σ barèmes`.
 *
 * Écart assumé avec archange : là où le legacy initialise toutes les notes à 0
 * en base et fait donc entrer chaque matière au dénominateur dès le départ
 * (moyennes ininterprétables tant que la saisie n'est pas terminée), une
 * matière sans aucune note saisie est ici exclue du numérateur ET du barème
 * total — même règle d'exemption que le secondaire.
 */
class MoyennePrimaireService extends BaseService
{
    public function __construct(private readonly MoyenneService $moyenneService) {}

    /**
     * Note d'une matière pour le trimestre : moyenne des totaux de séquence,
     * chaque total étant la somme des volets évalués.
     *
     * @return array{note: ?float, bareme: int, totaux_sequences: array<int, ?float>, volets: array<string, array<int, ?float>>}
     */
    public function noteMatiereEleve(Eleve $eleve, ClasseMatiere $classeMatiere, Trimestre $trimestre): array
    {
        $matiere = $classeMatiere->matiere;
        $composantes = $matiere->composantes();
        $sequences = $trimestre->sequences()->orderBy('ordre')->get();

        $notes = Note::where('eleve_id', $eleve->id)
            ->where('classe_matiere_id', $classeMatiere->id)
            ->whereIn('sequence_id', $sequences->pluck('id'))
            ->whereNotNull('valeur')
            ->get();

        $volets = [];
        foreach ($composantes as $composante) {
            foreach ($sequences as $sequence) {
                $volets[$composante][$sequence->id] = $notes
                    ->firstWhere(fn (Note $n) => $n->composante === $composante && $n->sequence_id === $sequence->id)
                    ?->valeur;
                $volets[$composante][$sequence->id] = $volets[$composante][$sequence->id] !== null
                    ? (float) $volets[$composante][$sequence->id]
                    : null;
            }
        }

        // Aucune note du tout : matière exclue (pas de 0 imposé, cf. en-tête).
        if ($notes->isEmpty()) {
            return [
                'note' => null,
                'bareme' => (int) ($matiere->notation ?? 20),
                'totaux_sequences' => $sequences->mapWithKeys(fn ($s) => [$s->id => null])->all(),
                'volets' => $volets,
            ];
        }

        $totauxSequences = [];
        foreach ($sequences as $sequence) {
            $total = 0.0;
            foreach ($composantes as $composante) {
                $total += $volets[$composante][$sequence->id] ?? 0.0;
            }
            $totauxSequences[$sequence->id] = round($total, 2);
        }

        $nbSequences = max($sequences->count(), 1);

        return [
            'note' => round(array_sum($totauxSequences) / $nbSequences, 2),
            'bareme' => (int) ($matiere->notation ?? 20),
            'totaux_sequences' => $totauxSequences,
            'volets' => $volets,
        ];
    }

    /**
     * Moyenne générale du trimestre, ramenée sur 20 :
     * `(Σ notes matières × 20) / Σ barèmes`.
     *
     * @return array{moyenne: ?float, total_obtenu: float, total_bareme: int}
     */
    public function moyenneGeneraleEleve(Eleve $eleve, Trimestre $trimestre): array
    {
        $affectations = $eleve->classe?->classeMatieres()->where('statut', 'actif')->with('matiere')->get() ?? collect();

        $totalObtenu = 0.0;
        $totalBareme = 0;

        foreach ($affectations as $classeMatiere) {
            $resultat = $this->noteMatiereEleve($eleve, $classeMatiere, $trimestre);

            if ($resultat['note'] === null) {
                continue;
            }

            $totalObtenu += $resultat['note'];
            $totalBareme += $resultat['bareme'];
        }

        return [
            'moyenne' => $totalBareme > 0 ? round($totalObtenu * 20 / $totalBareme, 2) : null,
            'total_obtenu' => round($totalObtenu, 2),
            'total_bareme' => $totalBareme,
        ];
    }

    /**
     * Moyenne annuelle : moyenne des moyennes trimestrielles renseignées
     * (`$av4 = (av1 + av2 + av3) / 3` chez archange, mais sans compter les
     * trimestres encore vides comme des zéros).
     */
    public function moyenneAnnuelleEleve(Eleve $eleve, int $anneeScolaireId): ?float
    {
        $trimestres = Trimestre::where('annee_scolaire_id', $anneeScolaireId)->orderBy('ordre')->get();

        $moyennes = $trimestres
            ->map(fn (Trimestre $t) => $this->moyenneGeneraleEleve($eleve, $t)['moyenne'])
            ->filter(fn ($m) => $m !== null);

        return $moyennes->isEmpty() ? null : round($moyennes->avg(), 2);
    }

    /**
     * Appréciation par compétence, calculée sur le pourcentage du barème
     * atteint — logique d'archange (`$perc = ($tterm / $no) * 100`), dont les
     * conditions se chevauchaient dans le legacy et sont ici remises à plat.
     *
     * NA = Non Acquis, ECA = En Cours d'Acquisition, A = Acquis, A+ = Expert.
     */
    public function appreciationCompetence(?float $note, int $bareme): string
    {
        if ($note === null || $bareme <= 0) {
            return '—';
        }

        $pourcentage = $note / $bareme * 100;

        return match (true) {
            $pourcentage >= 80 => 'A+',
            $pourcentage >= 60 => 'A',
            $pourcentage >= 50 => 'ECA',
            default => 'NA',
        };
    }

    /**
     * Classement de la classe pour le trimestre, rangs ex-aequo en classement
     * sportif (règle partagée avec le secondaire).
     *
     * @return Collection<int, array{eleve: Eleve, moyenne: ?float, rang: ?int}>
     */
    public function classementGeneral(Classe $classe, Trimestre $trimestre): Collection
    {
        $rows = $classe->eleves()->where('statut', 'actif')->get()->map(fn (Eleve $eleve) => [
            'eleve' => $eleve,
            'moyenne' => $this->moyenneGeneraleEleve($eleve, $trimestre)['moyenne'],
        ]);

        return $this->moyenneService->classer($rows);
    }

    /**
     * Décision de fin d'année : passage en classe supérieure si la moyenne
     * annuelle atteint le seuil configuré, redoublement sinon
     * (`decision.php` : `av4 > minav` → promu, sinon statut 'R').
     *
     * @return Collection<int, array{eleve: Eleve, moyenne_annuelle: ?float, decision: string}>
     */
    public function decisionsAnnuelles(Classe $classe, int $anneeScolaireId): Collection
    {
        $seuil = (float) Setting::get($classe->school_id, 'passage_moyenne_min', 10);

        return $classe->eleves()->where('statut', 'actif')->get()->map(function (Eleve $eleve) use ($anneeScolaireId, $seuil) {
            $moyenne = $this->moyenneAnnuelleEleve($eleve, $anneeScolaireId);

            return [
                'eleve' => $eleve,
                'moyenne_annuelle' => $moyenne,
                'decision' => match (true) {
                    $moyenne === null => 'en_attente',
                    $moyenne >= $seuil => 'admis',
                    default => 'redouble',
                },
            ];
        });
    }

    /**
     * Taux de remplissage des notes d'une matière : rapport entre les notes
     * réellement saisies et celles attendues (volets × séquences × élèves).
     */
    public function tauxRemplissage(ClasseMatiere $classeMatiere, Trimestre $trimestre): float
    {
        $nbEleves = $classeMatiere->classe->eleves()->where('statut', 'actif')->count();
        $nbSequences = $trimestre->sequences()->count();
        $nbComposantes = count($classeMatiere->matiere->composantes());

        $attendu = $nbEleves * $nbSequences * $nbComposantes;

        if ($attendu === 0) {
            return 0.0;
        }

        $saisi = Note::where('classe_matiere_id', $classeMatiere->id)
            ->whereIn('sequence_id', $trimestre->sequences()->pluck('id'))
            ->whereNotNull('valeur')
            ->count();

        return round($saisi / $attendu * 100, 1);
    }
}
