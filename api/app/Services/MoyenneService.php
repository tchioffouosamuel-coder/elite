<?php

namespace App\Services;

use App\Models\AbsenceTrimestre;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\Eleve;
use App\Models\Note;
use App\Models\Setting;
use App\Models\Trimestre;
use Illuminate\Support\Collection;

/**
 * Réplique l'algorithme de _smapp (calcul_process.php) sur un schéma normalisé :
 * moyenne matière = moyenne simple des notes de séquence saisies ; moyenne
 * générale = Σ(moyenne matière × coefficient) / Σ(coefficient) ; rangs en
 * classement sportif (ex-aequo => même rang, rang suivant décalé du nombre
 * d'ex-aequo). Rien n'est mis en cache : tout est recalculé à la demande,
 * pour éviter la double source de vérité observée dans _smapp (job batch +
 * requête RANK() en direct, parfois incohérents).
 */
class MoyenneService extends BaseService
{
    public function moyenneMatiereEleve(Eleve $eleve, ClasseMatiere $classeMatiere, Trimestre $trimestre): ?float
    {
        $sequenceIds = $trimestre->sequencesRetenues()->pluck('id');

        $notes = Note::where('eleve_id', $eleve->id)
            ->where('classe_matiere_id', $classeMatiere->id)
            ->whereIn('sequence_id', $sequenceIds)
            ->whereNotNull('valeur')
            ->pluck('valeur');

        if ($notes->isEmpty()) {
            return null; // aucune note saisie : matière exclue pour cet élève (pas de 0 imposé)
        }

        $emptyCancel = Setting::get($eleve->school_id, 'empty_cancel', 'cancel') === 'cancel';

        if ($emptyCancel) {
            return round((float) $notes->avg(), 2);
        }

        $numSequences = (int) Setting::get($eleve->school_id, 'num_sequences', 2);

        return round($notes->sum(fn ($v) => (float) $v) / $numSequences, 2);
    }

    /**
     * @return array{moyenne: ?float, total_points: float, total_coef: float}
     */
    public function moyenneGeneraleEleve(Eleve $eleve, Trimestre $trimestre): array
    {
        $affectations = $eleve->classe?->classeMatieres()->where('statut', 'actif')->get() ?? collect();

        $totalPoints = 0.0;
        $totalCoef = 0.0;

        foreach ($affectations as $classeMatiere) {
            $moyenne = $this->moyenneMatiereEleve($eleve, $classeMatiere, $trimestre);

            if ($moyenne === null) {
                continue; // exemption par élève, comme les "classes spéciales" de _smapp
            }

            $totalPoints += $moyenne * (float) $classeMatiere->coefficient;
            $totalCoef += (float) $classeMatiere->coefficient;
        }

        return [
            'moyenne' => $totalCoef > 0 ? round($totalPoints / $totalCoef, 2) : null,
            'total_points' => round($totalPoints, 2),
            'total_coef' => $totalCoef,
        ];
    }

    /**
     * Classement général de la classe pour le trimestre, trié par moyenne
     * décroissante avec rangs ex-aequo (classement sportif).
     *
     * @return Collection<int, array{eleve: Eleve, moyenne: ?float, rang: ?int}>
     */
    public function classementGeneral(Classe $classe, Trimestre $trimestre): Collection
    {
        $rows = $classe->eleves()->where('statut', 'actif')->get()->map(function (Eleve $eleve) use ($trimestre) {
            return ['eleve' => $eleve, 'moyenne' => $this->moyenneGeneraleEleve($eleve, $trimestre)['moyenne']];
        });

        return $this->attribuerRangs($rows);
    }

    /**
     * Classement de la classe pour une matière donnée (sert au rang/min/max
     * affichés sur le bulletin, équivalent de RankOfNote/MinNote/MaxNote).
     *
     * @return Collection<int, array{eleve_id: int, moyenne: ?float, rang: ?int}>
     */
    public function classementMatiere(ClasseMatiere $classeMatiere, Trimestre $trimestre): Collection
    {
        $eleves = $classeMatiere->classe->eleves()->where('statut', 'actif')->get();

        $rows = $eleves->map(fn (Eleve $eleve) => [
            'eleve_id' => $eleve->id,
            'moyenne' => $this->moyenneMatiereEleve($eleve, $classeMatiere, $trimestre),
        ]);

        return $this->attribuerRangs($rows);
    }

    public function tauxRemplissage(ClasseMatiere $classeMatiere, Trimestre $trimestre): float
    {
        $nbEleves = $classeMatiere->classe->eleves()->where('statut', 'actif')->count();
        $sequenceIds = $trimestre->sequencesRetenues()->pluck('id');
        $nbSequences = $sequenceIds->count();

        if ($nbEleves === 0 || $nbSequences === 0) {
            return 0.0;
        }

        $attendu = $nbEleves * $nbSequences;
        $saisi = Note::where('classe_matiere_id', $classeMatiere->id)
            ->whereIn('sequence_id', $sequenceIds)
            ->whereNotNull('valeur')
            ->count();

        return round($saisi / $attendu * 100, 1);
    }

    /**
     * @return Collection<int, array{classe_matiere_id:int, matiere:string, enseignant: ?string, taux: float}>
     */
    public function remplissageClasse(Classe $classe, Trimestre $trimestre): Collection
    {
        return $classe->classeMatieres()->where('statut', 'actif')->with(['matiere', 'enseignant'])->get()
            ->map(fn (ClasseMatiere $cm) => [
                'classe_matiere_id' => $cm->id,
                'matiere' => $cm->matiere->nom,
                'enseignant' => $cm->enseignant?->nom_complet,
                'taux' => $this->tauxRemplissage($cm, $trimestre),
            ]);
    }

