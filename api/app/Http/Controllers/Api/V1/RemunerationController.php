<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Personnel;
use App\Models\Remuneration;
use App\Services\Paie\BaremePaie;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Rémunération contractuelle du personnel.
 *
 * Une rémunération n'est jamais modifiée : on en enregistre une **nouvelle**
 * avec sa date d'effet, et l'ancienne reste au dossier. C'est ce qui permet de
 * rééditer un bulletin de mars sur les montants de mars, alors même que
 * l'agent a été augmenté depuis — et de justifier une augmentation en montrant
 * ce qui la précédait.
 */
class RemunerationController extends Controller
{
    /** Champs de gain, dans l'ordre où ils figurent sur le bulletin. */
    private const GAINS = [
        'salaire_base',
        'prime_anciennete',
        'prime_communication',
        'prime_transport',
        'prime_recherche',
        'prime_performance',
    ];

    /** Tout le personnel en poste, avec sa rémunération en vigueur s'il en a une. */
    public function index(Request $request): JsonResponse
    {
        $personnels = Personnel::forSchool(Tenant::schoolIds())
            ->when(
                $request->string('statut')->toString() ?: 'actif',
                fn ($q, $statut) => $statut === 'tous' ? $q : $q->where('statut', $statut),
            )
            ->with(['fonctionReference', 'school:id,name,code,type'])
            ->orderBy('nom_complet')
            ->get();

        // Une seule requête pour toutes les rémunérations, puis on ne garde que
        // la plus récente de chaque agent : `N+1` sur 40 agents à chaque
        // affichage n'apporterait rien.
        $courantes = Remuneration::whereIn('personnel_id', $personnels->pluck('id'))
            ->orderByDesc('date_effet')
            ->orderByDesc('id')
            ->get()
            ->groupBy('personnel_id')
            ->map(fn ($lot) => $lot->first());

        $bareme = new BaremePaie;

        return ApiResponse::success([
            'personnels' => $personnels->map(function (Personnel $personnel) use ($courantes, $bareme) {
                $remuneration = $courantes->get($personnel->id);

                return [
                    'id' => $personnel->id,
                    'nom_complet' => $personnel->nom_complet,
                    'matricule' => $personnel->matricule,
                    'fonction' => $personnel->fonction,
                    'statut' => $personnel->statut,
                    'school' => $personnel->school ? [
                        'id' => $personnel->school->id,
                        'name' => $personnel->school->name,
                        'code' => $personnel->school->code,
                        'type' => $personnel->school->type,
                    ] : null,
                    'remuneration' => $remuneration ? $this->resumer($remuneration, $bareme) : null,
                ];
            })->values(),
            'totaux' => $this->totaux($personnels, $courantes, $bareme),
        ]);
    }

