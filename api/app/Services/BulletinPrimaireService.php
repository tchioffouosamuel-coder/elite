<?php

namespace App\Services;

use App\Models\Classe;
use App\Models\ClasseCompetence;
use App\Models\Eleve;
use App\Models\Trimestre;
use Illuminate\Support\Collection;

/**
 * Assemble le bulletin du primaire et de la maternelle sur le modèle
 * d'archange (`fr/admin/term_reports.php`) : chaque COMPÉTENCE occupe autant de
 * lignes qu'elle a de volets évalués, avec une colonne par séquence, puis une
 * note trimestrielle et une appréciation.
 *
 * Le bulletin ne montre pas les matières. Elles décrivent le contenu enseigné
 * et servent à l'emploi du temps ou à la progression, mais le livret que reçoit
 * la famille raisonne en compétences — c'est l'unité que l'école évalue.
 */
class BulletinPrimaireService extends BaseService
{
    public function __construct(
        private readonly MoyennePrimaireService $moyennes,
        private readonly DisciplineService $discipline,
    ) {}

    /**
     * @param  array<int>|null  $eleveIds  restreint le document à certains élèves
     */
    public function donneesClasse(Classe $classe, Trimestre $trimestre, ?array $eleveIds = null): array
    {
        // `statut` qualifié : la jointure sur `competences` en apporte un second.
        $affectations = $classe->classeCompetences()->where('classe_competences.statut', 'actif')
            ->with(['competence', 'enseignant'])
            ->join('competences', 'competences.id', '=', 'classe_competences.competence_id')
            ->orderBy('competences.ordre')
            ->orderBy('competences.label_fr')
            ->select('classe_competences.*')
            ->get();

        $sequences = $trimestre->sequencesRetenues();
        $tousEleves = $classe->eleves()->where('statut', 'actif')->orderBy('nom_complet')->get();

        $classement = $this->moyennes->classementGeneral($classe, $trimestre);
        $moyennes = $classement->pluck('moyenne')->filter(fn($m) => $m !== null);

        // Au primaire l'absence se compte en journées déduites des appels :
        // calculées ici en un bloc pour toute la classe, plutôt qu'une requête
        // par élève comme le faisait la lecture d'AbsenceTrimestre.
        $jours = $this->discipline->joursAbsence($classe, $trimestre);

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
                'admis' => $moyennes->filter(fn($m) => $m >= 10)->count(),
                'evalues' => $moyennes->count(),
            ],
            'eleves' => $elevesDuDocument
                ->map(fn(Eleve $eleve) => $this->donneesEleve($eleve, $trimestre, $affectations, $sequences, $classement, $jours))
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, ClasseCompetence>  $affectations
     */
    private function donneesEleve(
        Eleve $eleve,
        Trimestre $trimestre,
        Collection $affectations,
        Collection $sequences,
        Collection $classement,
        Collection $jours,
    ): array {
        $totauxParSequence = array_fill_keys($sequences->pluck('id')->all(), 0.0);

        $lignes = $affectations->map(function (ClasseCompetence $cc) use ($eleve, $trimestre, $sequences, &$totauxParSequence) {
            $resultat = $this->moyennes->noteCompetenceEleve($eleve, $cc, $trimestre);
            $competence = $cc->competence;
            $repartition = $competence->repartitionVolets();

            foreach ($sequences as $sequence) {
                $totauxParSequence[$sequence->id] += $resultat['totaux_sequences'][$sequence->id] ?? 0.0;
            }

            return [
                // Les clés gardent leur nom : les gabarits PDF et le front les
                // lisent déjà ainsi, et ce qu'elles désignent — la ligne notée
                // du bulletin — n'a pas changé de rôle, seulement de nature.
                'matiere' => $competence->label_fr,
                'matiere_en' => $competence->label_en,
                'abreviation' => $competence->abbreviation,
                'bareme' => $resultat['bareme'],
                'enseignant' => $cc->enseignant?->nom_complet ?? '—',
                // Une ligne par volet : le libellé, son barème, puis une note par séquence.
                'volets' => collect($competence->volets())->map(fn(string $composante) => [
                    'code' => $composante,
                    'libelle' => self::LIBELLES_VOLETS[$composante]['fr'],
                    'libelle_en' => self::LIBELLES_VOLETS[$composante]['en'],
                    'bareme' => $repartition[$composante] ?? null,
                    'notes' => $sequences->map(fn($s) => $resultat['volets'][$composante][$s->id] ?? null)->values()->all(),
                ])->values()->all(),
                'totaux_sequences' => $sequences->map(fn($s) => $resultat['totaux_sequences'][$s->id] ?? null)->values()->all(),
                'note' => $resultat['note'],
                'appreciation' => $this->moyennes->appreciationCompetence($resultat['note'], $resultat['bareme']),
            ];
        });

        $general = $this->moyennes->moyenneGeneraleEleve($eleve, $trimestre);

        // Moyenne de chaque séquence prise isolément, ramenée sur 20 :
        // `$al1 = ($total1 * 20) / $sum` dans archange.
        $moyennesSequences = $sequences->map(fn($s) => $general['total_bareme'] > 0
            ? round($totauxParSequence[$s->id] * 20 / $general['total_bareme'], 2)
            : null)->values()->all();

        return [
            'eleve' => $eleve,
            'lignes' => $lignes->all(),
            'moyennes_sequences' => $moyennesSequences,
            'total_obtenu' => $general['total_obtenu'],
            'total_bareme' => $general['total_bareme'],
            'moyenne_generale' => $general['moyenne'],
            'rang' => $classement->first(fn($r) => $r['eleve']->id === $eleve->id)['rang'] ?? null,
            'appreciation_generale' => $this->appreciationGenerale($general['moyenne']),
            'jours_justifies' => (int) ($jours[$eleve->id]['jours_justifies'] ?? 0),
            'jours_non_justifies' => (int) ($jours[$eleve->id]['jours_non_justifies'] ?? 0),
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
