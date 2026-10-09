<?php

namespace App\Services;

use App\Models\ActionAnnulable;
use App\Models\ClasseCompetence;
use App\Models\ClasseMatiere;
use App\Models\Eleve;
use App\Models\Note;
use App\Models\ObservationEvaluation;
use App\Models\Personnel;
use App\Models\Sequence;
use App\Models\User;
use App\Support\Historique\CollecteurActions;
use App\Support\Historique\DependancesImport;
use App\Support\Historique\ImportsAnnulables;
use App\Support\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HistoriqueActionsService
{
    public const ROUTES = [
        'api.v1.eleves.update' => ['eleves.update', 'Modification de la fiche eleve'],
        'api.v1.personnels.update' => ['personnel.update', 'Modification de la fiche personnel'],
        'api.v1.notes.bulk-store' => ['notes.create', 'Enregistrement des notes'],
        'api.v1.notes-primaire.bulk-store' => ['notes.create', 'Enregistrement des evaluations'],
    ];

    public static function definition(?string $route): ?array
    {
        return self::ROUTES[$route] ?? ImportsAnnulables::ROUTES[$route] ?? null;
    }

    public function requete(User $user): Builder
    {
        return ActionAnnulable::where('user_id', $user->id)->whereIn('school_id', Tenant::schoolIds());
    }

    public function etat(User $user): array
    {
        $annuler = $this->requete($user)->whereIn('etat', ['appliquee', 'indisponible'])->orderByDesc('id')->first();
        $retablir = $this->requete($user)->where('etat', 'annulee')->orderBy('id')->first();
        $resumer = fn (?ActionAnnulable $action) => $action ? [
            'id' => $action->uuid, 'libelle' => $action->libelle, 'revision' => $action->revision,
            'route' => $action->route, 'contexte' => $action->contexte,
        ] : null;

        return [
            'annuler' => $annuler?->etat === 'appliquee' ? $resumer($annuler) : null,
            'retablir' => $resumer($retablir),
            'raison' => $annuler?->etat === 'indisponible' ? 'Cette modification comporte des effets non annulables.' : null,
        ];
    }

    public function enregistrer(Request $request, array $capture): ?ActionAnnulable
    {
        $changements = $capture['changements'];
        if ($changements === [] && ! $capture['incompatible'] && ! $request->routeIs('api.v1.eleves.import-traiter')) {
            return null;
        }

        $route = $request->route()->getName();
        $contexte = $request->route()->parameters();
        $import = isset(ImportsAnnulables::ROUTES[$route]);
        if ($request->routeIs('api.v1.notes.bulk-store', 'api.v1.notes.import', 'api.v1.notes-primaire.bulk-store')) {
            $contexte['sequence_ids'] = $request->routeIs('api.v1.notes.bulk-store', 'api.v1.notes.import')
                ? [$request->integer('sequence_id')]
                : collect($request->input('notes', []))->pluck('sequence_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        }
        $schoolId = $request->attributes->get('historique.school_id') ?? Tenant::schoolId();
        $ecoles = [];
        foreach ($changements as $ligne) {
            $valeurs = $ligne['apres'] ?? $ligne['avant'];
            if ($import) {
                $ecole = $valeurs['school_id'] ?? (isset($valeurs['eleve_id']) ? Eleve::whereKey($valeurs['eleve_id'])->value('school_id') : null);
                if ($ecole !== null) {
                    $ecoles[] = (int) $ecole;
                    $schoolId ??= (int) $ecole;
                }
                continue;
            }
            if ($ligne['modele'] === Eleve::class || $ligne['modele'] === Personnel::class) {
                $schoolId = (int) $ligne['avant']['school_id'];
                // Les transferts et changements de relations ne sont pas une simple edition de fiche.
                foreach (['school_id', 'user_id', 'classe_id', 'fonction_id', 'departement_id', 'banque_id', 'photo_path'] as $champ) {
                    if (($ligne['avant'][$champ] ?? null) != ($ligne['apres'][$champ] ?? null)) {
                        $capture['incompatible'] = true;
                    }
                }
            } else {
                $schoolId = (int) Eleve::whereKey($valeurs['eleve_id'])->value('school_id');
            }
        }
        if (! $import && $request->exists('tuteurs')) {
            $capture['incompatible'] = true;
        }

        $this->requete($request->user())->where('etat', 'annulee')->update(['etat' => 'abandonnee']);
        $uuid = $request->attributes->get('historique.uuid') ?: Str::uuid()->toString();
        abort_unless(Str::isUuid($uuid), 422, 'Identifiant d\'action invalide.');
        if ($import) {
            $contexte['school_ids'] = array_values(array_unique($ecoles ?: [(int) $schoolId]));
            if ($groupe = $request->attributes->get('historique.groupe')) {
                $changements = $this->fusionner($groupe->changements, $changements);
                $contexte['school_ids'] = array_values(array_unique([...$groupe->contexte['school_ids'], ...$contexte['school_ids']]));
                $capture['incompatible'] = $capture['incompatible'] || $groupe->etat === 'indisponible';
            }
            if (! $capture['incompatible']) {
                $changements = app(DependancesImport::class)->capturer($changements);
            }
            $contexte['lignes'] = count($changements);
        }
        if ($groupe = $request->attributes->get('historique.groupe')) {
            $groupe->update([
                'contexte' => $contexte, 'changements' => $capture['incompatible'] ? [] : $changements,
                'etat' => $capture['incompatible'] ? 'indisponible' : 'appliquee', 'revision' => $groupe->revision + 1,
            ]);

            return $groupe;
        }
        $action = ActionAnnulable::create([
            'uuid' => $uuid,
            'user_id' => $request->user()->id,
            'school_id' => $schoolId,
            'route' => $route,
            'libelle' => self::definition($route)[1],
            'contexte' => $contexte,
            'changements' => $capture['incompatible'] ? [] : $changements,
            'etat' => $capture['incompatible'] ? 'indisponible' : 'appliquee',
        ]);
        $request->attributes->set('historique.uuid', $uuid);

        return $action;
    }

    private function fusionner(array $avant, array $suite): array
    {
        $lignes = [];
        foreach ([...$avant, ...$suite] as $ligne) {
            $cle = $ligne['modele'].':'.$ligne['id'];
            if (isset($lignes[$cle])) {
                $ligne['avant'] = $lignes[$cle]['avant'];
            }
            $lignes[$cle] = $ligne;
        }

        return array_values(array_filter($lignes, fn ($ligne) => ! $this->identiques($ligne['avant'], $ligne['apres'])));
    }

    public function executer(Request $request, string $uuid, bool $retablir): array
    {
        return DB::transaction(function () use ($request, $uuid, $retablir) {
            // Le meme verrou serialise les nouvelles actions et les annulations de ce compte.
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $action = $this->requete($request->user())->where('uuid', $uuid)->lockForUpdate()->firstOrFail();
            $request->attributes->set('historique.school_id', $action->school_id);
            abort_unless($action->revision === $request->integer('revision'), 409, 'Cette action a deja ete modifiee. Actualisez l\'historique.');
            abort_unless($action->etat === ($retablir ? 'annulee' : 'appliquee'), 409, 'Cette action ne peut plus etre annulee ou retablie.');
            // Une operation synchronisee vise son UUID, jamais la derniere action du serveur.
            if (! $request->attributes->get('historique.synchronisation')) {
                $suivante = $this->etat($request->user())[$retablir ? 'retablir' : 'annuler'];
                abort_unless(($suivante['id'] ?? null) === $uuid, 409, 'L\'historique a change. Actualisez-le avant de continuer.');
            }
            $this->autoriser($request->user(), $action);
            if (isset(ImportsAnnulables::ROUTES[$action->route])) {
                app(AnnulationImportService::class)->executer($action, $retablir);
                $action->fill(['etat' => $retablir ? 'appliquee' : 'annulee', 'revision' => $action->revision + 1])->save();

                return $this->etat($request->user());
            }
            $attendu = $retablir ? 'avant' : 'apres';
            $cible = $retablir ? 'apres' : 'avant';
            $lignes = $retablir ? $action->changements : array_reverse($action->changements);

            foreach ($lignes as $ligne) {
                abort_unless(in_array($ligne['modele'], [Eleve::class, Personnel::class, Note::class, ObservationEvaluation::class], true), 409);
                $modele = $ligne['modele']::where($ligne['cle'])->lockForUpdate()->first();
                $actuel = $modele ? CollecteurActions::valeurs($modele) : null;
                abort_unless($this->identiques($actuel, $ligne[$attendu]), 409,
                    'Ces donnees ont ete modifiees depuis votre action. Aucune donnee n\'a ete ecrasee.');

                if ($ligne[$cible] === null) {
                    $modele?->delete();
                } elseif ($modele) {
                    $modele->setRawAttributes([...$modele->getAttributes(), ...$ligne[$cible]]);
                    $modele->save();
                } else {
                    $modele = new $ligne['modele'];
                    $modele->setRawAttributes($ligne[$cible]);
                    $modele->save();
                }
            }
            $action->update(['etat' => $retablir ? 'appliquee' : 'annulee', 'revision' => $action->revision + 1]);

            return $this->etat($request->user());
        });
    }

    public function identiques(?array $a, ?array $b): bool
    {
        if ($a === null || $b === null) {
            return $a === $b;
        }
        if (array_keys($a) !== array_keys($b)) {
            ksort($a);
            ksort($b);
            if (array_keys($a) !== array_keys($b)) {
                return false;
            }
        }
        foreach ($a as $champ => $valeur) {
            if (($valeur === null) !== ($b[$champ] === null) || $valeur != $b[$champ]) {
                return false;
            }
        }

        return true;
    }

    private function autoriser(User $user, ActionAnnulable $action): void
    {
        $definition = self::definition($action->route);
        abort_unless($definition && $user->can($definition[0]), 403,
            'Vous ne disposez plus du privilege permettant cette modification.');
        if (isset(ImportsAnnulables::ROUTES[$action->route])) {
            abort_unless(array_diff($action->contexte['school_ids'], Tenant::schoolIds()) === [], 403,
                'Cet import concerne aussi un etablissement hors du perimetre courant.');
            foreach ($action->changements as $ligne) {
                $modele = $ligne['modele']::where($ligne['cle'])->first();
                if ($modele && isset($modele->school_id)) {
                    abort_unless(in_array((int) $modele->school_id, Tenant::schoolIds(), true), 403);
                }
                if ($modele instanceof Eleve) {
                    Eleve::dansPerimetre($user)->findOrFail($modele->id);
                }
            }
            if ($action->route !== 'api.v1.notes.import') {
                return;
            }
        }
        if ($action->route === 'api.v1.eleves.update') {
            Eleve::forSchool(Tenant::schoolIds())->dansPerimetre($user)->findOrFail($action->contexte['id']);
        } elseif ($action->route === 'api.v1.personnels.update') {
            Personnel::forSchool(Tenant::schoolIds())->findOrFail($action->contexte['id']);
        } else {
            $primaire = $action->route === 'api.v1.notes-primaire.bulk-store';
            $modele = $primaire ? ClasseCompetence::class : ClasseMatiere::class;
            $attribution = $modele::forSchool(Tenant::schoolIds())->with('classe')->findOrFail(
                $action->contexte[$primaire ? 'classeCompetenceId' : 'classeMatiereId']
            );
            $service = $primaire ? NotePrimaireService::class : NoteService::class;
            abort_unless(app($service)->peutSaisir($user, $attribution), 403, 'Vous ne pouvez plus saisir ces notes.');
            $sequences = collect($action->contexte['sequence_ids'])->sort()->values();
            foreach ($sequences as $id) {
                Sequence::with('trimestre.anneeScolaire')->lockForUpdate()->findOrFail($id)
                    ->verifierSaisieNotes($user, $attribution->classe->school_id);
            }
            foreach ($action->changements as $ligne) {
                $valeurs = $ligne['apres'] ?? $ligne['avant'];
                abort_unless($attribution->classe->eleves()->whereKey($valeurs['eleve_id'])->exists(), 409,
                    'Un eleve a change de classe depuis cette saisie.');
            }
        }
    }
}