    /** Historique complet d'un agent, du plus récent au plus ancien. */
    public function historique(int $personnelId): JsonResponse
    {
        $personnel = Personnel::forSchool(Tenant::schoolIds())->findOrFail($personnelId);
        $bareme = new BaremePaie;

        $historique = Remuneration::where('personnel_id', $personnel->id)
            ->orderByDesc('date_effet')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Remuneration $r) => $this->resumer($r, $bareme));

        return ApiResponse::success([
            'personnel' => ['id' => $personnel->id, 'nom_complet' => $personnel->nom_complet],
            'historique' => $historique,
        ]);
    }

    /**
     * Enregistre une nouvelle rémunération. Une seconde saisie à la même date
     * d'effet remplace la première : c'est une correction, pas une révision.
     */
    public function store(Request $request, int $personnelId): JsonResponse
    {
        $personnel = Personnel::forSchool(Tenant::schoolIds())->findOrFail($personnelId);

        $donnees = $request->validate([
            'date_effet' => ['required', 'date'],
            /*
             * Deux régimes : le salaire mensuel négocié du primaire, et la
             * vacation horaire du technique, où seules les heures enseignées
             * sont dues. Un vacataire n'a pas de salaire de base — il a un
             * taux — d'où les deux champs conditionnés l'un à l'autre.
             */
            'mode' => ['nullable', 'in:mensuel,horaire'],
            'taux_horaire' => ['required_if:mode,horaire', 'nullable', 'integer', 'min:1'],
            'salaire_base' => ['required_unless:mode,horaire', 'nullable', 'integer', 'min:0'],
            'prime_anciennete' => ['nullable', 'integer', 'min:0'],
            'prime_communication' => ['nullable', 'integer', 'min:0'],
            'prime_transport' => ['nullable', 'integer', 'min:0'],
            'prime_recherche' => ['nullable', 'integer', 'min:0'],
            'prime_performance' => ['nullable', 'integer', 'min:0'],
            'categorie' => ['nullable', 'string', 'max:20'],
        ]);

        $horaire = ($donnees['mode'] ?? 'mensuel') === 'horaire';

        $remuneration = Remuneration::updateOrCreate(
            ['personnel_id' => $personnel->id, 'date_effet' => $donnees['date_effet']],
            [
                'school_id' => $personnel->school_id,
                'mode' => $horaire ? 'horaire' : 'mensuel',
                'taux_horaire' => $horaire ? (int) $donnees['taux_horaire'] : null,
                // Une vacation n'a ni base ni primes : les laisser garnies
                // ferait apparaître un salaire là où il n'y a qu'un taux.
                ...collect(self::GAINS)->mapWithKeys(
                    fn ($champ) => [$champ => $horaire ? 0 : (int) ($donnees[$champ] ?? 0)],
                ),
                'categorie' => $donnees['categorie'] ?? null,
            ],
        );

        return ApiResponse::created(
            $this->resumer($remuneration, new BaremePaie),
            'Rémunération enregistrée.',
        );
    }

    /**
     * Applique une même rémunération à plusieurs agents.
     *
     * Les montants viennent soit d'un agent modèle (`source_personnel_id`),
     * soit d'une saisie directe. Le premier cas est le plus courant : la
     * grille salariale d'un établissement compte quelques niveaux, pas
     * quarante — recopier celui d'un collègue évite de ressaisir six montants
     * à l'identique et d'y glisser une faute.
     *
     * Un agent déjà pourvu à cette date d'effet est mis à jour, pas dupliqué,
     * et l'historique antérieur reste intact.
     */
    public function appliquer(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'personnel_ids' => ['required', 'array', 'min:1'],
            'personnel_ids.*' => ['integer'],
            'date_effet' => ['required', 'date'],
            'source_personnel_id' => ['nullable', 'integer'],
            'categorie' => ['nullable', 'string', 'max:20'],
            ...collect(self::GAINS)->mapWithKeys(fn ($champ) => [$champ => ['nullable', 'integer', 'min:0']])->all(),
        ]);

        $montants = $this->montantsAAppliquer(Tenant::schoolIds(), $donnees);

        // Le périmètre est refermé sur le complexe : un identifiant d'agent
        // hors du périmètre accessible au compte ne doit pas passer par cette
        // porte.
        $cibles = Personnel::forSchool(Tenant::schoolIds())
            ->whereIn('id', $donnees['personnel_ids'])
            ->get();

        foreach ($cibles as $personnel) {
            Remuneration::updateOrCreate(
                ['personnel_id' => $personnel->id, 'date_effet' => $donnees['date_effet']],
                ['school_id' => $personnel->school_id, ...$montants, 'categorie' => $donnees['categorie'] ?? null],
            );
        }

        $ignores = count($donnees['personnel_ids']) - $cibles->count();

        return ApiResponse::success(
            ['appliquee' => $cibles->count(), 'ignores' => $ignores],
            $cibles->count().' rémunération(s) appliquée(s)'
                .($ignores > 0 ? ", {$ignores} agent(s) hors de l'établissement ignoré(s)" : '').'.',
        );
    }

    /**
     * @param  array<string, mixed>  $donnees
     * @return array<string, int>
     */
    private function montantsAAppliquer(int|array $schoolIds, array $donnees): array
    {
        if (! empty($donnees['source_personnel_id'])) {
            $source = Remuneration::whereHas(
                'personnel',
                fn ($q) => $q->whereIn('school_id', (array) $schoolIds)->where('id', $donnees['source_personnel_id']),
            )
                ->orderByDesc('date_effet')
                ->orderByDesc('id')
                ->firstOrFail();

            return $source->only(self::GAINS);
        }

        return collect(self::GAINS)->mapWithKeys(fn ($champ) => [$champ => (int) ($donnees[$champ] ?? 0)])->all();
    }

    /**
     * Applique le barème à des montants saisis, sans rien enregistrer.
     *
     * Le net d'un salaire n'est pas devinable : exonérations plafonnées,
     * tranches d'IRPP, cotisations à deux parts. Sans cette simulation, on
     * fixerait un brut à l'aveugle et l'agent découvrirait son net sur le
     * premier bulletin.
     */
    public function simuler(Request $request): JsonResponse
    {
        $gains = $request->validate(
            collect(self::GAINS)->mapWithKeys(fn ($champ) => [$champ => ['nullable', 'integer', 'min:0']])->all(),
        );

        return ApiResponse::success((new BaremePaie)->calculer($gains)->toArray());
    }

    /** @return array<string, mixed> */
    private function resumer(Remuneration $remuneration, BaremePaie $bareme): array
    {
        $resultat = $bareme->calculer($remuneration->only(self::GAINS));

        return [
            'id' => $remuneration->id,
            'date_effet' => $remuneration->date_effet?->format('Y-m-d'),
            'categorie' => $remuneration->categorie,
            'mode' => $remuneration->mode,
            'taux_horaire' => $remuneration->taux_horaire,
            ...$remuneration->only(self::GAINS),
            'brut' => $resultat->brut,
            'base_taxable' => $resultat->baseTaxable,
            'charges_salariales' => $resultat->chargesSalariales,
            'charges_patronales' => $resultat->chargesPatronales,
            'net' => $resultat->netAvantDeductions(),
            'cout_employeur' => $resultat->coutEmployeur(),
        ];
    }

    /** @return array<string, int> */
    private function totaux($personnels, $courantes, BaremePaie $bareme): array
    {
        $definies = $courantes->filter();

        $couts = $definies->map(fn (Remuneration $r) => $bareme->calculer($r->only(self::GAINS)));

        return [
            'effectif' => $personnels->count(),
            'definies' => $definies->count(),
            'sans_remuneration' => $personnels->count() - $definies->count(),
            'masse_brute' => (int) $couts->sum(fn ($r) => $r->brut),
            'cout_employeur' => (int) $couts->sum(fn ($r) => $r->coutEmployeur()),
            'net_mensuel' => (int) $couts->sum(fn ($r) => $r->netAvantDeductions()),
        ];
    }

    /** Ancienneté indicative, pour aider à fixer la prime correspondante. */
    public function anciennete(int $personnelId): JsonResponse
    {
        $personnel = Personnel::forSchool(Tenant::schoolIds())->findOrFail($personnelId);

        $annees = $personnel->date_embauche
            ? round($personnel->date_embauche->diffInDays(Carbon::today()) / 365.25, 2)
            : null;

        return ApiResponse::success(['annees' => $annees]);
    }
}
