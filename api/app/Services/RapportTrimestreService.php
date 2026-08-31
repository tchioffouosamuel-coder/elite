<?php

namespace App\Services;

use App\Models\Classe;
use App\Models\School;
use App\Models\Setting;
use App\Models\Trimestre;

/**
 * Assemble le rapport de fin de trimestre, canevas MINEDUB, à partir des
 * services déjà exploités par les écrans de l'application — même principe
 * que `RapportRentreeService` : ce document est une mise en forme commune de
 * données déjà calculées ailleurs, jamais une source de vérité qui leur
 * ferait doublon.
 *
 * Une seule rubrique du canevas papier n'a pas d'équivalent dans le système :
 * l'assiduité des enseignants (tableau « Personnel »), faute d'un pointage
 * journalier du personnel — elle ressort à `null` et le générateur l'imprime
 * comme une rubrique à compléter à la main.
 */
class RapportTrimestreService
{
    public function __construct(
        private readonly EleveService $eleves,
        private readonly InfrastructureService $infrastructures,
        private readonly DisciplineService $discipline,
        private readonly ProgressionService $progression,
        private readonly StatistiquesService $statistiques,
        private readonly RapportTrimestreTexteService $textes,
    ) {}

    public function generer(int $schoolId, int $trimestreId): array
    {
        $school = School::findOrFail($schoolId);
        $trimestre = Trimestre::whereHas(
            'anneeScolaire',
            fn ($q) => $q->where('school_id', $schoolId)
        )->findOrFail($trimestreId);

        $classes = Classe::forSchool($schoolId)->with('titulaire')->orderBy('nom')->get();

        return [
            'school' => $school,
            'trimestre' => $trimestre,
            'identite' => $this->identite($schoolId),
            'infrastructures' => $this->infrastructures->rapport($schoolId),
            'effectifs_par_classe' => $this->eleves->effectifsDesagregesParClasse([$schoolId]),
            'minorites' => $this->eleves->rapportMinorites([$schoolId]),
            'frequentation_par_classe' => $classes->map(fn (Classe $c) => [
                'classe' => $c->nom,
                ...$this->discipline->tauxFrequentation($c, $trimestre),
            ])->all(),
            'frequentation_minorites_par_classe' => $classes->map(fn (Classe $c) => [
                'classe' => $c->nom,
                'categories' => $this->discipline->tauxFrequentationMinorites($c, $trimestre),
            ])->all(),
            'couverture_par_classe' => $classes->map(fn (Classe $c) => $this->couvertureClasse($c, $trimestre))->all(),
            'promotion_par_classe' => $classes->map(fn (Classe $c) => $this->promotionClasse($c, $trimestre))->all(),
            'textes' => $this->textes->all($schoolId, $trimestreId),
        ];
    }

    /** Mêmes clés que `RapportRentreeService::identite()` — même canevas d'identité d'établissement. */
    private function identite(int $schoolId): array
    {
        $cles = [
            'arrondissement', 'secteur', 'cycle', 'mode_fonctionnement',
            'directeur_nom', 'directeur_contact',
        ];

        $resultat = [];
        foreach ($cles as $cle) {
            $resultat[$cle] = Setting::get($schoolId, $cle) ?: null;
        }

        return $resultat;
    }

    /**
     * Couverture des programmes agrégée pour une classe : somme des leçons/
     * traitées de toutes ses matières, comme le fait déjà
     * `ProgressionService::tauxEtablissement()` pour l'année entière.
     */
    private function couvertureClasse(Classe $classe, Trimestre $trimestre): array
    {
        $matieres = $this->progression->tauxClasseTrimestre($classe, $trimestre);

        $leconsAnnee = $matieres->sum('lecons_annee');
        $leconsTrimestre = $matieres->sum('lecons_trimestre');
        $traiteesTrimestre = $matieres->sum('traitees_trimestre');

        return [
            'classe' => $classe->nom,
            'lecons_annee' => $leconsAnnee,
            'taux_annee' => $leconsAnnee > 0 ? round($matieres->sum(fn ($m) => $m['taux_annee'] * $m['lecons_annee']) / $leconsAnnee, 1) : 0.0,
            'lecons_trimestre' => $leconsTrimestre,
            'traitees_trimestre' => $traiteesTrimestre,
            'taux_trimestre' => $leconsTrimestre > 0 ? round($traiteesTrimestre / $leconsTrimestre * 100, 1) : 0.0,
        ];
    }

    /** Promotion interne / résultats internes d'une classe, à partir des statistiques pédagogiques déjà calculées. */
    private function promotionClasse(Classe $classe, Trimestre $trimestre): array
    {
        $stats = $this->statistiques->pedagogiquesClasse($classe, $trimestre);
        $total = $stats['categories']['total'];

        return [
            'classe' => $classe->nom,
            'effectif' => $total['effectif'],
            'admis' => $total['admis'],
            'taux_promotion' => $total['taux_reussite'],
            'moyenne_classe' => $stats['moyenne_classe'],
            'moyenne_plus_forte' => $stats['moyenne_plus_forte'],
            'moyenne_plus_faible' => $stats['moyenne_plus_faible'],
        ];
    }
}