    /**
     * Élèves dont la moyenne générale du trimestre franchit le seuil
     * `honour_roll` ET dont les heures d'absence non justifiées restent
     * sous `honour_attendance_max` — double condition de _smapp
     * (honor_roll_single_fr.php), pas un simple "top N".
     *
     * @return Collection<int, array{eleve: Eleve, moyenne: float, heures_non_justifiees: float}>
     */
    public function palmares(int $schoolId, Trimestre $trimestre, ?int $classeId = null): Collection
    {
        $seuilMoyenne = (float) Setting::get($schoolId, 'honour_roll', 14);
        $seuilAbsences = (float) Setting::get($schoolId, 'honour_attendance_max', 20);

        $eleves = Eleve::forSchool($schoolId)->where('statut', 'actif')
            ->when($classeId, fn ($q, $id) => $q->where('classe_id', $id))
            ->with('classe')
            ->get();

        return $eleves
            ->map(function (Eleve $eleve) use ($trimestre) {
                $absence = AbsenceTrimestre::where('eleve_id', $eleve->id)->where('trimestre_id', $trimestre->id)->first();

                return [
                    'eleve' => $eleve,
                    'moyenne' => $this->moyenneGeneraleEleve($eleve, $trimestre)['moyenne'],
                    'heures_non_justifiees' => $absence ? (float) $absence->heures_non_justifiees : 0.0,
                ];
            })
            ->filter(fn ($row) => $row['moyenne'] !== null
                && $row['moyenne'] >= $seuilMoyenne
                && $row['heures_non_justifiees'] < $seuilAbsences)
            ->sortByDesc('moyenne')
            ->values();
    }

    public function lettreCote(?float $moyenne): string
    {
        return match (true) {
            $moyenne === null => '—',
            $moyenne >= 18 => 'A+',
            $moyenne >= 16 => 'A',
            $moyenne >= 15 => 'B+',
            $moyenne >= 14 => 'B',
            $moyenne >= 12 => 'C+',
            $moyenne >= 10 => 'C',
            default => 'D',
        };
    }

    public function appreciation(?float $moyenne): string
    {
        return match (true) {
            $moyenne === null => '—',
            $moyenne >= 16 => 'tres_bien',
            $moyenne >= 14 => 'bien',
            $moyenne >= 12 => 'assez_bien',
            $moyenne >= 10 => 'passable',
            default => 'insuffisant',
        };
    }

    /**
     * Mention "travail" (distinction/avertissement) selon les seuils
     * configurables de l'école — dans _smapp ces réglages existaient déjà
     * (`preferences.php`) mais n'étaient en réalité câblés nulle part sur
     * le bulletin. Ici ils pilotent réellement la mention affichée.
     */
    public function mentionTravail(int $schoolId, ?float $moyenne): ?string
    {
        if ($moyenne === null) {
            return null;
        }

        $felicitations = (float) Setting::get($schoolId, 'felicitations_min', 16);
        $encouragements = (float) Setting::get($schoolId, 'encouragements_min', 14);
        $avertMin = (float) Setting::get($schoolId, 'avertissement_travail_min', 8);
        $avertMax = (float) Setting::get($schoolId, 'avertissement_travail_max', 10);
        $blameMax = (float) Setting::get($schoolId, 'blame_travail_max', 8);

        return match (true) {
            $moyenne >= $felicitations => 'felicitations',
            $moyenne >= $encouragements => 'encouragements',
            $moyenne <= $blameMax => 'blame_travail',
            $moyenne >= $avertMin && $moyenne <= $avertMax => 'avertissement_travail',
            default => null,
        };
    }

    /** Mention "conduite" selon les heures d'absence non justifiées cumulées du trimestre. */
    public function mentionConduite(int $schoolId, float $heuresNonJustifiees): ?string
    {
        $blameMin = (float) Setting::get($schoolId, 'blame_conduite_min', 20);
        $avertMin = (float) Setting::get($schoolId, 'avertissement_conduite_min', 10);
        $avertMax = (float) Setting::get($schoolId, 'avertissement_conduite_max', 20);

        return match (true) {
            $heuresNonJustifiees >= $blameMin => 'blame_conduite',
            $heuresNonJustifiees >= $avertMin && $heuresNonJustifiees < $avertMax => 'avertissement_conduite',
            default => null,
        };
    }

    /**
     * Classement sportif : ex-aequo partagent le même rang, le suivant
     * reprend à la position 1-indexée (1, 2, 2, 4, ...). $rows doit contenir
     * une clé 'moyenne' ; les valeurs null sont laissées sans rang, en fin
     * de liste.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    public function classer(Collection $rows): Collection
    {
        return $this->attribuerRangs($rows);
    }

    private function attribuerRangs(Collection $rows): Collection
    {
        $classes = $rows->partition(fn ($row) => $row['moyenne'] !== null);
        $ranked = $classes[0]->sortByDesc('moyenne')->values();
        $unranked = $classes[1]->map(fn ($row) => [...$row, 'rang' => null]);

        $result = $ranked->values()->map(function ($row, $i) use ($ranked) {
            $previous = $i > 0 ? $ranked[$i - 1] : null;
            $row['rang'] = ($previous && $previous['moyenne'] == $row['moyenne']) ? null : $i + 1;

            return $row;
        });

        // Deuxième passe : propager le rang des ex-aequo (celui du premier de la série).
        $lastRang = null;
        $result = $result->map(function ($row) use (&$lastRang) {
            $row['rang'] = $row['rang'] ?? $lastRang;
            $lastRang = $row['rang'];

            return $row;
        });

        return $result->concat($unranked);
    }
}
