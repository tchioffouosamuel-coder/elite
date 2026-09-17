<?php

namespace App\Services\Paie;

/**
 * Contrat d'un barème de paie.
 *
 * L'établissement en applique deux, et ils ne donnent pas le même résultat :
 * le barème légal camerounais (IRPP progressif, TDL par tranche, exonérations
 * de transport et communication) et le barème effectivement pratiqué dans ses
 * registres — taux forfaitaires sur une assiette plafonnée, sans IRPP.
 * Laisser le second en dur dans le code aurait rendu le premier inaccessible,
 * et inversement ; le choix se fait par configuration.
 *
 * @see BaremePaie   pour le barème légal
 * @see BaremeMaison pour la pratique des registres
 */
interface Bareme
{
    /**
     * @param  array<string, int>  $gains  salaire_base, prime_anciennete,
     *                                     prime_communication, prime_transport,
     *                                     prime_recherche, prime_performance
     * @param  int|null  $schoolId  école dont les taux (Setting) surchargent le
     *                              barème par défaut — cf. BaremeMaison. Sans
     *                              elle (aucun établissement précis, ex. une
     *                              simulation hors contexte), le barème par
     *                              défaut de config/paie.php s'applique.
     */
    public function calculer(array $gains, ?int $schoolId = null): ResultatPaie;

    /** Libellé du barème, porté sur le bulletin pour qu'il se relise plus tard. */
    public function libelle(): string;
}
