<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Eleve;
use App\Support\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Console d'audit (interface développeur) : consultation, filtrage et
 * export du journal exhaustif des requêtes — réservée au super
 * administrateur, comme les autres outils système.
 *
 * Périmètre : le compte racine (sans école) voit tout, y compris les
 * connexions échouées qui ne se rattachent à aucune école ; un super admin
 * rattaché à une école ne voit que les écoles de son périmètre.
 */
class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $page = $this->requeteFiltree($request)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(max(1, min($request->integer('per_page', 50), 200)));

        $idsEleves = $page->getCollection()
            ->map(fn (AuditLog $log) => $this->eleveId($log))
            ->filter()
            ->unique()
            ->values();
        $requeteEleves = Eleve::query()->whereIn('id', $idsEleves);
        if ($request->user()->school_id !== null) {
            $requeteEleves->whereIn('school_id', Tenant::schoolIds());
        }
        $nomsEleves = $idsEleves->isEmpty() ? collect() : $requeteEleves->pluck('nom_complet', 'id');

        $page->through(fn (AuditLog $log) => $this->formater(
            $log,
            sujet: $nomsEleves->get($this->eleveId($log)),
        ));

        return ApiResponse::paginated($page);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $log = $this->requetePerimetre($request)->with('school:id,name')->findOrFail($id);

        return ApiResponse::success($this->formater(
            $log,
            detail: true,
            sujet: $this->nomEleve($log),
        ));
    }

    /**
     * Synthèse sur la même sélection que la liste : volume par type
     * d'action, utilisateurs et modules les plus actifs, erreurs.
     */
    public function stats(Request $request): JsonResponse
    {
        $base = fn () => $this->requeteFiltree($request);

        $parAction = $base()->select('action', DB::raw('COUNT(*) as total'))
            ->groupBy('action')->pluck('total', 'action');

        $utilisateurs = $base()->whereNotNull('user_id')
            ->select('user_id', DB::raw('MAX(user_nom) as user_nom'), DB::raw('COUNT(*) as total'))
            ->groupBy('user_id')->orderByDesc('total')->limit(8)->get();

        $modules = $base()->whereNotNull('module')
            ->select('module', DB::raw('COUNT(*) as total'))
            ->groupBy('module')->orderByDesc('total')->limit(8)->get();

        return ApiResponse::success([
            'total' => (int) $parAction->sum(),
            'par_action' => $parAction,
            'erreurs' => $base()->where('statut_http', '>=', 400)->count(),
            'utilisateurs_distincts' => $base()->whereNotNull('user_id')->distinct()->count('user_id'),
            'top_utilisateurs' => $utilisateurs,
            'top_modules' => $modules,
        ]);
    }

    /** Valeurs proposées par les listes déroulantes de filtre. */
    public function filtres(Request $request): JsonResponse
    {
        $base = $this->requetePerimetre($request);

        return ApiResponse::success([
            'actions' => collect(AuditLog::ACTIONS)->map(fn ($libelle, $code) => ['code' => $code, 'libelle' => $libelle])->values(),
            'modules' => (clone $base)->whereNotNull('module')->distinct()->orderBy('module')->pluck('module'),
            'utilisateurs' => (clone $base)->whereNotNull('user_id')
                ->select('user_id', DB::raw('MAX(user_nom) as user_nom'), DB::raw('MAX(user_role) as user_role'))
                ->groupBy('user_id')->orderBy('user_nom')->get(),
        ]);
    }

    /** Export CSV de la sélection courante (séparateur « ; » pour Excel en français). */
    public function export(Request $request): StreamedResponse
    {
        $requete = $this->requeteFiltree($request)->orderByDesc('created_at')->orderByDesc('id');
        $nomFichier = 'journal-audit-'.now()->format('Y-m-d_His').'.csv';

        return response()->streamDownload(function () use ($requete) {
            $sortie = fopen('php://output', 'w');
            fwrite($sortie, "\xEF\xBB\xBF");
            fputcsv($sortie, ['Date', 'Heure', 'Utilisateur', 'Rôle', 'Action', 'Module', 'Méthode', 'URL', 'Statut', 'Durée (ms)', 'IP', 'École', 'Navigateur'], ';');

            $requete->with('school:id,name')->chunk(1000, function ($lignes) use ($sortie) {
                foreach ($lignes as $log) {
                    fputcsv($sortie, [
                        $log->created_at?->format('d/m/Y'),
                        $log->created_at?->format('H:i:s'),
                        $log->user_nom,
                        $log->user_role,
                        AuditLog::ACTIONS[$log->action] ?? $log->action,
                        $log->module,
                        $log->methode,
                        $log->url,
                        $log->statut_http,
                        $log->duree_ms,
                        $log->ip_address,
                        $log->school?->name,
                        $log->user_agent,
                    ], ';');
                }
            });

            fclose($sortie);
        }, $nomFichier, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function requetePerimetre(Request $request): Builder
    {
        $requete = AuditLog::query();

        if ($request->user()->school_id !== null) {
            $requete->whereIn('school_id', Tenant::schoolIds());
        }

        return $requete;
    }

    private function requeteFiltree(Request $request): Builder
    {
        $requete = $this->requetePerimetre($request);

        if ($request->filled('du')) {
            $requete->where('created_at', '>=', Carbon::parse($request->string('du'))->startOfDay());
        }
        if ($request->filled('au')) {
            $requete->where('created_at', '<=', Carbon::parse($request->string('au'))->endOfDay());
        }
        if ($request->filled('user_id')) {
            $requete->where('user_id', $request->integer('user_id'));
        }
        if ($request->filled('action')) {
            $requete->whereIn('action', explode(',', $request->string('action')));
        }
        if ($request->filled('module')) {
            $requete->where('module', $request->string('module'));
        }
        if ($request->boolean('exclure_notifications')) {
            $requete->where(fn (Builder $q) => $q
                ->whereNull('module')
                ->orWhere('module', '!=', 'notifications'));
        }
        if ($request->filled('methode')) {
            $requete->where('methode', strtoupper($request->string('methode')));
        }
        if ($request->filled('statut')) {
            match ($request->string('statut')->toString()) {
                'succes' => $requete->where('statut_http', '<', 400),
                'erreur' => $requete->where('statut_http', '>=', 400),
                default => null,
            };
        }
        if ($request->filled('ip')) {
            $requete->where('ip_address', 'like', $request->string('ip').'%');
        }
        if ($request->filled('recherche')) {
            $terme = '%'.$request->string('recherche').'%';
            $requete->where(fn (Builder $q) => $q
                ->where('user_nom', 'like', $terme)
                ->orWhere('url', 'like', $terme)
                ->orWhere('route', 'like', $terme)
                ->orWhere('module', 'like', $terme));
        }
        if ($request->filled('apres_id')) {
            $requete->where('id', '>', $request->integer('apres_id'));
        }

        return $requete;
    }

    /** @return array<string, mixed> */
    private function formater(AuditLog $log, bool $detail = false, ?string $sujet = null): array
    {
        $ligne = [
            'id' => $log->id,
            'created_at' => $log->created_at?->toIso8601String(),
            'school_id' => $log->school_id,
            'user_id' => $log->user_id,
            'user_nom' => $log->user_nom,
            'user_role' => $log->user_role,
            'action' => $log->action,
            'action_libelle' => AuditLog::ACTIONS[$log->action] ?? $log->action,
            'module' => $log->module,
            'route' => $log->route,
            'methode' => $log->methode,
            'url' => $log->url,
            'parametres' => $log->parametres,
            'statut_http' => $log->statut_http,
            'duree_ms' => $log->duree_ms,
            'ip_address' => $log->ip_address,
            'nb_changements' => count($log->changements ?? []),
            'sujet' => $sujet,
        ];

        if ($detail) {
            $ligne['school'] = $log->school?->name;
            $ligne['user_agent'] = $log->user_agent;
            $ligne['donnees'] = $log->donnees;
            $ligne['changements'] = $log->changements;
        }

        return $ligne;
    }

    private function nomEleve(AuditLog $log): ?string
    {
        $id = $this->eleveId($log);

        return $id === null ? null : Eleve::query()->whereKey($id)->value('nom_complet');
    }

    private function eleveId(AuditLog $log): ?int
    {
        $url = (string) $log->url;
        if (preg_match('~/(?:parent/)?enfants/(\d+)(?:/|$)~', $url, $matches) === 1) {
            return (int) $matches[1];
        }

        if (preg_match('~/eleves/(\d+)(?:/|$)~', $url, $matches) === 1) {
            return (int) $matches[1];
        }

        $parametres = $log->parametres ?? [];
        $id = $parametres['eleveId'] ?? $parametres['eleve_id'] ?? null;

        return is_numeric($id) ? (int) $id : null;
    }
}
