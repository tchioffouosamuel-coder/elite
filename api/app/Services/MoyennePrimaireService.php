<?php

namespace App\Services;

use App\Models\Classe;
use App\Models\ClasseCompetence;
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
 * - l'unité évaluée est la COMPÉTENCE, pas la matière : « Langue et
 *   communication » porte le barème et les volets, la lecture et l'écriture ne
 *   sont que le contenu qu'elle recouvre ;
 * - chaque compétence est évaluée sur trois volets (oral, écrit, savoir-être),
 *   plus un volet pratique pour celles qui s'y prêtent ;
 * - le total d'une séquence est la SOMME des volets de cette séquence
 *   (`$tof1 = $m1_o + $m2_o + $m3_o [+ $m4_o]`) ;
 * - la note de la compétence pour le trimestre est la moyenne des totaux de
 *   séquence (`$tterm = ($tof1 + $tof2 + $tof3) / 3`) ;
 * - la compétence n'est pas notée sur 20 avec un coefficient mais sur un barème
 *   propre (`competences.notation`, `disciplines.cot` chez archange), et la
 *   moyenne générale ramène le total obtenu sur 20 :
 *   `$av = ($total * 20) / $sum` où `$sum = Σ barèmes`.
 *
 * Écart assumé avec archange : là où le legacy initialise toutes les notes à 0
 * en base et fait donc entrer chaque compétence au dénominateur dès le départ
 * (moyennes ininterprétables tant que la saisie n'est pas terminée), une
 * compétence sans aucune note saisie est ici exclue du numérateur ET du barème
 * total — même règle d'exemption que le secondaire.
 */
class MoyennePrimaireService extends BaseService
{
    public function __construct(private readonly MoyenneService $moyenneService) {}

    /**
     * Note d'une compétence pour le trimestre : moyenne des totaux de séquence,
     * chaque total étant la somme des volets évalués.
     *
     * @return array{note: ?float, bareme: int, totaux_sequences: array<int, ?float>, volets: array<string, array<int, ?float>>}
     */
    public function noteCompetenceEleve(Eleve $eleve, ClasseCompetence $classeCompetence, Trimestre $trimestre): array
    {
        $competence = $classeCompetence->competence;
        $composantes = $competence->voletsNotes();
        $sequences = $trimestre->sequencesRetenues();

        $notes = Note::where('eleve_id', $eleve->id)
            ->where('classe_competence_id', $classeCompetence->id)
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

        // Aucune note du tout : compétence exclue (pas de 0 imposé, cf. en-tête).
        if ($notes->isEmpty()) {
            return [
                'note' => null,
                'bareme' => (int) ($competence->notation ?? 20),
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
            'bareme' => (int) ($competence->notation ?? 20),
            'totaux_sequences' => $totauxSequences,
            'volets' => $volets,
        ];
    }

    /**
     * Moyenne générale du trimestre, ramenée sur 20 :
     * `(Σ notes compétences × 20) / Σ barèmes`.
     *
     * @return array{moyenne: ?float, total_obtenu: float, total_bareme: int}
     */
    public function moyenneGeneraleEleve(Eleve $eleve, Trimestre $trimestre): array
    {
        $affectations = $eleve->classe?->classeCompetences()
            ->where('statut', 'actif')->with('competence')->get() ?? collect();

        $totalObtenu = 0.0;
        $totalBareme = 0;

        foreach ($affectations as $classeCompetence) {
            $resultat = $this->noteCompetenceEleve($eleve, $classeCompetence, $trimestre);

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
     * Classement de la classe pour une compétence donnée, équivalent du
     * `classementMatiere` du secondaire (sert au rang affiché par compétence).
     *
     * @return Collection<int, array{eleve_id: int, moyenne: ?float, rang: ?int}>
     */
    public function classementCompetence(ClasseCompetence $classeCompetence, Trimestre $trimestre): Collection
    {
        $eleves = $classeCompetence->classe->eleves()->where('statut', 'actif')->get();

        $rows = $eleves->map(fn (Eleve $eleve) => [
            'eleve_id' => $eleve->id,
            'moyenne' => $this->noteCompetenceEleve($eleve, $classeCompetence, $trimestre)['note'],
        ]);

        return $this->moyenneService->classer($rows);
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
     * Classement général de la classe sur l'année, à partir des moyennes
     * annuelles — sert au conseil de classe de fin d'année.
     *
     * @return Collection<int, array{eleve: Eleve, moyenne: ?float, rang: ?int}>
     */
    public function classementAnnuel(Classe $classe, int $anneeScolaireId): Collection
    {
        $rows = $classe->eleves()->where('statut', 'actif')->get()->map(fn (Eleve $eleve) => [
            'eleve' => $eleve,
            'moyenne' => $this->moyenneAnnuelleEleve($eleve, $anneeScolaireId),
        ]);

        return $this->moyenneService->classer($rows);
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
     * Taux de remplissage des notes d'une compétence : rapport entre les notes
     * réellement saisies et celles attendues (volets × séquences × élèves).
     */
    public function tauxRemplissage(ClasseCompetence $classeCompetence, Trimestre $trimestre): float
    {
        $nbEleves = $classeCompetence->classe->eleves()->where('statut', 'actif')->count();
        $sequences = $trimestre->sequencesRetenues();
        $nbComposantes = count($classeCompetence->competence->voletsNotes());

        $attendu = $nbEleves * $sequences->count() * $nbComposantes;

        if ($attendu === 0) {
            return 0.0;
        }

        // Scopé aux élèves actuellement actifs, comme $nbEleves : sinon un
        // élève parti après avoir eu ses notes saisies gonfle le numérateur
        // sans plus compter au dénominateur, et le taux dépasse 100 %.
        $saisi = Note::where('classe_competence_id', $classeCompetence->id)
            ->whereIn('sequence_id', $sequences->pluck('id'))
            ->whereNotNull('valeur')
            ->whereHas('eleve', fn ($q) => $q->where('statut', 'actif'))
            ->count();

        return round($saisi / $attendu * 100, 1);
    }
}
