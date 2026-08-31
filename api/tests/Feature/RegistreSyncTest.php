<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Sync\RegistreSync;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Garde-fou structurel : chaque entité du registre doit interroger un
 * modèle existant, avec des colonnes et des relations réellement présentes
 * en base — sans quoi `SyncController::pull()` échouerait en silence
 * (ou en 500) au premier appel réel sur cette entité.
 *
 * Ne teste pas le contenu métier des filtres (couvert par `DesktopSyncTest`
 * et les tests de périmètre existants) : seulement que chaque définition du
 * catalogue est exécutable de bout en bout.
 */
class RegistreSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_chaque_entite_du_registre_est_interrogeable(): void
    {
        $user = User::factory()->create();

        foreach (RegistreSync::entites($user) as $cle => $definition) {
            $requete = $definition['modele']::query()
                ->select(array_values(array_unique([...$definition['colonnes'], 'updated_at'])));

            ($definition['portee'])($requete, $user->school_id ?? 1);

            try {
                $requete->limit(1)->get();
            } catch (\Throwable $e) {
                $this->fail("Entité « {$cle} » : {$e->getMessage()}");
            }
        }

        $this->assertTrue(true);
    }

    /** Chaque modèle du registre existe réellement et sait dire sous quelle école ranger sa pierre tombale (ou explicitement aucune). */
    public function test_ecole_de_ne_plante_sur_aucune_entite(): void
    {
        foreach (RegistreSync::cles() as $cle) {
            $modele = RegistreSync::entites()[$cle]['modele'];
            $instance = new $modele();

            try {
                RegistreSync::ecoleDe($cle, $instance);
            } catch (\Throwable $e) {
                $this->fail("ecoleDe(« {$cle} ») : {$e->getMessage()}");
            }
        }

        $this->assertTrue(true);
    }
}
