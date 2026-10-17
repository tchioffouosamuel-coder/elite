<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Suggestions de saisie pour les champs texte libres du formulaire
 * d'inscription (lieu de naissance, adresse, profession du tuteur...) :
 * renvoie les valeurs déjà enregistrées pour l'école courante qui
 * correspondent au texte tapé, sans jamais empêcher la saisie d'une valeur
 * inédite côté client — ce n'est qu'une aide à la frappe.
 *
 * Insensibilité casse + accents : la base utilise par défaut
 * `utf8mb4_unicode_ci` (cf. config/database.php), déjà insensible à la casse
 * ET aux accents en pratique ('Yaoundé' = 'Yaounde' sous cette collation,
 * vérifié directement en base) — un simple `whereIn`/distinct suffirait
 * donc côté MySQL. Le filtrage est néanmoins fait ici en PHP via
 * `Str::ascii()` (translitération + minuscule) plutôt que par un `LIKE` SQL,
 * pour deux raisons : (1) ne pas dépendre d'une collation précise qui
 * pourrait changer ou différer d'un environnement à l'autre (ex. SQLite en
 * test, qui ne replie pas les accents), et (2) le nombre de valeurs
 * distinctes pour ces champs reste modeste à l'échelle d'une école, donc le
 * coût de les comparer en PHP après un seul `distinct()` est négligeable.
 */
class ValeurSuggereeController extends Controller
{
    /**
     * Liste blanche des champs exposés : jamais de nom de table/colonne
     * arbitraire fourni par le client, pour écarter toute injection ou
     * énumération d'une colonne non prévue pour cet usage.
     *
     * @var array<string, array{table: string, column: string}>
     */
    private const CHAMPS = [
        'eleves.lieu_naissance' => ['table' => 'eleves', 'column' => 'lieu_naissance'],
        'eleves.adresse' => ['table' => 'eleves', 'column' => 'adresse'],
        'tuteurs.adresse' => ['table' => 'tuteurs', 'column' => 'adresse'],
        'tuteurs.profession' => ['table' => 'tuteurs', 'column' => 'profession'],
    ];

    private const LIMITE = 15;

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'champ' => ['required', 'string', Rule::in(array_keys(self::CHAMPS))],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        ['table' => $table, 'column' => $column] = self::CHAMPS[$validated['champ']];
        $recherche = trim((string) ($validated['q'] ?? ''));

        $valeurs = DB::table($table)
            ->whereIn('school_id', Tenant::schoolIds())
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->select($column)
            ->distinct()
            ->orderBy($column)
            ->pluck($column);

        if ($recherche !== '') {
            $cible = self::normaliser($recherche);
            $valeurs = $valeurs->filter(
                fn (string $valeur) => str_contains(self::normaliser($valeur), $cible)
            )->values();
        }

        return ApiResponse::success(['valeurs' => $valeurs->take(self::LIMITE)->values()]);
    }

    /** Minuscule + accents retirés, pour une comparaison insensible à la casse et aux accents. */
    private static function normaliser(string $valeur): string
    {
        return mb_strtolower(Str::ascii($valeur));
    }
}
