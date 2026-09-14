<?php

use App\Models\DesktopProvisioning;
use App\Models\DesktopProvisioningEcole;
use App\Models\SyncOutbox;
use App\Support\Sync\RegistreSync;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Auto-réparation, une seule fois, du bug d'horodatage qui a empêché
     * `preinscriptions.annee_scolaire_id` (et potentiellement d'autres
     * colonnes ajoutées après coup à des entités déjà synchronisées) de se
     * mettre à jour sur tout poste desktop déjà en service : `SyncPull`
     * laissait Eloquent réécrire `updated_at` à l'heure de CHAQUE
     * sauvegarde locale, si bien qu'une fois une ligne synchronisée une
     * première fois, l'arbitrage « le plus récent gagne » la considérait
     * pour toujours plus récente que le serveur distant, quelle que soit la
     * fraîcheur réelle de son contenu — un simple reclonage (curseur remis
     * à zéro) ne suffisait donc pas à la corriger, confirmé en conditions
     * réelles sur une installation affectée.
     *
     * Plutôt que d'exiger un script PowerShell manuel par poste, cette
     * migration s'exécute automatiquement au prochain démarrage de
     * l'application (`artisan migrate --force` tourne déjà à chaque
     * lancement, cf. `demarrerServeurPhp()` dans `main.cjs`) : elle
     * antidate l'`updated_at` de chaque ligne synchronisée à une date très
     * ancienne et remet à zéro le curseur de chaque école, pour que le
     * prochain `sync:pull` (la boucle périodique de `main.cjs`, dans les 5
     * minutes suivantes, ou un clic sur « Synchroniser maintenant ») retire
     * tout, sans qu'aucun arbitrage ne bloque plus jamais une colonne
     * manquante.
     *
     * `SYNC_LOCAL_REPLICA` : n'a de sens QUE sur un poste desktop — cette
     * migration, comme le reste du code, est partagée avec le serveur
     * distant multi-écoles, où elle ne doit surtout jamais toucher aux
     * horodatages réels de la production.
     */
    public function up(): void
    {
        if (! config('sync.local_replica')) {
            return;
        }

        if (DesktopProvisioning::query()->doesntExist()) {
            // Poste jamais provisionné (première installation) : rien à
            // réparer, aucune ligne synchronisée n'existe encore.
            return;
        }

        // Une écriture hors-ligne pas encore poussée porte un `updated_at`
        // REEL (une vraie modification locale, via l'usage normal de
        // l'application) — l'antidater la ferait paraître plus ancienne que
        // le serveur distant, et le prochain pull écraserait cette
        // modification avec une version distante potentiellement périmée.
        // On préfère ne rien réparer du tout plutôt que risquer de perdre
        // une saisie réelle ; l'utilisateur peut relancer plus tard, une
        // fois son outbox vidée, pour que la réparation s'applique.
        if (SyncOutbox::query()->whereNull('pushed_at')->exists()) {
            Log::warning(
                'sync:pull réparation des horodatages reportée : écritures hors-ligne en attente d’envoi.',
            );

            return;
        }

        $epoque = '1970-01-01 00:00:01';

        foreach (RegistreSync::entites() as $definition) {
            $table = (new $definition['modele'])->getTable();

            if (DB::getSchemaBuilder()->hasColumn($table, 'updated_at')) {
                DB::table($table)->update(['updated_at' => $epoque]);
            }
        }

        DesktopProvisioningEcole::query()->update(['curseur_sync' => null]);

        Log::info('sync:pull horodatages réinitialisés — reclonage complet au prochain cycle de synchronisation.');
    }

    /**
     * Irréversible par nature (on ne connaît plus les horodatages réels
     * d'origine) — la seule chose que ferait un vrai rollback serait de
     * forcer un nouveau reclonage, déjà couvert par `up()` lui-même à la
     * prochaine exécution.
     */
    public function down(): void
    {
        //
    }
};
