<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\DossierScolarite;
use App\Models\FraisAnnexe;
use App\Models\GrilleFrais;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Paramétrage des tarifs : grille de scolarité par classe et catalogue des
 * frais annexes.
 *
 * Modifier un tarif ici ne change **rien** aux dossiers déjà ouverts : ceux-ci
 * ont recopié le montant à l'inscription (cf. ScolariteService). C'est
 * volontaire — une famille à qui on a annoncé un prix ne doit pas le voir
 * bouger en cours d'année — mais assez contre-intuitif pour être rappelé à
 * l'écran.
 */
class TarifsController extends Controller
{
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
            ->when($annee->id, fn ($q) => $q->where('annee_scolaire_id', $annee->id))
            ->orderBy('nom')
            ->get(['id', 'nom']);

        return ApiResponse::success([
            'annee_scolaire' => ['id' => $annee->id, 'libelle' => $annee->libelle],
            'tarif_par_defaut' => $grilles->get(0)?->montant,
            'classes' => $classes->map(fn (Classe $classe) => [
                'id' => $classe->id,
                'nom' => $classe->nom,
                'montant' => $grilles->get($classe->id)?->montant,
                // Un dossier déjà ouvert ne suit plus la grille : le compter
                // dit à l'utilisateur combien de familles ne seront pas
                // affectées par sa modification.
                'dossiers_ouverts' => $this->dossiersOuverts($classe->id, $annee->id),
            ])->values(),
            'frais_annexes' => FraisAnnexe::forSchool($schoolId)
                ->where('annee_scolaire_id', $annee->id)
                ->orderBy('libelle')
                ->get(['id', 'libelle', 'montant', 'obligatoire', 'is_active']),
        ]);
    }

    /** Enregistre le tarif d'une classe, ou le tarif par défaut si `classe_id` est nul. */
    public function definirTarif(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'classe_id' => ['nullable', 'integer', 'exists:classes,id'],
            'montant' => ['required', 'integer', 'min:0'],
        ]);

        $annee = $this->annee($request);

        GrilleFrais::updateOrCreate(
            [
                'school_id' => app('tenant.school_id'),
                'annee_scolaire_id' => $annee->id,
                'classe_id' => $donnees['classe_id'] ?? null,
            ],
            ['montant' => $donnees['montant']],
        );

        return ApiResponse::success(null, 'Tarif enregistré.');
    }

    public function supprimerTarif(Request $request, int $classeId): JsonResponse
    {
        GrilleFrais::forSchool(app('tenant.school_id'))
            ->where('annee_scolaire_id', $this->annee($request)->id)
            ->where('classe_id', $classeId)
            ->delete();

        return ApiResponse::success(null, 'Tarif retiré — la classe suit désormais le tarif par défaut.');
    }

    public function creerFraisAnnexe(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'libelle' => ['required', 'string', 'max:120'],
            'montant' => ['required', 'integer', 'min:0'],
            'obligatoire' => ['nullable', 'boolean'],
        ]);

        $frais = FraisAnnexe::create([
            'school_id' => app('tenant.school_id'),
            'annee_scolaire_id' => $this->annee($request)->id,
            'libelle' => $donnees['libelle'],
            'montant' => $donnees['montant'],
            'obligatoire' => $donnees['obligatoire'] ?? false,
            'is_active' => true,
        ]);

        return ApiResponse::created($frais, 'Frais annexe ajouté.');
    }

    public function modifierFraisAnnexe(Request $request, int $id): JsonResponse
    {
        $frais = FraisAnnexe::forSchool(app('tenant.school_id'))->findOrFail($id);

        $frais->update($request->validate([
            'libelle' => ['sometimes', 'string', 'max:120'],
            'montant' => ['sometimes', 'integer', 'min:0'],
            'obligatoire' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]));

        return ApiResponse::success($frais->fresh(), 'Frais annexe mis à jour.');
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
