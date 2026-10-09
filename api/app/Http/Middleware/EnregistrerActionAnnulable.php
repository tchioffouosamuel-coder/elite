<?php

namespace App\Http\Middleware;

use App\Models\ClasseCompetence;
use App\Models\ClasseMatiere;
use App\Models\Eleve;
use App\Models\Personnel;
use App\Models\User;
use App\Services\AnnulationImportService;
use App\Services\HistoriqueActionsService;
use App\Support\Historique\CollecteurActions;
use App\Support\Historique\ImportsAnnulables;
use App\Support\Tenant;
use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnregistrerActionAnnulable
{
    public function __construct(
        private readonly CollecteurActions $collecteur,
        private readonly HistoriqueActionsService $historique,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($ecoles = $request->attributes->get('historique.ecoles')) {
            abort_unless(is_array($ecoles)
                && count(array_filter($ecoles, fn ($id) => is_int($id) && $id > 0)) === count($ecoles)
                && array_diff($ecoles, Tenant::schoolIds()) === [], 403,
                'Un etablissement de cet import n\'est plus accessible.');
            app()->bind('tenant.school_ids', fn () => $ecoles);
        }
        if ($request->routeIs('api.v1.historique.annuler', 'api.v1.historique.retablir')) {
            return $this->transaction(fn () => $next($request));
        }
        if (! HistoriqueActionsService::definition($request->route()?->getName())) {
            $reponse = $next($request);
            if ($reponse->isSuccessful() && ! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)
                && ! $request->routeIs(['api.v1.historique.*', 'api.v1.sync.*', 'api.v1.desktop.*', 'api.v1.notifications.*', 'api.v1.appareils.*', 'api.v1.eleves.import-preparer'])) {
                $this->historique->requete($request->user())->where('etat', 'annulee')->update(['etat' => 'abandonnee']);
                if ($request->routeIs('*.import', '*.import-traiter', '*.import-classe', '*.import-ocr')) {
                    $this->historique->enregistrer($request, ['changements' => [], 'incompatible' => true]);
                }
            }

            return $reponse;
        }

        return $this->transaction(function () use ($request, $next) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $uuid = $request->header('X-Action-Id') ?: Str::uuid()->toString();
            abort_unless(Str::isUuid($uuid), 422, 'Identifiant d\'action invalide.');
            if ($request->routeIs('api.v1.eleves.import-traiter')) {
                $precedente = $this->historique->requete($request->user())->orderByDesc('id')->lockForUpdate()->first();
                if ($request->attributes->get('historique.synchronisation')) {
                    $precedente = $this->historique->requete($request->user())->where('uuid', $uuid)->lockForUpdate()->first();
                }
                if ($precedente?->route === $request->route()->getName() && ($precedente->contexte['token'] ?? null) === $request->route('token')) {
                    abort_unless(in_array($precedente->etat, ['appliquee', 'indisponible', 'vide'], true), 409, 'Cet import a deja ete annule. Preparez un nouvel import.');
                    if ($precedente->etat === 'appliquee') {
                        app(AnnulationImportService::class)->verifier($precedente);
                    }
                    $request->attributes->set('historique.groupe', $precedente);
                    $uuid = $precedente->uuid;
                }
            }
            $request->attributes->set('historique.uuid', $uuid);
            if ($request->routeIs('api.v1.eleves.import', 'api.v1.eleves.import-traiter') && Tenant::isAggregate()) {
                $request->attributes->set('historique.ecoles_outbox', Tenant::schoolIds());
            }
            // Les fiches sont relues par le controleur apres la prise du verrou.
            if ($request->routeIs('api.v1.eleves.update', 'api.v1.personnels.update')) {
                $modele = $request->routeIs('api.v1.eleves.update') ? Eleve::class : Personnel::class;
                $fiche = $modele::whereKey($request->route('id'))->lockForUpdate()->first();
                $request->attributes->set('historique.school_id', $fiche?->school_id);
            } elseif ($request->routeIs('api.v1.notes.*', 'api.v1.notes-primaire.bulk-store')) {
                $primaire = $request->routeIs('api.v1.notes-primaire.bulk-store');
                $modele = $primaire ? ClasseCompetence::class : ClasseMatiere::class;
                $attribution = $modele::with('classe')->find($request->route($primaire ? 'classeCompetenceId' : 'classeMatiereId'));
                $request->attributes->set('historique.school_id', $attribution?->classe?->school_id);
            } else {
                $request->attributes->set('historique.school_id', $request->integer('school_id') ?: Tenant::schoolId());
            }
            $this->collecteur->ouvrir(ImportsAnnulables::ROUTES[$request->route()->getName()][2] ?? null);
            try {
                $reponse = $next($request);
            } finally {
                $capture = $this->collecteur->fermer();
            }
            if (! $reponse->isSuccessful()) {
                // Certains controleurs repondent avec une erreur sans lever d'exception.
                throw new HttpResponseException($reponse);
            }
            $this->historique->enregistrer($request, $capture);

            return $reponse;
        });
    }

    private function transaction(Closure $operation): Response
    {
        try {
            return DB::transaction(function () use ($operation) {
                $reponse = $operation();
                if (! $reponse->isSuccessful()) {
                    throw new HttpResponseException($reponse);
                }

                return $reponse;
            });
        } catch (HttpResponseException $exception) {
            return $exception->getResponse();
        }
    }
}
