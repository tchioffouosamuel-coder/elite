<?php

namespace App\Services;

use App\Models\ActionAnnulable;
use App\Support\Historique\CollecteurActions;
use App\Support\Historique\DependancesImport;
use App\Support\Historique\ImportsAnnulables;
use Illuminate\Database\QueryException;

class AnnulationImportService
{
    public function __construct(private readonly DependancesImport $dependances) {}

    public function executer(ActionAnnulable $action, bool $retablir): void
    {
        $lignes = $action->changements;
        $attendu = $retablir ? 'avant' : 'apres';
        $cible = $retablir ? 'apres' : 'avant';
        $modeles = ImportsAnnulables::ROUTES[$action->route][2];
        // Toutes les verifications precedent la premiere mutation du lot.
        foreach ($lignes as $ligne) {
            abort_unless(in_array($ligne['modele'], $modeles, true), 409);
            $modele = $ligne['modele']::where($ligne['cle'])->lockForUpdate()->first();
            $actuel = $modele ? CollecteurActions::valeurs($modele) : null;
            abort_unless(app(HistoriqueActionsService::class)->identiques($actuel, $ligne[$attendu]), 409,
                'Des donnees de cet import ont ete modifiees. L\'import n\'a pas ete annule.');
            abort_unless($this->dependances->empreintes($ligne) === $ligne['dependances'], 409,
                'Des donnees liees a cet import ont change (notes, paiements ou autres liens). Aucune donnee n\'a ete ecrasee.');
        }
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
}
