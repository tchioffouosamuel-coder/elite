<?php

namespace App\Services;

use App\Models\AbsenceTrimestre;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Presence;
use App\Models\Seance;
use App\Models\Trimestre;
use App\Models\User;
use Illuminate\Support\Collection;

class DisciplineService extends BaseService
{
    /** Pointages valant présence : un élève en retard s'est bien présenté. */
    private const STATUTS_PRESENCE = ['present', 'retard'];

    /** Pointages valant absence au cours. */
    private const STATUTS_ABSENCE = ['absent', 'renvoye'];

    public function __construct(private readonly EmploiDuTempsService $emploiDuTemps) {}

    /**
     * Grille des absences de la classe pour le trimestre.
     *
     * Le primaire et la maternelle comptent des journées, déduites des
     * appels — {@see joursAbsence()}. Le secondaire compte des heures,
     * déduites des mêmes appels par durée réelle de séance
     * ({@see \App\Services\EmploiDuTempsService::cumulAbsences()}) : un
     * élève absent à un cours de 50 minutes voit ses heures non justifiées
     * augmenter de 50 minutes, quel que soit le motif — seul un motif
     * reconnu (hors « inconnu ») saisi à l'appel la justifie déjà à ce
     * moment-là (cf. `EmploiDuTempsService::enregistrerAppel()`).
     *
     * Le Surveillant Général garde la main pour corriger un élève en
     * particulier (erreur d'appel, cas rattrapé a posteriori) : dès qu'une
     * ligne `AbsenceTrimestre` existe pour lui sur ce trimestre, elle prime
     * sur le calcul et le remplace intégralement — {@see sauvegarderEnLot()}.
     *
     * @return Collection<int, array{eleve_id:int, nom_complet:string, unite:string, justifiees:float, non_justifiees:float, calculee:bool}>
     */
    public function grille(Classe $classe, Trimestre $trimestre): Collection
    {
        $eleves = $classe->eleves()->where('statut', 'actif')->orderBy('nom_complet')->get();

        if (! $classe->school->estSecondaire()) {
            $jours = $this->joursAbsence($classe, $trimestre);

            return $eleves->map(fn (Eleve $eleve) => [
                'eleve_id' => $eleve->id,
                'nom_complet' => $eleve->nom_complet,
                'unite' => 'jours',
                'justifiees' => (float) ($jours[$eleve->id]['jours_justifies'] ?? 0),
                'non_justifiees' => (float) ($jours[$eleve->id]['jours_non_justifies'] ?? 0),
                'calculee' => true,
            ]);
        }

        $calculees = $this->emploiDuTemps->cumulAbsences($classe, $trimestre)->keyBy('eleve_id');

        // Une correction manuelle du Surveillant Général (ligne existante,
        // forcément passée par sauvegarderEnLot()) prime sur le calcul.
        $corrections = AbsenceTrimestre::where('trimestre_id', $trimestre->id)
            ->whereIn('eleve_id', $eleves->pluck('id'))
            ->get()->keyBy('eleve_id');

        return $eleves->map(function (Eleve $eleve) use ($calculees, $corrections) {
            if ($correction = $corrections->get($eleve->id)) {
                return [
                    'eleve_id' => $eleve->id,
                    'nom_complet' => $eleve->nom_complet,
                    'unite' => 'heures',
                    'justifiees' => (float) $correction->heures_justifiees,
                    'non_justifiees' => (float) $correction->heures_non_justifiees,
                    'calculee' => false,
                ];
            }

            $calcul = $calculees->get($eleve->id);

            return [
                'eleve_id' => $eleve->id,
                'nom_complet' => $eleve->nom_complet,
                'unite' => 'heures',
                'justifiees' => (float) ($calcul['heures_justifiees'] ?? 0),
                'non_justifiees' => (float) ($calcul['heures_non_justifiees'] ?? 0),
                'calculee' => true,
            ];
        });
    }

