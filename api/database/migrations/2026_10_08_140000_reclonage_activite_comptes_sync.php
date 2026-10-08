<?php

use App\Models\DesktopProvisioningEcole;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
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
