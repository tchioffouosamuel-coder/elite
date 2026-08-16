<?php

namespace App\Services;

use App\Models\School;

/**
 * Numérotation des reçus d'encaissement : une série par école, remise à zéro
 * à chaque année scolaire.
 *
 * S'appuie sur le registre des documents (cf. DocumentReferenceService), qui
 * pose déjà un numéro d'ordre verrouillé par école, type et année — deux
 * caissiers qui encaissent au même instant ne peuvent pas obtenir le même
 * numéro. Ce service n'ajoute que la mise en forme et le rattachement du reçu
 * à son versement.
 */
class NumeroRecuService
{
    private const TYPE = 'recu_scolarite';

    public function __construct(private readonly DocumentReferenceService $references) {}

    /**
     * Réserve le prochain numéro de la série de cette école et le rend mis en
     * forme, par exemple « RC-EBT-0042 ».
     */
    public function attribuer(School $school, ?int $anneeScolaireId, ?int $generePar = null): string
    {
        $reference = $this->references->attribuer($school->id, self::TYPE, $anneeScolaireId, null, $generePar);

        return $this->formater($school, $reference->numero);
    }

    /** `schools.code` est obligatoire en base : il y a toujours un segment central. */
    public function formater(School $school, int $numero): string
    {
        return sprintf(
            '%s-%s-%s',
            config('recu.prefixe'),
            $school->code,
            str_pad((string) $numero, (int) config('recu.longueur_numero'), '0', STR_PAD_LEFT),
        );
    }
}