    /**
     * Journées d'absence par élève sur le trimestre, déduites des appels.
     *
     * Une journée est absente si l'élève n'a répondu présent à aucun des
     * cours de ce jour. Elle bascule en non justifiée dès qu'une seule des
     * absences de la journée ne l'est pas : au primaire le justificatif
     * couvre la journée entière, un seul cours non couvert la découvre donc.
     *
     * Une journée sans aucun pointage exploitable pour l'élève n'est pas
     * comptée : un enfant inscrit en cours de trimestre n'a pas à hériter
     * des journées antérieures à son arrivée.
     *
     * @return Collection<int, array{jours_justifies:int, jours_non_justifies:int}> indexée par eleve_id
     */
    public function joursAbsence(Classe $classe, Trimestre $trimestre): Collection
    {
        $seances = Seance::where('classe_id', $classe->id)
            ->where('trimestre_id', $trimestre->id)
            ->where('statut', 'effectuee')
            ->with('presences')
            ->get();

        $cumuls = [];

        // La règle porte sur la journée entière : on regroupe donc les
        // pointages par date avant de trancher, élève par élève.
        foreach ($seances->groupBy(fn (Seance $s) => $s->date_seance->toDateString()) as $seancesDuJour) {
            $parEleve = $seancesDuJour->flatMap(fn (Seance $s) => $s->presences)->groupBy('eleve_id');

            foreach ($parEleve as $eleveId => $pointages) {
                if ($pointages->whereIn('statut', self::STATUTS_PRESENCE)->isNotEmpty()) {
                    continue;
                }

                $absences = $pointages->whereIn('statut', self::STATUTS_ABSENCE);
                if ($absences->isEmpty()) {
                    continue;
                }

                $cumuls[$eleveId] ??= ['jours_justifies' => 0, 'jours_non_justifies' => 0];
                $cle = $absences->every(fn (Presence $p) => $p->justifie) ? 'jours_justifies' : 'jours_non_justifies';
                $cumuls[$eleveId][$cle]++;
            }
        }

        return collect($cumuls);
    }

    /**
     * Assiduité d'un élève, journée par journée, sur une année scolaire —
     * même convention que {@see joursAbsence()} : présent dès qu'il a
     * répondu présent à au moins un cours de la journée, sinon absent
     * (justifiée dès que la totalité des absences du jour le sont). Une
     * journée sans aucun pointage pour lui n'apparaît pas — l'élève peut
     * être arrivé en cours d'année, ou l'appel n'a pas encore eu lieu.
     *
     * Porte sur ses pointages quelle que soit sa classe au moment du cours :
     * un transfert ou un redoublement en cours d'année ne doit pas faire
     * disparaître l'historique déjà enregistré.
     *
     * @return Collection<int, array{date:string, statut:string}> triée par date croissante
     */
    public function assiduiteEleve(Eleve $eleve, AnneeScolaire $anneeScolaire): Collection
    {
        $presences = Presence::where('eleve_id', $eleve->id)
            ->whereHas('seance', fn ($q) => $q->where('statut', 'effectuee')
                ->whereHas('trimestre', fn ($q2) => $q2->where('annee_scolaire_id', $anneeScolaire->id)))
            ->with('seance:id,date_seance')
            ->get();

        $jours = [];
        foreach ($presences->groupBy(fn (Presence $p) => $p->seance->date_seance->toDateString()) as $date => $pointages) {
            if ($pointages->whereIn('statut', self::STATUTS_PRESENCE)->isNotEmpty()) {
                $jours[$date] = 'present';
                continue;
            }

            $absences = $pointages->whereIn('statut', self::STATUTS_ABSENCE);
            if ($absences->isEmpty()) {
                continue;
            }

            $jours[$date] = $absences->every(fn (Presence $p) => $p->justifie) ? 'absent_justifiee' : 'absent_non_justifiee';
        }

        ksort($jours);

        return collect($jours)->map(fn ($statut, $date) => ['date' => $date, 'statut' => $statut])->values();
    }

    /**
     * Corrige à la main les heures d'un ou plusieurs élèves — la ligne créée
     * remplace alors intégralement le calcul automatique pour cet élève sur
     * ce trimestre (cf. `grille()`). Un élève absent de `$absences` garde son
     * calcul automatique inchangé : seuls les élèves explicitement soumis
     * basculent en correction manuelle.
     *
     * @param  array<int, array{eleve_id:int, heures_justifiees?: ?float, heures_non_justifiees?: ?float}>  $absences
     */
    public function sauvegarderEnLot(Classe $classe, Trimestre $trimestre, array $absences, ?User $user): int
    {
        // Au primaire et en maternelle les journées se déduisent des appels :
        // accepter une saisie ici créerait un compteur d'heures que plus
        // personne n'affiche, en laissant croire qu'il fait autorité.
        if (! $classe->school->estSecondaire()) {
            return 0;
        }

        $personnelId = $user?->personnel?->id;
        $eleveIdsValides = $classe->eleves()->pluck('id')->flip();

        return $this->transaction(function () use ($trimestre, $absences, $personnelId, $eleveIdsValides) {
            $count = 0;
            foreach ($absences as $row) {
                if (! $eleveIdsValides->has($row['eleve_id'])) {
                    continue;
                }

                AbsenceTrimestre::updateOrCreate(
                    ['eleve_id' => $row['eleve_id'], 'trimestre_id' => $trimestre->id],
                    [
                        'heures_justifiees' => $row['heures_justifiees'] ?? 0,
                        'heures_non_justifiees' => $row['heures_non_justifiees'] ?? 0,
                        'mis_a_jour_par' => $personnelId,
                    ]
                );
                $count++;
            }

            return $count;
        });
    }

