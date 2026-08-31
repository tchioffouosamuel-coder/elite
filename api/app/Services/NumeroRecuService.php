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

    /** Série distincte du transport : un reçu de bus ne doit jamais partager son rang avec un reçu de scolarité. */
    private const TYPE_BUS = 'recu_bus';

    private const PREFIXE_BUS = 'RB';

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

    /** Même série de numérotation, mais dédiée au transport — reçu « RB-EBT-0007 ». */
    public function attribuerBus(School $school, ?int $anneeScolaireId, ?int $generePar = null): string
    {
        $reference = $this->references->attribuer($school->id, self::TYPE_BUS, $anneeScolaireId, null, $generePar);

        return $this->formater($school, $reference->numero, self::PREFIXE_BUS);
    }

    /** `schools.code` est obligatoire en base : il y a toujours un segment central. */
    public function formater(School $school, int $numero, ?string $prefixe = null): string
    {
        return sprintf(
            '%s-%s-%s',
            $prefixe ?? config('recu.prefixe'),
            $school->code,
            str_pad((string) $numero, (int) config('recu.longueur_numero'), '0', STR_PAD_LEFT),
        );
    }
}
