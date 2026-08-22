<?php

namespace App\Services;

use App\Models\AbsenceTrimestre;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\Eleve;
use App\Models\Note;
use App\Models\Sanction;
use App\Models\Trimestre;
use Illuminate\Support\Collection;

/**
 * Assemble les données d'un bulletin sur le modèle de report_cards_single.php
 * (_smapp) : le document couvre toute une classe d'un coup, chaque élève étant
 * une page. Les statistiques de profil de classe sont donc calculées une seule
 * fois et partagées par toutes les pages.
 */
class BulletinService extends BaseService
{
    private const NOTE_PASSAGE = 10.0;

    public function __construct(private readonly MoyenneService $moyennes) {}

    /**
     * @param  array<int>|null  $eleveIds  restreint le document à certains élèves (bulletin individuel)
     */
    public function donneesClasse(Classe $classe, Trimestre $trimestre, ?array $eleveIds = null): array
    {
        $affectations = $classe->classeMatieres()->where('statut', 'actif')
            ->with(['matiere', 'enseignant'])->orderBy('groupe')->orderBy('id')->get();

        $sequences = $trimestre->sequencesRetenues();
        $tousEleves = $classe->eleves()->where('statut', 'actif')->orderBy('nom_complet')->get();

        $classementGeneral = $this->moyennes->classementGeneral($classe, $trimestre);
        $classementsMatiere = $affectations->mapWithKeys(
            fn (ClasseMatiere $cm) => [$cm->id => $this->moyennes->classementMatiere($cm, $trimestre)]
        );

        $moyennesClasse = $classementGeneral->pluck('moyenne')->filter(fn ($m) => $m !== null);
        $stats = [
            'evalues' => $moyennesClasse->count(),
            'sup10' => $moyennesClasse->filter(fn ($m) => $m >= self::NOTE_PASSAGE)->count(),
            'premier' => $moyennesClasse->isNotEmpty() ? (float) $moyennesClasse->max() : null,
            'dernier' => $moyennesClasse->isNotEmpty() ? (float) $moyennesClasse->min() : null,
            'moyenne_classe' => $moyennesClasse->isNotEmpty() ? round((float) $moyennesClasse->avg(), 2) : null,
        ];
        $stats['pourcentage_reussite'] = $stats['evalues'] > 0
            ? round($stats['sup10'] / $stats['evalues'] * 100, 1)
            : 0.0;

        $elevesDuDocument = $eleveIds === null
            ? $tousEleves
            : $tousEleves->whereIn('id', $eleveIds)->values();

        $elevesDuDocument = $this->parOrdreDeMerite($elevesDuDocument, $classementGeneral);

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
            'stats' => $stats,
            'eleves' => $elevesDuDocument->map(fn (Eleve $eleve) => $this->donneesEleve(
                $eleve, $trimestre, $affectations, $sequences, $classementGeneral, $classementsMatiere
            ))->all(),
        ];
    }

    /**
     * Range les élèves par ordre de mérite : la liasse imprimée suit le
     * classement, du premier au dernier.
     *
     * Un paquet de bulletins se distribue en conseil de classe, où l'on part du
     * premier ; l'ordre alphabétique obligeait à retrier la pile à la main.
     *
     * Les élèves sans moyenne — aucune note saisie — n'ont pas de rang : ils
     * ferment la liasse par ordre alphabétique plutôt que de s'intercaler
     * arbitrairement parmi les classés.
     *
     * @param  Collection<int, Eleve>  $eleves
     * @param  Collection<int, array{eleve: Eleve, moyenne: ?float, rang: ?int}>  $classement
     * @return Collection<int, Eleve>
     */
    private function parOrdreDeMerite(Collection $eleves, Collection $classement): Collection
    {
        $rangs = $classement
            ->filter(fn (array $ligne) => $ligne['rang'] !== null)
            ->mapWithKeys(fn (array $ligne) => [$ligne['eleve']->id => $ligne['rang']]);

        /*
         * Une seule clé de tri, et non un tableau de closures : passé un
         * tableau, `sortBy()` traite chaque entrée comme un COMPARATEUR
         * `($a, $b) => int` et non comme un extracteur de clé — des closures à
         * un argument y produisent un ordre arbitraire.
         *
         * Le préfixe range les classés (0) avant les non classés (1) ; le rang
         * est complété à six chiffres pour que 10 suive 9 et non 1.
         */
        return $eleves
            ->sortBy(fn (Eleve $eleve) => $rangs->has($eleve->id)
                ? sprintf('0%06d', $rangs->get($eleve->id))
                : '1'.$eleve->nom_complet)
            ->values();
    }

    /**
     * @param  Collection<int, ClasseMatiere>  $affectations
     */
    private function donneesEleve(
        Eleve $eleve,
        Trimestre $trimestre,
        Collection $affectations,
        Collection $sequences,
        Collection $classementGeneral,
        Collection $classementsMatiere,
    ): array {
        $notes = Note::where('eleve_id', $eleve->id)
            ->whereIn('classe_matiere_id', $affectations->pluck('id'))
            ->whereIn('sequence_id', $sequences->pluck('id'))
            ->get()
            ->groupBy('classe_matiere_id');

        $matieresFaibles = [];

        $lignes = $affectations->map(function (ClasseMatiere $cm) use (
            $eleve, $trimestre, $sequences, $notes, $classementsMatiere, &$matieresFaibles
        ) {
            $notesMatiere = ($notes->get($cm->id) ?? collect())->keyBy('sequence_id');
            $moyenne = $this->moyennes->moyenneMatiereEleve($eleve, $cm, $trimestre);

            $classement = $classementsMatiere->get($cm->id) ?? collect();
            $valeurs = $classement->pluck('moyenne')->filter(fn ($v) => $v !== null);

            if ($moyenne !== null && $moyenne < self::NOTE_PASSAGE) {
                $matieresFaibles[] = $cm->matiere->abbreviation ?: $cm->matiere->nom;
            }

            return [
                'groupe' => (int) $cm->groupe,
                'matiere' => $cm->matiere->nom,
                'abreviation' => $cm->matiere->abbreviation,
                'enseignant' => $cm->enseignant?->nom_complet ?? '—',
                'competences' => array_values(array_filter(
                    array_map('trim', explode('|', (string) $cm->competences))
                )),
                'notes' => $sequences->map(
                    fn ($s) => $notesMatiere->get($s->id)?->valeur !== null
                        ? (float) $notesMatiere->get($s->id)->valeur
                        : null
                )->values()->all(),
                'moyenne' => $moyenne,
                'coefficient' => (float) $cm->coefficient,
                'total' => $moyenne !== null ? round($moyenne * (float) $cm->coefficient, 2) : null,
                'cote' => $this->moyennes->lettreCote($moyenne),
                'rang' => $classement->firstWhere('eleve_id', $eleve->id)['rang'] ?? null,
                'min' => $valeurs->isNotEmpty() ? (float) $valeurs->min() : null,
                'max' => $valeurs->isNotEmpty() ? (float) $valeurs->max() : null,
            ];
        });

        $general = $this->moyennes->moyenneGeneraleEleve($eleve, $trimestre);
        $absence = AbsenceTrimestre::where('eleve_id', $eleve->id)->where('trimestre_id', $trimestre->id)->first();
        $heuresNonJustifiees = (float) ($absence?->heures_non_justifiees ?? 0);

        return [
            'eleve' => $eleve,
            'groupes' => $lignes->groupBy('groupe')->map->values()->all(),
            'moyenne_generale' => $general['moyenne'],
            'total_points' => $general['total_points'],
            'total_coef' => $general['total_coef'],
            'rang' => $classementGeneral->first(fn ($r) => $r['eleve']->id === $eleve->id)['rang'] ?? null,
            'cote' => $this->moyennes->lettreCote($general['moyenne']),
            'appreciation' => $this->moyennes->appreciation($general['moyenne']),
            'mention_travail' => $this->moyennes->mentionTravail($eleve->school_id, $general['moyenne']),
            'mention_conduite' => $this->moyennes->mentionConduite($eleve->school_id, $heuresNonJustifiees),
            'conseil' => $this->conseil($matieresFaibles, $affectations->count(), $general['moyenne']),
            'heures_justifiees' => (float) ($absence?->heures_justifiees ?? 0),
            'heures_non_justifiees' => $heuresNonJustifiees,
            'sanctions' => Sanction::where('eleve_id', $eleve->id)->where('trimestre_id', $trimestre->id)->get(),
            'rappel' => $this->rappelMoyennes($eleve, $trimestre),
        ];
    }

    /**
     * Conseil de fin de bulletin, repris de getAdvice() dans _smapp : au-delà de
     * 70 % de matières sous la moyenne le détail n'a plus de sens, on renvoie un
     * constat global ; sinon on cite les trois matières les plus problématiques.
     */
    private function conseil(array $matieresFaibles, int $totalMatieres, ?float $moyenneGenerale): string
    {
        if ($matieresFaibles === []) {
            return $moyenneGenerale !== null && $moyenneGenerale >= self::NOTE_PASSAGE
                ? 'Excellent travail, continuez ainsi !'
                : "Un effort général s'impose";
        }

        if (count($matieresFaibles) > (int) ceil(0.7 * max(1, $totalMatieres))) {
            return 'Presque tout !!!';
        }

        return 'Effort requis en : '.implode(', ', array_slice($matieresFaibles, 0, 3));
    }

    /**
     * Tableau de rappel : moyenne et rang de l'élève pour chaque séquence et
     * chaque trimestre de l'année, tel que le bas du bulletin _smapp l'affiche.
     *
     * @return array<int, array{libelle: string, sequences: array<int, array{libelle: string, moyenne: ?float, rang: ?int}>, trimestre: array{moyenne: ?float, rang: ?int}}>
     */
    private function rappelMoyennes(Eleve $eleve, Trimestre $trimestreCourant): array
    {
        $trimestres = Trimestre::where('annee_scolaire_id', $trimestreCourant->annee_scolaire_id)
            ->with('sequences')->orderBy('ordre')->get();

        $classe = $eleve->classe;

        return $trimestres->map(function (Trimestre $trimestre) use ($eleve, $classe) {
            $classement = $this->moyennes->classementGeneral($classe, $trimestre);
            $ligne = $classement->first(fn ($r) => $r['eleve']->id === $eleve->id);

            return [
                'libelle' => $trimestre->libelle,
                'sequences' => $trimestre->sequencesRetenues()->map(fn ($sequence) => [
                    'libelle' => $sequence->libelle,
                    ...$this->moyenneSequence($eleve, $classe, $sequence->id),
                ])->values()->all(),
                'trimestre' => [
                    'moyenne' => $ligne['moyenne'] ?? null,
                    'rang' => $ligne['rang'] ?? null,
                ],
            ];
        })->all();
    }

    /**
     * Moyenne pondérée de l'élève sur une séquence isolée, et son rang dans la
     * classe pour cette même séquence.
     *
     * @return array{moyenne: ?float, rang: ?int}
     */
    private function moyenneSequence(Eleve $eleve, Classe $classe, int $sequenceId): array
    {
        $affectations = $classe->classeMatieres()->where('statut', 'actif')->get()->keyBy('id');

        $notesParEleve = Note::whereIn('classe_matiere_id', $affectations->keys())
            ->where('sequence_id', $sequenceId)
            ->whereNotNull('valeur')
            ->get()
            ->groupBy('eleve_id');

        $rows = $classe->eleves()->where('statut', 'actif')->pluck('id')->map(function (int $eleveId) use ($notesParEleve, $affectations) {
            $points = 0.0;
            $coefs = 0.0;

            foreach ($notesParEleve->get($eleveId) ?? [] as $note) {
                $coefficient = (float) $affectations[$note->classe_matiere_id]->coefficient;
                $points += (float) $note->valeur * $coefficient;
                $coefs += $coefficient;
            }

            return [
                'eleve_id' => $eleveId,
                'moyenne' => $coefs > 0 ? round($points / $coefs, 2) : null,
            ];
        });

        $ligne = $this->moyennes->classer($rows)->firstWhere('eleve_id', $eleve->id);

        return ['moyenne' => $ligne['moyenne'] ?? null, 'rang' => $ligne['rang'] ?? null];
    }
}