    /**
     * Détail par élève (justifiées + non justifiées) pour l'export PDF —
     * bilanClasse() n'expose que les agrégats.
     *
     * @return Collection<int, array{eleve: Eleve, hj: float, hnj: float}>
     */
    public function lignesDetail(Classe $classe, Trimestre $trimestre): Collection
    {
        $eleves = $classe->eleves()->where('statut', 'actif')->get()->keyBy('id');

        // `hj`/`hnj` gardent leur nom d'origine (heures) : au primaire ils
        // portent des journées, et c'est `unite` du bilan qui l'annonce.
        return $this->grille($classe, $trimestre)
            ->map(fn (array $ligne) => [
                'eleve' => $eleves->get($ligne['eleve_id']),
                'hj' => $ligne['justifiees'],
                'hnj' => $ligne['non_justifiees'],
            ]);
    }

    /**
     * Bilan disciplinaire d'une classe : total/moyenne d'absences non
     * justifiées par genre, élève le plus absent — même logique que
     * bilan_disciplinaire.php (basée sur les absences, pas sur le nombre de
     * sanctions).
     *
     * `unite` dit en quoi comptent les totaux : des heures au secondaire, des
     * journées au primaire et en maternelle. Les clés gardent leur préfixe
     * `hnj` d'origine, tout ce qui les affiche s'appuyant sur `unite`.
     */
    public function bilanClasse(Classe $classe, Trimestre $trimestre): array
    {
        $rows = $this->lignesDetail($classe, $trimestre);
        $eleves = $rows->pluck('eleve');

        $garcons = $rows->filter(fn ($r) => $r['eleve']->sexe === 'M');
        $filles = $rows->filter(fn ($r) => $r['eleve']->sexe === 'F');
        $plusAbsent = $rows->sortByDesc('hnj')->first();

        return [
            'effectif' => $eleves->count(),
            'unite' => $classe->school->estSecondaire() ? 'heures' : 'jours',
            'total_hj' => round((float) $rows->sum('hj'), 1),
            'total_hnj' => round((float) $rows->sum('hnj'), 1),
            'moyenne_hnj' => $eleves->count() > 0 ? round($rows->sum('hnj') / $eleves->count(), 1) : 0,
            'total_hnj_garcons' => round((float) $garcons->sum('hnj'), 1),
            'moyenne_hnj_garcons' => $garcons->count() > 0 ? round($garcons->sum('hnj') / $garcons->count(), 1) : 0,
            'total_hnj_filles' => round((float) $filles->sum('hnj'), 1),
            'moyenne_hnj_filles' => $filles->count() > 0 ? round($filles->sum('hnj') / $filles->count(), 1) : 0,
            'eleve_plus_absent' => $plusAbsent && $plusAbsent['hnj'] > 0 ? [
                'nom_complet' => $plusAbsent['eleve']->nom_complet,
                'heures_non_justifiees' => $plusAbsent['hnj'],
            ] : null,
        ];
    }

    /**
     * Taux de fréquentation trimestriel par sexe — rubrique « Taux de
     * fréquentation trimestriel par cours et par sexe » du rapport de fin de
     * trimestre MINEDUB.
     *
     * Réutilise `grille()` (déjà normalisée jours/heures selon le cycle, et
     * déjà consciente des corrections manuelles du Surveillant Général) plutôt
     * que de refaire l'agrégation : le numérateur d'absence est donc
     * exactement celui affiché à l'écran de discipline.
     *
     * @return array{unite: string, garcons: array, filles: array, total: array}
     */
    public function tauxFrequentation(Classe $classe, Trimestre $trimestre): array
    {
        $lignes = $this->grille($classe, $trimestre);
        $eleves = $classe->eleves()->where('statut', 'actif')->get()->keyBy('id');
        $prevu = $this->joursOuHeuresPrevus($classe, $trimestre);

        return [
            'unite' => $classe->school->estSecondaire() ? 'heures' : 'jours',
            'garcons' => $this->tauxSousEnsemble($lignes->filter(fn (array $l) => $eleves->get($l['eleve_id'])?->sexe === 'M'), $prevu),
            'filles' => $this->tauxSousEnsemble($lignes->filter(fn (array $l) => $eleves->get($l['eleve_id'])?->sexe === 'F'), $prevu),
            'total' => $this->tauxSousEnsemble($lignes, $prevu),
        ];
    }

