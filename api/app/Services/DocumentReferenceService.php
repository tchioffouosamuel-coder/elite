<?php

namespace App\Services;

use App\Models\DocumentReference;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

/**
 * Registre des documents officiels émis par l'établissement : chaque appel à
 * `attribuer()` pose un numéro d'ordre séquentiel (par école, type de
 * document et année scolaire) et le conserve, remplaçant le registre papier
 * que la mention « Réf. N° ………… » du certificat de scolarité laissait
 * jusqu'ici à compléter à la main.
 */
class DocumentReferenceService extends BaseService
{
    /**
     * Attribue et enregistre le prochain numéro d'ordre pour ce type de
     * document, dans le périmètre école + année scolaire.
     *
     * @param  Model|null  $sujet  Élève, personnel… concerné par le document (facultatif).
     */
    public function attribuer(
        int $schoolId,
        string $type,
        ?int $anneeScolaireId = null,
        ?Model $sujet = null,
        ?int $generePar = null,
    ): DocumentReference {
        try {
            return $this->inserer($schoolId, $type, $anneeScolaireId, $sujet, $generePar);
        } catch (QueryException $e) {
            // Rejoue une fois : absorbe la rare course où deux documents du
            // même type partent au même instant et calculent le même
            // « prochain numéro » avant que l'un des deux ne soit enregistré
            // — la contrainte unique fait alors échouer la première tentative.
            return $this->inserer($schoolId, $type, $anneeScolaireId, $sujet, $generePar);
        }
    }

    private function inserer(
        int $schoolId,
        string $type,
        ?int $anneeScolaireId,
        ?Model $sujet,
        ?int $generePar,
    ): DocumentReference {
        return $this->transaction(function () use ($schoolId, $type, $anneeScolaireId, $sujet, $generePar) {
            $dernier = DocumentReference::query()
                ->forSchool($schoolId)
                ->where('type', $type)
                ->where('annee_scolaire_id', $anneeScolaireId)
                ->lockForUpdate()
                ->max('numero');

            return DocumentReference::create([
                'school_id' => $schoolId,
                'type' => $type,
                'annee_scolaire_id' => $anneeScolaireId,
                'numero' => ((int) $dernier) + 1,
                'referencable_type' => $sujet?->getMorphClass(),
                'referencable_id' => $sujet?->getKey(),
                'genere_par' => $generePar,
            ]);
        });
    }
}
