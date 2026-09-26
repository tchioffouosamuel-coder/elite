<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banques', function (Blueprint $table) {
            $table->unsignedBigInteger('solde')->default(0)->after('numero_compte_ecole');
        });

        Schema::create('banque_mouvements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('banque_id')->constrained('banques')->restrictOnDelete();
            $table->enum('type', ['depot', 'retrait', 'paie']);
            $table->unsignedBigInteger('montant');
            $table->date('date_mouvement');
            $table->string('libelle', 255);
            $table->string('reference', 100)->nullable();
            $table->foreignId('bulletin_paie_id')->nullable()->unique()
                ->constrained('bulletins_paie')->nullOnDelete();
            $table->foreignId('effectue_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['banque_id', 'date_mouvement']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banque_mouvements');

        Schema::table('banques', function (Blueprint $table) {
            $table->dropColumn('solde');
        });
    }
};