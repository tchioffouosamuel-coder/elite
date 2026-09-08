<?php

namespace App\Console\Commands;

use App\Models\FraisAnnexe;
use App\Services\ScolariteService;
use Illuminate\Console\Command;

/**
 * Rattrape, une fois, les dossiers déjà ouverts qu'un changement
 * actif/obligatoire sur un frais annexe n'a pas pu répercuter avant
 * l'introduction de `ScolariteService::synchroniserFraisAnnexe()` : un frais
 * désactivé ou rendu facultatif AVANT ce correctif restait sinon
 * indéfiniment sur les dossiers où il avait été attaché — visible, et
 * imputable — malgré son état affiché comme « Désactivé ».
 *
 * Sûr à relancer plusieurs fois : chaque frais est traité indépendamment de
 * son historique réel, seul l'écart entre son état actuel et ce que portent
 * les dossiers compte. Un frais déjà cohérent (attaché partout où il doit
 * l'être, absent partout ailleurs) ne déclenche ni retrait, ni ajout, ni SMS.
 */
class SynchroniserFraisAnnexes extends Command
{
    protected $signature = 'frais-annexes:synchroniser {--school-id= : Ne traiter que les frais annexes de cette école}';

    protected $description = "Réaligne les dossiers déjà ouverts sur l'état actuel (actif/obligatoire) du catalogue des frais annexes.";

    public function handle(ScolariteService $service): int
    {
        $frais = FraisAnnexe::query()
            ->when($this->option('school-id'), fn ($q, $id) => $q->where('school_id', $id))
            ->get();

        $totalAjoutes = 0;
        $totalRetires = 0;

        foreach ($frais as $f) {
            // On force le passage par la branche « changement » de
            // synchroniserFraisAnnexe en lui présentant l'état inverse de
            // l'actuel comme « précédent » : ce script rattrape un état
            // jamais synchronisé, il n'a pas de vrai « avant » à comparer —
            // seul l'écart avec l'état courant des dossiers importe, et la
            // méthode ne fait rien de plus qu'un frais déjà cohérent.
            $estApplicable = $f->is_active && $f->obligatoire;
            $resultat = $service->synchroniserFraisAnnexe($f, ! $estApplicable);

            if ($resultat['ajoutes'] > 0 || $resultat['retires'] > 0) {
                $this->line("- {$f->libelle} (#{$f->id}) : +{$resultat['ajoutes']} ajouté(s), -{$resultat['retires']} retiré(s).");
            }

            $totalAjoutes += $resultat['ajoutes'];
            $totalRetires += $resultat['retires'];
        }

        $this->info("Terminé — {$totalAjoutes} ajout(s), {$totalRetires} retrait(s) sur {$frais->count()} frais annexe(s) parcouru(s).");

        return self::SUCCESS;
    }
}
