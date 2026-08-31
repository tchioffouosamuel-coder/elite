<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\DossierScolarite;
use App\Models\FraisAnnexe;
use App\Models\GrilleFrais;
use App\Services\ScolariteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Paramétrage des tarifs : grille de scolarité par classe (ou par défaut pour
 * toute l'école) et catalogue des frais annexes.
 *
 * Un tarif modifié ici se répercute aussitôt sur les dossiers déjà ouverts
 * (cf. `ScolariteService::synchroniserTarifs`) : leur montant de scolarité —
 * et donc leur reste à payer — suit la grille en continu, pas seulement les
 * dossiers ouverts après coup. Chaque famille concernée reçoit alors un SMS
 * l'informant du nouveau montant.
 */
class TarifsController extends Controller
{
    public function __construct(private readonly ScolariteService $scolarite) {}

    /** Grille complète : une ligne par classe, plus le tarif par défaut. */
    public function index(Request $request): JsonResponse
    {
        $schoolId = app('tenant.school_id');
        $annee = $this->annee($request);

        $grilles = GrilleFrais::forSchool($schoolId)
            ->where('annee_scolaire_id', $annee->id)
            ->get()
            ->keyBy(fn (GrilleFrais $g) => $g->classe_id ?? 0);

        $classes = Classe::forSchool($schoolId)
            ->orderBy('nom')
            ->get(['id', 'nom']);

        return ApiResponse::success([
            'annee_scolaire' => ['id' => $annee->id, 'libelle' => $annee->libelle],
            'tarif_par_defaut' => $grilles->get(0)?->montant,
            'classes' => $classes->map(fn (Classe $classe) => [
                'id' => $classe->id,
                'nom' => $classe->nom,
                'montant' => $grilles->get($classe->id)?->montant,
                'dossiers_ouverts' => $this->dossiersOuverts($classe->id, $annee->id),
            ])->values(),
            'frais_annexes' => FraisAnnexe::forSchool($schoolId)
                ->where('annee_scolaire_id', $annee->id)
                ->with('classes:id,nom')
                ->orderBy('libelle')
                ->get(['id', 'libelle', 'montant', 'obligatoire', 'is_active'])
                ->map(fn (FraisAnnexe $frais) => [
                    'id' => $frais->id,
                    'libelle' => $frais->libelle,
                    'montant' => $frais->montant,
                    'obligatoire' => $frais->obligatoire,
                    'is_active' => $frais->is_active,
                    // Vide = portée école entière.
                    'classes' => $frais->classes->map(fn (Classe $c) => ['id' => $c->id, 'nom' => $c->nom])->values(),
                ]),
        ]);
    }

    /** Enregistre le tarif d'une classe, ou le tarif par défaut si `classe_id` est nul, et le répercute sur les dossiers déjà ouverts. */
    public function definirTarif(Request $request): JsonResponse
    {
        $schoolId = app('tenant.school_id');

        $donnees = $request->validate([
            'classe_id' => ['nullable', 'integer', Rule::exists('classes', 'id')->where('school_id', $schoolId)],
            'montant' => ['required', 'integer', 'min:0'],
        ]);

        $annee = $this->annee($request);

        GrilleFrais::updateOrCreate(
            [
                'school_id' => $schoolId,
                'annee_scolaire_id' => $annee->id,
                'classe_id' => $donnees['classe_id'] ?? null,
            ],
            ['montant' => $donnees['montant']],
        );

        $misAJour = $this->scolarite->synchroniserTarifs($schoolId, $annee);

        return ApiResponse::success(
            ['dossiers_mis_a_jour' => $misAJour],
            $misAJour > 0 ? "Tarif enregistré — {$misAJour} dossier(s) mis à jour." : 'Tarif enregistré.',
        );
    }

    public function supprimerTarif(Request $request, int $classeId): JsonResponse
    {
        $schoolId = app('tenant.school_id');
        $annee = $this->annee($request);

        GrilleFrais::forSchool($schoolId)
            ->where('annee_scolaire_id', $annee->id)
            ->where('classe_id', $classeId)
            ->delete();

        $misAJour = $this->scolarite->synchroniserTarifs($schoolId, $annee);

        return ApiResponse::success(
            ['dossiers_mis_a_jour' => $misAJour],
            "Tarif retiré — la classe suit désormais le tarif par défaut".($misAJour > 0 ? " ({$misAJour} dossier(s) mis à jour)." : '.'),
        );
    }

    public function creerFraisAnnexe(Request $request): JsonResponse
    {
        $schoolId = app('tenant.school_id');

        $donnees = $request->validate([
            'libelle' => ['required', 'string', 'max:120'],
            'montant' => ['required', 'integer', 'min:0'],
            'obligatoire' => ['nullable', 'boolean'],
            'classe_ids' => ['nullable', 'array'],
            'classe_ids.*' => ['integer', Rule::exists('classes', 'id')->where('school_id', $schoolId)],
        ]);

        $frais = FraisAnnexe::create([
            'school_id' => $schoolId,
            'annee_scolaire_id' => $this->annee($request)->id,
            'libelle' => $donnees['libelle'],
            'montant' => $donnees['montant'],
            'obligatoire' => $donnees['obligatoire'] ?? false,
            'is_active' => true,
        ]);

        $frais->synchroniserClasses($donnees['classe_ids'] ?? []);

        return ApiResponse::created($frais->load('classes:id,nom'), 'Frais annexe ajouté.');
    }

    public function modifierFraisAnnexe(Request $request, int $id): JsonResponse
    {
        $schoolId = app('tenant.school_id');
        $frais = FraisAnnexe::forSchool($schoolId)->findOrFail($id);

        $donnees = $request->validate([
            'libelle' => ['sometimes', 'string', 'max:120'],
            'montant' => ['sometimes', 'integer', 'min:0'],
            'obligatoire' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'classe_ids' => ['sometimes', 'array'],
            'classe_ids.*' => ['integer', Rule::exists('classes', 'id')->where('school_id', $schoolId)],
        ]);

        $frais->update(collect($donnees)->except('classe_ids')->all());

        if (array_key_exists('classe_ids', $donnees)) {
            $frais->synchroniserClasses($donnees['classe_ids']);
        }

        return ApiResponse::success($frais->fresh()->load('classes:id,nom'), 'Frais annexe mis à jour.');
    }

    /**
     * Désactivation plutôt que suppression : le frais figure peut-être déjà sur
     * des dossiers, et son libellé doit rester lisible sur les reçus émis.
     */
    public function desactiverFraisAnnexe(int $id): JsonResponse
    {
        FraisAnnexe::forSchool(app('tenant.school_id'))->findOrFail($id)->update(['is_active' => false]);

        return ApiResponse::success(null, 'Frais annexe désactivé.');
    }

    private function dossiersOuverts(int $classeId, int $anneeId): int
    {
        return DossierScolarite::where('annee_scolaire_id', $anneeId)
            ->whereHas('eleve', fn ($q) => $q->where('classe_id', $classeId))
            ->count();
    }

    private function annee(Request $request): AnneeScolaire
    {
        $schoolId = app('tenant.school_id');

        if ($id = $request->integer('annee_scolaire_id')) {
            return AnneeScolaire::where('school_id', $schoolId)->findOrFail($id);
        }

        return AnneeScolaire::where('school_id', $schoolId)->where('is_active', true)->firstOrFail();
    }
}
