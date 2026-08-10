<?php

namespace App\Services;

use App\Models\AbsenceTrimestre;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\Eleve;
use App\Models\Trimestre;
use Illuminate\Support\Collection;

/**
 * Assemble le bulletin du primaire et de la maternelle sur le modèle
 * d'archange (`fr/admin/term_reports.php`) : chaque matière occupe autant de
 * lignes qu'elle a de volets évalués, avec une colonne par séquence, puis une
 * note trimestrielle et une appréciation par compétence communes à la matière.
 */
class BulletinPrimaireService extends BaseService
{
    public function __construct(private readonly MoyennePrimaireService $moyennes) {}

    /**
     * @param  array<int>|null  $eleveIds  restreint le document à certains élèves
     */
    public function donneesClasse(Classe $classe, Trimestre $trimestre, ?array $eleveIds = null): array
    {
        $affectations = $classe->classeMatieres()->where('statut', 'actif')
            ->with(['matiere', 'enseignant'])->orderBy('id')->get();

        $sequences = $trimestre->sequences()->orderBy('ordre')->get();
        $tousEleves = $classe->eleves()->where('statut', 'actif')->orderBy('nom')->orderBy('prenom')->get();

        $classement = $this->moyennes->classementGeneral($classe, $trimestre);
        $moyennes = $classement->pluck('moyenne')->filter(fn ($m) => $m !== null);

        $elevesDuDocument = $eleveIds === null
            ? $tousEleves
            : $tousEleves->whereIn('id', $eleveIds)->values();

        return [
            'classe' => $classe,
            'school' => $classe->school,
            'trimestre' => $trimestre,
            'annee' => $trimestre->anneeScolaire,
            'sequences' => $sequences,
            'effectif' => [
                'total' => $tousEleves->count(),
                'garcons' => $tousEleves->where('sexe', 'M')->count(),
                'filles' => $tousEleves->where('sexe', 'F')->count(),
            ],
            'stats' => [
                'moyenne_classe' => $moyennes->isNotEmpty() ? round((float) $moyennes->avg(), 2) : null,
                'premier' => $moyennes->isNotEmpty() ? (float) $moyennes->max() : null,
                'dernier' => $moyennes->isNotEmpty() ? (float) $moyennes->min() : null,
                'admis' => $moyennes->filter(fn ($m) => $m >= 10)->count(),
                'evalues' => $moyennes->count(),
            ],
            'eleves' => $elevesDuDocument
                ->map(fn (Eleve $eleve) => $this->donneesEleve($eleve, $trimestre, $affectations, $sequences, $classement))
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, ClasseMatiere>  $affectations
     */
    private function donneesEleve(
        Eleve $eleve,
        Trimestre $trimestre,
        Collection $affectations,
        Collection $sequences,
        Collection $classement,
    ): array {
        $totauxParSequence = array_fill_keys($sequences->pluck('id')->all(), 0.0);

        $lignes = $affectations->map(function (ClasseMatiere $cm) use ($eleve, $trimestre, $sequences, &$totauxParSequence) {
            $resultat = $this->moyennes->noteMatiereEleve($eleve, $cm, $trimestre);

            foreach ($sequences as $sequence) {
                $totauxParSequence[$sequence->id] += $resultat['totaux_sequences'][$sequence->id] ?? 0.0;
            }

            return [
                'matiere' => $cm->matiere->nom,
                'matiere_en' => $cm->matiere->nom_en,
                'abreviation' => $cm->matiere->abbreviation,
                'bareme' => $resultat['bareme'],
                'enseignant' => $cm->enseignant?->nomComplet() ?? '—',
                // Une ligne par volet : le libellé, puis une note par séquence.
                'volets' => collect($cm->matiere->composantes())->map(fn (string $composante) => [
                    'code' => $composante,
                    'libelle' => self::LIBELLES_VOLETS[$composante]['fr'],
                    'libelle_en' => self::LIBELLES_VOLETS[$composante]['en'],
                    'notes' => $sequences->map(fn ($s) => $resultat['volets'][$composante][$s->id] ?? null)->values()->all(),
                ])->values()->all(),
                'totaux_sequences' => $sequences->map(fn ($s) => $resultat['totaux_sequences'][$s->id] ?? null)->values()->all(),
                'note' => $resultat['note'],
                'appreciation' => $this->moyennes->appreciationCompetence($resultat['note'], $resultat['bareme']),
            ];
        });

        $general = $this->moyennes->moyenneGeneraleEleve($eleve, $trimestre);
        $absence = AbsenceTrimestre::where('eleve_id', $eleve->id)->where('trimestre_id', $trimestre->id)->first();

        // Moyenne de chaque séquence prise isolément, ramenée sur 20 :
        // `$al1 = ($total1 * 20) / $sum` dans archange.
        $moyennesSequences = $sequences->map(fn ($s) => $general['total_bareme'] > 0
            ? round($totauxParSequence[$s->id] * 20 / $general['total_bareme'], 2)
            : null)->values()->all();

        return [
            'eleve' => $eleve,
            'lignes' => $lignes->all(),
            'moyennes_sequences' => $moyennesSequences,
            'total_obtenu' => $general['total_obtenu'],
            'total_bareme' => $general['total_bareme'],
            'moyenne_generale' => $general['moyenne'],
            'rang' => $classement->first(fn ($r) => $r['eleve']->id === $eleve->id)['rang'] ?? null,
            'appreciation_generale' => $this->appreciationGenerale($general['moyenne']),
            'heures_justifiees' => (float) ($absence?->heures_justifiees ?? 0),
            'heures_non_justifiees' => (float) ($absence?->heures_non_justifiees ?? 0),
        ];
    }

    private const LIBELLES_VOLETS = [
        'oral' => ['fr' => 'Oral', 'en' => 'Oral'],
        'ecrit' => ['fr' => 'Écrit', 'en' => 'Writing'],
        'savoir_etre' => ['fr' => 'Savoir-être', 'en' => 'Attitude'],
        'pratique' => ['fr' => 'Pratique', 'en' => 'Practical'],
    ];

    private function appreciationGenerale(?float $moyenne): string
    {
        return match (true) {
            $moyenne === null => '—',
            $moyenne >= 16 => 'Excellent',
            $moyenne >= 14 => 'Très bien',
            $moyenne >= 12 => 'Bien',
            $moyenne >= 10 => 'Assez bien',
            default => 'Insuffisant',
        };
    }
}
