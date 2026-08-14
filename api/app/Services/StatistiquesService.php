<?php

namespace App\Services;

use App\Models\Classe;
use App\Models\Sanction;
use App\Models\School;
use App\Models\Setting;
use App\Models\Trimestre;

/**
 * Statistiques pédagogiques et disciplinaires, par classe et pour tout
 * l'établissement. Reprend le découpage de `_smapp`
 * (`pedagogic_stats_per_class.php`, `discipilnary_stats_per_class.php`) :
 * les effectifs sont ventilés en redoublants / nouveaux / garçons / filles,
 * chaque catégorie étant rapportée à son propre effectif.
 */
class StatistiquesService extends BaseService
{
    /** Bornes des cotes, de la plus haute à la plus basse. */
    private const BANDES = [
        'cvwa' => 16.0,
        'cwa' => 14.0,
        'ca' => 12.0,
        'caa' => 10.0,
    ];

    private const CATEGORIES = ['redoublants', 'nouveaux', 'garcons', 'filles', 'total'];

    public function __construct(private readonly MoyenneService $moyennes) {}

    /**
     * Statistiques pédagogiques d'une classe pour un trimestre.
     *
     * Point d'attention repris de `_smapp` : les pourcentages se calculent sur
     * l'effectif de la catégorie, pas sur le nombre d'élèves effectivement
     * notés. Un élève sans aucune note pèse donc dans le dénominateur — c'est
     * volontaire, sinon une classe à moitié saisie afficherait 100 % de
     * réussite.
     */
    public function pedagogiquesClasse(Classe $classe, Trimestre $trimestre): array
    {
        $seuilHonneur = (float) Setting::get($classe->school_id, 'honour_roll', 14);

        $eleves = $classe->eleves()->where('statut', 'actif')->get();
        // classementGeneral renvoie des lignes ['eleve' => Eleve, 'moyenne' => ?float].
        $classement = $this->moyennes
            ->classementGeneral($classe, $trimestre)
            ->keyBy(fn (array $ligne) => $ligne['eleve']->id);

        $stats = [];
        foreach (self::CATEGORIES as $categorie) {
            $stats[$categorie] = $this->categorieVide();
        }

        foreach ($eleves as $eleve) {
            $moyenne = $classement->get($eleve->id)['moyenne'] ?? null;

            $appartenances = ['total'];
            $appartenances[] = $eleve->redoublant ? 'redoublants' : 'nouveaux';
            $appartenances[] = $eleve->sexe === 'F' ? 'filles' : 'garcons';

            foreach ($appartenances as $categorie) {
                $stats[$categorie]['effectif']++;

                if ($moyenne === null) {
                    continue;
                }

                $stats[$categorie]['notes']++;
                $stats[$categorie][$this->bande($moyenne)]++;

                if ($moyenne >= 10) {
                    $stats[$categorie]['admis']++;
                }

                if ($moyenne >= $seuilHonneur) {
                    $stats[$categorie]['honneur']++;
                }
            }
        }

        foreach ($stats as $categorie => $valeurs) {
            $stats[$categorie] = $this->avecTaux($valeurs);
        }

        $moyennesValides = $classement->pluck('moyenne')->filter(fn (?float $m) => $m !== null);

        return [
            'classe' => ['id' => $classe->id, 'nom' => $classe->nom],
            'seuil_honneur' => $seuilHonneur,
            'categories' => $stats,
            'moyenne_classe' => $moyennesValides->isEmpty() ? null : round($moyennesValides->avg(), 2),
            'moyenne_plus_forte' => $moyennesValides->max(),
            'moyenne_plus_faible' => $moyennesValides->min(),
        ];
    }

    /** Statistiques pédagogiques de toutes les classes de l'établissement. */
    public function pedagogiquesEtablissement(int $schoolId, Trimestre $trimestre): array
    {
        $classes = Classe::query()
            ->where('school_id', $schoolId)
            ->orderBy('nom')
            ->get();

        $parClasse = $classes->map(fn (Classe $c) => $this->pedagogiquesClasse($c, $trimestre))->all();

        return [
            'trimestre' => ['id' => $trimestre->id, 'libelle' => $trimestre->libelle],
            'classes' => $parClasse,
            'consolide' => $this->consoliderPedagogiques($parClasse),
        ];
    }

