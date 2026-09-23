<?php

use App\Models\DesktopProvisioningEcole;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Poste desktop déjà en service : l'entité `utilisateurs` et la colonne
     * `personnels.user_id` viennent d'entrer dans le registre de
     * synchronisation (cf. RegistreSync). Le curseur de chaque école étant
     * commun à toutes les entités, le prochain `sync:pull` ne tirerait sinon
     * que les comptes modifiés depuis ce curseur — jamais les comptes
     * existants. On le remet à zéro : reclonage complet au prochain cycle.
     *
     * Aucun horodatage local n'est touché (contrairement à
     * `reparer_horodatages_synchronises`) : une ligne locale plus récente
     * que le serveur, donc une saisie hors-ligne pas encore poussée, garde
     * la priorité comme d'habitude.
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
