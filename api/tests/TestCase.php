<?php

namespace Tests;

use App\Models\AnneeScolaire;
use App\Models\Eleve;
use App\Models\Preinscription;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Inscrit des élèves pour l'année active de leur école (préinscription
     * validée) : les listes de travail — classe, notes, bulletins, appels,
     * conseil de classe… — ne montrent que les inscrits, cf.
     * `Eleve::scopeInscritAnneeActive()`. Sans année active, rien n'est fait.
     *
     * @return Eleve|list<Eleve> l'élève (ou les élèves) reçu(s), pour chaîner
     */
    protected function inscrireAnneeActive(Eleve ...$eleves): Eleve|array
    {
        foreach ($eleves as $eleve) {
            $annee = AnneeScolaire::where('school_id', $eleve->school_id)->where('is_active', true)->first();

            if ($annee === null) {
                continue;
            }

            Preinscription::firstOrCreate(
                ['eleve_id' => $eleve->id, 'annee_scolaire_id' => $annee->id],
                [
                    'school_id' => $eleve->school_id,
                    'type' => 'existant',
                    'statut' => 'validee',
                    'donnees_eleve' => ['nom_complet' => $eleve->nom_complet],
                    'donnees_tuteurs' => [],
                    'classe_id' => $eleve->classe_id,
                ],
            );
        }

        return count($eleves) === 1 ? $eleves[0] : $eleves;
    }
}
