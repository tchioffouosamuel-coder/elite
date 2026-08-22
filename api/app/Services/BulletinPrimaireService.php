<?php

namespace App\Services;

use App\Models\Appreciation;
use App\Models\Classe;
use App\Models\ClasseCompetence;
use App\Models\Eleve;
use App\Models\Note;
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
 *
 * La maternelle suit un autre document : pas de note, pas de moyenne, pas de
 * rang. Chaque volet y porte un niveau d'appréciation, et la case de la colonne
 * atteinte se colore. `mode` distingue les deux rendus.
 */
class BulletinPrimaireService extends BaseService
{
    public function __construct(
        private readonly MoyennePrimaireService $moyennes,
        private readonly DisciplineService $discipline,
        private readonly AppreciationService $appreciations,
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

        // La maternelle n'a ni moyenne, ni rang, ni classement : on ne les
        // calcule même pas, plutôt que de les produire pour ne pas les afficher.
        $parAppreciation = (bool) $classe->school?->estMaternelle();

        $classement = $parAppreciation ? collect() : $this->moyennes->classementGeneral($classe, $trimestre);
        $moyennes = $classement->pluck('moyenne')->filter(fn($m) => $m !== null);

        // Au primaire l'absence se compte en journées déduites des appels :
        // calculées ici en un bloc pour toute la classe, plutôt qu'une requête
        // par élève comme le faisait la lecture d'AbsenceTrimestre.
        $jours = $this->discipline->joursAbsence($classe, $trimestre);

        $elevesDuDocument = $eleveIds === null
            ? $tousEleves
            : $tousEleves->whereIn('id', $eleveIds)->values();

        return [
            'mode' => $parAppreciation ? 'appreciation' : 'note',
            // Colonnes du bulletin de maternelle, dans l'ordre du référentiel.
            'appreciations' => $parAppreciation
                ? $this->appreciations->referentiel((int) $classe->school_id)
                : collect(),
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
                ->map(fn(Eleve $eleve) => $parAppreciation
                    ? $this->donneesEleveMaternelle($eleve, $affectations, $sequences, $jours)
                    : $this->donneesEleve($eleve, $trimestre, $affectations, $sequences, $classement, $jours))
                ->all(),
        ];
    }

    /**
     * Bulletin de maternelle : une ligne par volet, portant le niveau
     * d'appréciation atteint plutôt qu'une note.
     *
     * Un trimestre compte plusieurs séquences alors que le document n'offre
     * qu'un jeu de colonnes : on retient l'appréciation de la DERNIÈRE séquence
     * renseignée. L'acquisition est une trajectoire — ce que le livret
     * communique à la famille, c'est où l'enfant en est à la fin du trimestre,
     * pas une moyenne de ses étapes.
     *
     * @param  Collection<int, ClasseCompetence>  $affectations
     */
    private function donneesEleveMaternelle(
        Eleve $eleve,
        Collection $affectations,
        Collection $sequences,
        Collection $jours,
    ): array {
        $notes = Note::where('eleve_id', $eleve->id)
            ->whereIn('classe_competence_id', $affectations->pluck('id'))
            ->whereIn('sequence_id', $sequences->pluck('id'))
            ->whereNotNull('appreciation_id')
            ->with('appreciation')
            ->get();

        // Rang de chaque séquence, par identifiant : c'est lui qui dit laquelle
        // est la dernière. `flip()` sur la collection de modèles ne donnerait
        // rien d'exploitable — il faut passer par les identifiants.
        $rangSequence = $sequences->values()->pluck('id')->flip();

        $lignes = $affectations->map(function (ClasseCompetence $cc) use ($notes, $rangSequence) {
            $competence = $cc->competence;

            return [
                'matiere' => $competence->label_fr,
                'matiere_en' => $competence->label_en,
                'abreviation' => $competence->abbreviation,
                'enseignant' => $cc->enseignant?->nom_complet ?? '—',
                'volets' => collect($competence->volets())->map(function (string $volet) use ($notes, $cc, $rangSequence) {
                    $retenue = $notes
                        ->where('classe_competence_id', $cc->id)
                        ->where('composante', $volet)
                        ->sortByDesc(fn(Note $note) => $rangSequence[$note->sequence_id] ?? -1)
                        ->first();

                    return [
                        'code' => $volet,
                        'libelle' => self::LIBELLES_VOLETS[$volet]['fr'],
                        'libelle_en' => self::LIBELLES_VOLETS[$volet]['en'],
                        'appreciation' => $retenue?->appreciation ? [
                            'id' => $retenue->appreciation->id,
                            'label_fr' => $retenue->appreciation->label_fr,
                            'label_en' => $retenue->appreciation->label_en,
                            'emoji' => $retenue->appreciation->emoji,
                            'couleur' => $retenue->appreciation->couleur,
                        ] : null,
                    ];
                })->values()->all(),
            ];
        });

        return [
            'eleve' => $eleve,
            'lignes' => $lignes->all(),
            'jours_justifies' => (int) ($jours[$eleve->id]['jours_justifies'] ?? 0),
            'jours_non_justifies' => (int) ($jours[$eleve->id]['jours_non_justifies'] ?? 0),
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
