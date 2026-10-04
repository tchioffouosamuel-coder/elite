<?php

use App\Models\DesktopProvisioningEcole;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Poste desktop déjà en service : les colonnes `type` et `libelle` de
     * `emplois_du_temps` viennent d'entrer dans le registre de
     * synchronisation (cf. RegistreSync). Les pauses et activités déjà
     * copiées sur ce poste l'ont été sans elles : la colonne locale `type` a
     * pris sa valeur par défaut, `cours`, si bien qu'elles s'affichent comme
     * des cours sans matière. Le curseur de chaque école étant commun à
     * toutes les entités, le prochain `sync:pull` ne les renverrait jamais —
     * elles n'ont pas changé côté serveur. On le remet à zéro : reclonage
     * complet au prochain cycle, comme `reclonage_pour_comptes_utilisateurs`.
     *
     * Une ligne réapparaît avec le même `updated_at` que sa copie locale :
     * `SyncPull::appliquerLigne()` la réapplique bien (seule une ligne locale
     * strictement plus récente, donc une saisie hors-ligne pas encore
     * poussée, garde la priorité).
     *
     * Sans effet sur le serveur distant (`SYNC_LOCAL_REPLICA` faux).
     */
    public function up(): void
    {
        if (! config('sync.local_replica')) {
            return;
        }

        DesktopProvisioningEcole::query()->update(['curseur_sync' => null]);
    }

    public function down(): void
    {
        //
    }
};