    /**
     * Même taux, ventilé par catégorie de nationalité/minorité — rubrique
     * « Taux de fréquentation des minorités » du canevas. Mêmes champs que
     * `EleveService::rapportMinorites()`/`effectifsDesagregesParClasse()`
     * pour identifier chaque catégorie (nationalite, refugie, deplace_interne,
     * bororo, baka).
     *
     * @return array<string, array{garcons: array, filles: array, total: array}>
     */
    public function tauxFrequentationMinorites(Classe $classe, Trimestre $trimestre): array
    {
        $lignes = $this->grille($classe, $trimestre)->keyBy('eleve_id');
        $eleves = $classe->eleves()->where('statut', 'actif')->get();
        $prevu = $this->joursOuHeuresPrevus($classe, $trimestre);

        $categories = [
            'camerounais' => fn (Eleve $e) => str_contains(mb_strtolower((string) $e->nationalite), 'camerounais'),
            'deplaces_internes' => fn (Eleve $e) => $e->deplace_interne === 'Oui',
            'refugies' => fn (Eleve $e) => $e->refugie === 'Oui',
            'bororo' => fn (Eleve $e) => $e->bororo === 'Oui',
            'baka' => fn (Eleve $e) => $e->baka === 'Oui',
        ];

        $lignesDe = fn (Collection $sousEnsemble) => $sousEnsemble
            ->map(fn (Eleve $e) => $lignes->get($e->id) ?? ['eleve_id' => $e->id, 'justifiees' => 0.0, 'non_justifiees' => 0.0]);

        $resultat = [];
        foreach ($categories as $cle => $filtre) {
            $sousEnsemble = $eleves->filter($filtre);

            $resultat[$cle] = [
                'garcons' => $this->tauxSousEnsemble($lignesDe($sousEnsemble->where('sexe', 'M')), $prevu),
                'filles' => $this->tauxSousEnsemble($lignesDe($sousEnsemble->where('sexe', 'F')), $prevu),
                'total' => $this->tauxSousEnsemble($lignesDe($sousEnsemble), $prevu),
            ];
        }

        return $resultat;
    }

    /**
     * Nombre de jours (primaire/maternelle) ou d'heures (secondaire) prévus
     * sur le trimestre pour la classe — même filtre de séances que
     * `joursAbsence()`/`cumulAbsences()` (`statut = 'effectuee'`), pour que le
     * dénominateur compte exactement ce que compte le numérateur d'absence.
     */
    private function joursOuHeuresPrevus(Classe $classe, Trimestre $trimestre): float
    {
        $seances = Seance::where('classe_id', $classe->id)
            ->where('trimestre_id', $trimestre->id)
            ->where('statut', 'effectuee')
            ->get();

        if (! $classe->school->estSecondaire()) {
            return $seances->pluck('date_seance')->map(fn ($d) => $d->toDateString())->unique()->count();
        }

        return round($seances->sum(fn (Seance $s) => $s->dureeHeures()), 2);
    }

    /**
     * Effectif, absences cumulées et taux de fréquentation d'un sous-ensemble
     * de lignes `grille()` (déjà filtrées par sexe et/ou catégorie).
     *
     * @param  Collection<int, array{justifiees: float, non_justifiees: float}>  $lignes
     */
    private function tauxSousEnsemble(Collection $lignes, float $prevu): array
    {
        $effectif = $lignes->count();
        $absences = (float) $lignes->sum(fn (array $l) => $l['justifiees'] + $l['non_justifiees']);
        $presencesMax = $effectif * $prevu;
        $presents = max($presencesMax - $absences, 0.0);

        return [
            'effectif' => $effectif,
            'prevu' => $prevu,
            'absences' => round($absences, 1),
            'taux' => $presencesMax > 0 ? round($presents / $presencesMax * 100, 1) : 0.0,
        ];
    }
}
