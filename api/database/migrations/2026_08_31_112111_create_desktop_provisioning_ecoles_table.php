<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Une ligne par école répliquée sur ce poste — le compte peut en
     * accéder à plusieurs (super admin d'un complexe), chacune avec son
     * propre curseur de synchronisation : le pull d'une école s'arrête où
     * il en était, indépendamment des autres.
     */
    public function up(): void
    {
        Schema::create('desktop_provisioning_ecoles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('desktop_provisioning_id')->constrained('desktop_provisioning')->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            // Même format Zulu que celui déjà utilisé par le mobile
            // (cf. SyncController::pull()).
            $table->string('curseur_sync')->nullable();
            $table->timestamp('dernier_pull_le')->nullable();
            $table->timestamps();

            $table->unique(['desktop_provisioning_id', 'school_id'], 'dpe_provisioning_school_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('desktop_provisioning_ecoles');
    }
};
