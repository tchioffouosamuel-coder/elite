<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La souscription au bus se règle comme un frais annexe : un poste de plus
     * dans le même registre de versements, pas un système de paiement à part.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $this->recreerTable(true);

            return;
        }

        DB::statement(
            "ALTER TABLE versement_lignes MODIFY affectation ENUM('scolarite', 'frais_annexe', 'report_dette', 'bus') DEFAULT 'scolarite'"
        );
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $this->recreerTable(false);

            return;
        }

        DB::statement(
            "ALTER TABLE versement_lignes MODIFY affectation ENUM('scolarite', 'frais_annexe', 'report_dette') DEFAULT 'scolarite'"
        );
    }

    private function recreerTable(bool $avecBus): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::rename('versement_lignes', 'versement_lignes_legacy');

        Schema::create('versement_lignes', function ($table) use ($avecBus) {
            $table->id();
            $table->foreignId('versement_id')->constrained()->cascadeOnDelete();
            $table->enum('affectation', $avecBus
                ? ['scolarite', 'frais_annexe', 'report_dette', 'bus']
                : ['scolarite', 'frais_annexe', 'report_dette'])->default('scolarite');
            $table->foreignId('dossier_frais_annexe_id')->nullable()
                ->constrained('dossier_frais_annexes')->nullOnDelete();
            $table->string('libelle');
            $table->unsignedBigInteger('montant');
            $table->timestamps();
        });

        DB::statement(
            'INSERT INTO versement_lignes (id, versement_id, affectation, dossier_frais_annexe_id, libelle, montant, created_at, updated_at) '
                . 'SELECT id, versement_id, affectation, dossier_frais_annexe_id, libelle, montant, created_at, updated_at '
                . 'FROM versement_lignes_legacy'
        );

        Schema::drop('versement_lignes_legacy');
        Schema::enableForeignKeyConstraints();
    }
};
