<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Règle par défaut de preuve de présence pour « Ma journée », définie par
     * la direction au niveau école (+ sous-système, en option) plutôt
     * qu'agent par agent : `methode_validation_seance` sur le dossier de
     * l'agent reste possible mais devient une exception à cette règle
     * (nullable = hérite d'ici). `delai_valeur`/`delai_unite` remplacent la
     * fenêtre de correction fixe de 15 minutes (`Seance::MINUTES_VERROUILLAGE_APPEL`).
     */
    public function up(): void
    {
        Schema::create('regles_validation_seances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            // Null = s'applique à tous les sous-systèmes de l'école, sauf règle plus spécifique.
            $table->foreignId('sous_systeme_id')->nullable()->constrained('sous_systemes')->cascadeOnDelete();
            $table->enum('methode_validation', ['qr', 'code', 'libre'])->default('qr');
            $table->unsignedInteger('delai_valeur')->default(15);
            $table->enum('delai_unite', ['minutes', 'jours', 'semaines'])->default('minutes');
            $table->timestamps();

            $table->unique(['school_id', 'sous_systeme_id']);
        });

        // `change()` sur une colonne enum exige doctrine/dbal (absent du
        // projet) : on recrée la colonne plutôt que de la modifier en place.
        Schema::table('personnels', function (Blueprint $table) {
            $table->dropColumn('methode_validation_seance');
        });
        Schema::table('personnels', function (Blueprint $table) {
            $table->enum('methode_validation_seance', ['qr', 'code', 'libre'])->nullable()->after('numero_compte');
        });
    }

    public function down(): void
    {
        Schema::table('personnels', function (Blueprint $table) {
            $table->dropColumn('methode_validation_seance');
        });
        Schema::table('personnels', function (Blueprint $table) {
            $table->enum('methode_validation_seance', ['qr', 'code', 'libre'])->default('qr')->after('numero_compte');
        });

        Schema::dropIfExists('regles_validation_seances');
    }
};