    /** Statistiques disciplinaires d'une classe, adossées au bilan existant. */
    public function disciplinairesClasse(Classe $classe, Trimestre $trimestre, DisciplineService $discipline): array
    {
        $bilan = $discipline->bilanClasse($classe, $trimestre);

        $sanctions = Sanction::query()
            ->where('classe_id', $classe->id)
            ->where('trimestre_id', $trimestre->id)
            ->get(['eleve_id', 'type']);

        $parType = $sanctions->countBy('type')->all();

        return [
            'classe' => ['id' => $classe->id, 'nom' => $classe->nom],
            'bilan' => $bilan,
            'sanctions_par_type' => $parType,
            'eleves_sanctionnes' => $sanctions->pluck('eleve_id')->unique()->count(),
            'total_sanctions' => $sanctions->count(),
        ];
    }

    /** Statistiques disciplinaires de toutes les classes de l'établissement. */
    public function disciplinairesEtablissement(int $schoolId, Trimestre $trimestre, DisciplineService $discipline): array
    {
        $classes = Classe::query()
            ->where('school_id', $schoolId)
            ->orderBy('nom')
            ->get();

        $parClasse = $classes
            ->map(fn (Classe $c) => $this->disciplinairesClasse($c, $trimestre, $discipline))
            ->all();

        $tousTypes = collect($parClasse)
            ->flatMap(fn (array $c) => $c['sanctions_par_type'])
            ->keys()
            ->unique();

        $consolide = [
            'total_sanctions' => collect($parClasse)->sum('total_sanctions'),
            'eleves_sanctionnes' => collect($parClasse)->sum('eleves_sanctionnes'),
            'effectif' => collect($parClasse)->sum(fn ($c) => $c['bilan']['effectif']),
            // Heures au secondaire, journées au primaire et en maternelle : les
            // clés gardent leur nom d'origine, `unite` dit ce qu'elles comptent.
            'unite' => School::findOrFail($schoolId)->estSecondaire() ? 'heures' : 'jours',
            'heures_justifiees' => round(collect($parClasse)->sum(fn ($c) => $c['bilan']['total_hj']), 1),
            'heures_non_justifiees' => round(collect($parClasse)->sum(fn ($c) => $c['bilan']['total_hnj']), 1),
            'sanctions_par_type' => $tousTypes
                ->mapWithKeys(fn ($type) => [
                    $type => collect($parClasse)->sum(fn ($c) => $c['sanctions_par_type'][$type] ?? 0),
                ])
                ->all(),
        ];

        return [
            'trimestre' => ['id' => $trimestre->id, 'libelle' => $trimestre->libelle],
            'classes' => $parClasse,
            'consolide' => $consolide,
        ];
    }

    private function categorieVide(): array
    {
        return [
            'effectif' => 0,
            'notes' => 0,
            'admis' => 0,
            'honneur' => 0,
            'cvwa' => 0,
            'cwa' => 0,
            'ca' => 0,
            'caa' => 0,
            'cna' => 0,
        ];
    }

    private function bande(float $moyenne): string
    {
        foreach (self::BANDES as $cle => $plancher) {
            if ($moyenne >= $plancher) {
                return $cle;
            }
        }

        return 'cna';
    }

    private function avecTaux(array $valeurs): array
    {
        $effectif = $valeurs['effectif'];

        $valeurs['taux_reussite'] = $effectif > 0 ? round($valeurs['admis'] / $effectif * 100, 2) : 0.0;
        $valeurs['taux_participation'] = $effectif > 0 ? round($valeurs['notes'] / $effectif * 100, 2) : 0.0;

        return $valeurs;
    }

    /** Agrège les catégories de chaque classe en un total d'établissement. */
    private function consoliderPedagogiques(array $parClasse): array
    {
        $consolide = [];

        foreach (self::CATEGORIES as $categorie) {
            $somme = $this->categorieVide();

            foreach ($parClasse as $classe) {
                foreach (array_keys($somme) as $cle) {
                    $somme[$cle] += $classe['categories'][$categorie][$cle];
                }
            }

            $consolide[$categorie] = $this->avecTaux($somme);
        }

        return $consolide;
    }
}
