<?php

namespace App\Services;

use App\Models\Observation;
use App\Models\Tuteur;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Fusion de deux fiches Tuteur reconnues comme doublons — même numéro de
 * téléphone normalisé, même école. La création d'un tuteur
 * ({@see \App\Services\EleveService::resolveTuteur()},
 * {@see \App\Services\PreinscriptionService}) compare le numéro BRUT, pas sa
 * forme normalisée ({@see \App\Support\Telephone::normaliser()}) : deux
 * saisies du même numéro sous des formes différentes ("659732002" vs
 * "0659732002") créent donc deux fiches au lieu de réutiliser la première.
 *
 * Contrairement à {@see EleveFusionService}, aucune condition de refus : un
 * Tuteur ne porte pas lui-même d'argent (les dossiers de scolarité sont
 * rattachés à l'ÉLÈVE, jamais au tuteur), la fusion peut donc toujours
 * procéder.
 */
class TuteurFusionService extends BaseService
{
    /** @return array{fusionne: bool} */
    public function fusionner(Tuteur $conservee, Tuteur $supprimee): array
    {
        $this->transaction(function () use ($conservee, $supprimee) {
            $this->dedupliquerElevesTuteur($supprimee->id, $conservee->id);

            foreach (['tuteur_telephones', 'modifications_eleves', 'justifications_absences', 'preinscriptions'] as $table) {
                if (DB::getSchemaBuilder()->hasColumn($table, 'tuteur_id')) {
                    DB::table($table)->where('tuteur_id', $supprimee->id)->update(['tuteur_id' => $conservee->id]);
                }
            }

            $this->fusionnerCompteParent($conservee, $supprimee);

            $supprimee->delete();
        });

        return ['fusionne' => true];
    }

    /**
     * Réassigne les enfants de la fiche supprimée à la fiche conservée, en
     * écartant d'abord ceux déjà rattachés à cette dernière — `eleve_tuteur`
     * porte une contrainte unique `(eleve_id, tuteur_id)` que la
     * réassignation en masse violerait sinon. La ligne de la fiche
     * conservée est celle qui gagne (même convention que
     * {@see EleveFusionService::dedupliquer()}) : son `lien_parente`/
     * `is_principal` prévaut sur celui, perdu, de la fiche supprimée.
     */
    private function dedupliquerElevesTuteur(int $supprimeeId, int $conserveeId): void
    {
        $elevesDejaLies = DB::table('eleve_tuteur')->where('tuteur_id', $conserveeId)->pluck('eleve_id');

        DB::table('eleve_tuteur')->where('tuteur_id', $supprimeeId)
            ->whereIn('eleve_id', $elevesDejaLies)
            ->delete();

        DB::table('eleve_tuteur')->where('tuteur_id', $supprimeeId)->update(['tuteur_id' => $conserveeId]);
    }

    /**
     * Si seule la fiche supprimée a un accès parent, il est repris tel quel
     * plutôt que perdu. Si les deux en ont un — deux comptes distincts pour
     * le même numéro, {@see \App\Services\CompteParentService::assurer()}
     * réutilise pourtant déjà un compte existant par téléphone normalisé, ce
     * cas ne devrait donc survenir que si l'accès a été ouvert avant que les
     * deux fiches ne soient reconnues comme doublons — le compte de la fiche
     * conservée est gardé, et les observations laissées par l'autre lui sont
     * réattribuées avant sa suppression, même logique que
     * {@see FusionComptesPersonnelParentService::fusionner()}.
     */
    private function fusionnerCompteParent(Tuteur $conservee, Tuteur $supprimee): void
    {
        if ($supprimee->user_id === null || $supprimee->user_id === $conservee->user_id) {
            return;
        }

        if ($conservee->user_id === null) {
            $conservee->forceFill(['user_id' => $supprimee->user_id])->save();

            return;
        }

        Observation::where('user_id', $supprimee->user_id)->update(['user_id' => $conservee->user_id]);
        User::find($supprimee->user_id)?->delete();
    }
}
