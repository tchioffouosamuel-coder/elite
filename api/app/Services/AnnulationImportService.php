<?php

namespace App\Services;

use App\Models\ActionAnnulable;
use App\Support\Historique\CollecteurActions;
use App\Support\Historique\DependancesImport;
use App\Support\Historique\ImportsAnnulables;
use App\Support\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class AnnulationImportService
{
    public function __construct(private readonly DependancesImport $dependances) {}

    public function verifier(ActionAnnulable $action, bool $retablir = false): void
    {
        $attendu = $retablir ? 'avant' : 'apres';
        $modeles = ImportsAnnulables::ROUTES[$action->route][2];
        // Toutes les verifications precedent la premiere mutation du lot.
        foreach ($action->changements as $ligne) {
            abort_unless(in_array($ligne['modele'], $modeles, true), 409);
            $modele = $ligne['modele']::where($ligne['cle'])->lockForUpdate()->first();
            $actuel = $modele ? CollecteurActions::valeurs($modele) : null;
            abort_unless(app(HistoriqueActionsService::class)->identiques($actuel, $ligne[$attendu]), 409,
                'Des donnees de cet import ont ete modifiees. L\'import n\'a pas ete annule.');
            abort_unless($this->dependances->empreintes($ligne) === $ligne['dependances'], 409,
                'Des donnees liees a cet import ont change (notes, paiements ou autres liens). Aucune donnee n\'a ete ecrasee.');
        }
    }

    public function executer(ActionAnnulable $action, bool $retablir): void
    {
        $this->verifier($action, $retablir);
        $lignes = $action->changements;
        $cible = $retablir ? 'apres' : 'avant';
        $this->verifierRelations($lignes, $cible);
        $ordre = $this->dependances->ordonner($lignes);
        if (! $retablir) {
            $ordre = array_reverse($ordre);
        }
        try {
            foreach ($ordre as $index) {
                $ligne = $lignes[$index];
                $modele = $ligne['modele']::where($ligne['cle'])->lockForUpdate()->first();
                if ($ligne[$cible] === null) {
                    $modele?->delete();
                } elseif ($modele) {
                    $modele->setRawAttributes([...$modele->getAttributes(), ...$ligne[$cible]]);
                    $modele->save();
                } else {
                    $modele = new $ligne['modele'];
                    $modele->setRawAttributes($ligne[$cible]);
                    $modele->save();
                    // Les nouvelles identites evitent de ressusciter un id synchronise supprime.
                    $lignes = $this->dependances->remapper($lignes, $modele->getTable(), $ligne['id'], $modele->getKey());
                }
            }
        } catch (QueryException $exception) {
            if (in_array($exception->getCode(), ['23000', '23503', '23505'], true)) {
                abort(409, 'Des relations ou des doublons empechent le retablissement de cet import.');
            }
            throw $exception;
        }
        $action->changements = $this->dependances->capturer($lignes);
    }

    private function verifierRelations(array $lignes, string $cible): void
    {
        $prevus = [];
        $lus = [];
        foreach ($lignes as $ligne) {
            $prevus[(new $ligne['modele'])->getTable()][$ligne['id']] = $ligne[$cible];
        }
        foreach ($lignes as $ligne) {
            if ($ligne[$cible] === null) {
                continue;
            }
            foreach ($this->dependances->etrangeres()[(new $ligne['modele'])->getTable()] ?? [] as $cle) {
                $id = $ligne[$cible][$cle['columns'][0]] ?? null;
                $table = $cle['foreign_table'];
                // Les comptes preexistants peuvent legitimement etre partages entre ecoles.
                if ($id === null || $table === 'users' || $cle['foreign_columns'] !== ['id']) {
                    continue;
                }
                if (array_key_exists($id, $prevus[$table] ?? [])) {
                    $parent = $prevus[$table][$id];
                } else {
                    $cleLecture = $table.':'.$id;
                    if (! array_key_exists($cleLecture, $lus)) {
                        $parent = DB::table($table)->where('id', $id)->lockForUpdate()->first();
                        $lus[$cleLecture] = $parent ? (array) $parent : null;
                    }
                    $parent = $lus[$cleLecture];
                }
                abort_unless($parent !== null, 409, 'Une reference de cet import n\'existe plus.');
                $ecole = $table === 'schools' ? (int) $id : ($parent['school_id'] ?? null);
                abort_unless($ecole === null || in_array((int) $ecole, Tenant::schoolIds(), true), 403,
                    'Une reference de cet import appartient maintenant a un autre etablissement.');
            }
        }
    }
}
