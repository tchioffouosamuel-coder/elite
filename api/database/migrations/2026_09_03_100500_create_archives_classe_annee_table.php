<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Détail pédagogique figé d'une classe pour une année révolue — le point de
 * vérité une fois l'année archivée, indépendant des tables vivantes qui
 * continuent d'évoluer (une classe est un gabarit permanent, réutilisé
 * l'année suivante ; un coefficient modifié plus tard ne doit jamais changer
 * rétroactivement un bulletin déjà émis).
 *
 * Les colonnes JSON sont des tableaux de scalaires construits à la main par
 * ArchivageService — jamais un dump direct de modèles Eloquent, pour ne pas
 * dépendre de leur forme future.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archives_classe_annee', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('annee_scolaire_id')->constrained('annee_scolaires')->cascadeOnDelete();
            $table->foreignId('classe_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->string('classe_nom');
            $table->string('niveau_libelle')->nullable();
            $table->foreignId('conseil_classe_id')->nullable()->constrained('conseils_classe')->nullOnDelete();
            $table->unsignedSmallInteger('effectif')->default(0);
            $table->json('roster_json');
            $table->json('notes_json');
            $table->json('absences_json');
            $table->json('discipline_json');
            $table->json('infirmerie_json');
            $table->foreignId('archive_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archive_le');
            $table->timestamps();

            $table->unique(['annee_scolaire_id', 'classe_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archives_classe_annee');
    }
};
