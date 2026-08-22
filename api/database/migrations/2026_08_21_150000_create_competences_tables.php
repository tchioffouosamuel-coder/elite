<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compétences évaluées du primaire et de la maternelle.
 *
 * Jusqu'ici la matière portait à la fois le contenu enseigné et le barème :
 * « INITIATION TO READING » était noté sur 20, réparti en oral/écrit/savoir-être.
 * Le bulletin listait donc une vingtaine de lignes là où le livret officiel en
 * attend quelques-unes — la lecture, l'écriture et la langue relèvent d'une même
 * compétence « Langue et communication », qui est ce que l'on évalue.
 *
 * La compétence reprend donc tout ce qui relève de la notation ; la matière ne
 * garde que le contenu (cf. la migration suivante). Les deux restent utiles :
 * on évalue par compétence, mais on enseigne, on planifie l'emploi du temps et
 * on suit la progression par matière.
 *
 * Le secondaire n'est pas concerné : il note la matière avec un coefficient,
 * et ses tables ne bougent pas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('label_fr');
            $table->string('label_en')->nullable();
            $table->string('abbreviation')->nullable();

            // Barème propre de la compétence : la moyenne générale ramène le
            // total obtenu sur 20 (`$av = $total * 20 / Σ barèmes`), le barème
            // joue donc le rôle du coefficient du secondaire.
            $table->unsignedSmallInteger('notation')->default(20);

            // Oral, écrit et savoir-être sont systématiques ; le volet pratique
            // ne concerne que les compétences qui s'y prêtent (sport, jeux…).
            $table->boolean('evalue_pratique')->default(false);

            // Points de chaque volet : {"oral": 10, "ecrit": 5, "savoir_etre": 5}.
            // Leur somme vaut `notation`, ce que l'API valide à l'écriture.
            $table->json('repartition_volets')->nullable();

            // Ordre d'apparition au bulletin, figé pour qu'il reste comparable
            // d'un trimestre à l'autre.
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->enum('statut', ['actif', 'inactif'])->default('actif');
            $table->timestamps();

            $table->index(['school_id', 'statut']);
        });

        Schema::create('classe_competences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('competence_id')->constrained('competences')->cascadeOnDelete();

            // L'enseignant est porté par la compétence, pas par chaque matière :
            // au primaire le titulaire tient l'ensemble du bloc. Les affectations
            // de matières créées en cascade héritent de cet agent.
            $table->foreignId('personnel_id')->nullable()->constrained('personnels')->nullOnDelete();

            $table->unsignedTinyInteger('groupe')->default(1);
            $table->enum('statut', ['actif', 'inactif'])->default('actif');
            $table->timestamps();

            $table->unique(['classe_id', 'competence_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classe_competences');
        Schema::dropIfExists('competences');
    }
};
