<?php

namespace App\Services;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\School;
use App\Models\Setting;

/**
 * Assemble le rapport de rentrée scolaire complet, canevas MINEDUB, à partir
 * des services déjà exploités par les écrans de l'application — aucune
 * donnée n'est recalculée en double, ce document n'est qu'une mise en forme
 * commune de ce que Élèves, Personnel, Infrastructures, Finances et Vie
 * scolaire exposent chacun séparément.
 *
 * Certaines rubriques du canevas papier n'ont pas d'équivalent dans le
 * système (évolution des effectifs sur 5 ans, taux de fréquentation
 * journalier, assiduité des enseignants, protocole anti-COVID) : elles
 * ressortent à `null` plutôt que d'être approximées, et le générateur PDF
 * les imprime comme des rubriques à compléter à la main.
 */
class RapportRentreeService
{
    public function __construct(
        private readonly EleveService $eleves,
        private readonly PersonnelService $personnels,
        private readonly InfrastructureService $infrastructures,
        private readonly BudgetFonctionnementService $budgetFonctionnement,
        private readonly AssuranceScolaireService $assurances,
        private readonly GouvernanceEcoleService $gouvernance,
        private readonly VisiteAutoriteService $visites,
        private readonly ActiviteRentreeService $activites,
        private readonly VenteDenreeService $ventes,
        private readonly RapportRentreeTexteService $textes,
    ) {}

    public function generer(int $schoolId, int $anneeScolaireId): array
    {
        $school = School::findOrFail($schoolId);
        $annee = AnneeScolaire::where('school_id', $schoolId)->findOrFail($anneeScolaireId);

        return [
            'school' => $school,
            'annee' => $annee,
            'identite' => $this->identite($schoolId),
            'effectifs_par_classe' => $this->eleves->effectifsDesagregesParClasse([$schoolId]),
            'minorites' => $this->eleves->rapportMinorites([$schoolId]),
            'pyramide_ages' => $this->eleves->tableauAges([$schoolId]),
            'ratio_eleve_maitre' => $this->ratioEleveMaitre($schoolId),
            'personnel' => $this->personnels->rapportMiseEnPlace($schoolId),
            'infrastructures' => $this->infrastructures->rapport($schoolId),
            'budget_fonctionnement' => $this->budgetFonctionnement->rapport($schoolId, $anneeScolaireId),
            'assurances_scolaires' => $this->assurances->list($schoolId, $anneeScolaireId),
            'visites_autorites' => $this->visites->list($schoolId, $anneeScolaireId),
            'activites_pedagogiques' => $this->activites->list($schoolId, $anneeScolaireId, 'pedagogique'),
            'activites_eps' => $this->activites->list($schoolId, $anneeScolaireId, 'eps'),
            'activites_fenassco' => $this->activites->list($schoolId, $anneeScolaireId, 'fenassco'),
            'ventes_denrees' => $this->ventes->list($schoolId, $anneeScolaireId),
            'conseil_ecole' => $this->gouvernance->conseilEcole($schoolId, $anneeScolaireId),
            'apee' => $this->gouvernance->apee($schoolId, $anneeScolaireId),
            'textes' => $this->textes->all($schoolId, $anneeScolaireId),
        ];
    }

    /** @return array{arrondissement: ?string, secteur: ?string, cycle: ?string, mode_fonctionnement: ?string, annee_creation: ?string, annee_ouverture: ?string, numero_arrete_ouverture: ?string, numero_autorisation_ouverture: ?string, fondateur_nom: ?string, fondateur_contact: ?string, directeur_nom: ?string, directeur_contact: ?string} */
    private function identite(int $schoolId): array
    {
        $cles = [
            'arrondissement', 'secteur', 'cycle', 'mode_fonctionnement',
            'annee_creation', 'annee_ouverture', 'numero_arrete_ouverture', 'numero_autorisation_ouverture',
            'fondateur_nom', 'fondateur_contact', 'directeur_nom', 'directeur_contact',
        ];

        $resultat = [];
        foreach ($cles as $cle) {
            $resultat[$cle] = Setting::get($schoolId, $cle) ?: null;
        }

        return $resultat;
    }

    /**
     * Ratio élève/maître par classe (tableau 16) : une classe compte pour un
     * enseignant dès qu'elle a un titulaire — c'est la seule affectation
     * fiable au primaire/maternelle, où un seul agent tient la classe.
     *
     * @return list<array{classe: string, effectif: int, enseignants: int, ratio: ?float}>
     */
    private function ratioEleveMaitre(int $schoolId): array
    {
        return Classe::forSchool($schoolId)
            ->withCount(['eleves' => fn ($q) => $q->where('statut', 'actif')])
            ->orderBy('nom')
            ->get()
            ->map(fn (Classe $classe) => [
                'classe' => $classe->nom,
                'effectif' => $classe->eleves_count,
                'enseignants' => $classe->titulaire_id ? 1 : 0,
                'ratio' => $classe->titulaire_id ? round($classe->eleves_count / 1, 1) : null,
            ])
            ->all();
    }
}
