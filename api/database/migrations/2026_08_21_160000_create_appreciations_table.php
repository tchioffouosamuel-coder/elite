<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Référentiel d'appréciations de la maternelle.
 *
 * On n'y note pas des enfants de trois ans sur vingt : le bulletin porte des
 * « APPRECIATION CODES » — une colonne par niveau, la case du niveau atteint
 * étant coloriée. L'enseignante choisit un visage, pas un chiffre.
 *
 * Le référentiel appartient à l'établissement plutôt qu'au code : le libellé,
 * l'émoji, la couleur et l'ordre se règlent depuis l'application. Les trois
 * niveaux d'usage (acquis / en cours / non acquis) sont posés ici pour chaque
 * école de maternelle existante, afin que la saisie fonctionne sans réglage
 * préalable ; l'école les ajuste ensuite.
 */
return new class extends Migration
{
    /** Niveaux d'usage, du plus favorable au moins favorable. */
    private const DEFAUTS = [
        ['Acquis', 'Acquired', '🙂', '#16a34a', 1],
        ["En cours d'acquisition", 'In progress', '😐', '#f59e0b', 2],
        ['Non acquis', 'Not acquired', '🙁', '#dc2626', 3],
    ];

    public function up(): void
    {
        Schema::create('appreciations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('label_fr');
            $table->string('label_en')->nullable();

            // L'émoji tel qu'il figure en tête de colonne sur le bulletin.
            $table->string('emoji', 8)->nullable();

            // Couleur de la case coloriée, en hexadécimal : l'école choisit sa
            // palette, le document n'impose que le sens (favorable → défavorable).
            $table->string('couleur', 7)->default('#16a34a');

            // L'ordre fixe la position de la colonne, et donc la lecture du
            // bulletin : il doit rester stable d'un trimestre à l'autre.
            $table->unsignedTinyInteger('ordre')->default(1);
            $table->enum('statut', ['actif', 'inactif'])->default('actif');
            $table->timestamps();

            $table->index(['school_id', 'statut']);
        });

        // La note de maternelle ne porte pas de valeur chiffrée mais une
        // appréciation. `valeur` reste nullable et inutilisée sur ce chemin.
        Schema::table('notes', function (Blueprint $table) {
            $table->foreignId('appreciation_id')->nullable()->after('valeur')
                ->constrained('appreciations')->nullOnDelete();
        });

        foreach (DB::table('schools')->where('type', 'maternelle')->pluck('id') as $schoolId) {
            foreach (self::DEFAUTS as [$labelFr, $labelEn, $emoji, $couleur, $ordre]) {
                DB::table('appreciations')->insert([
                    'school_id' => $schoolId,
                    'label_fr' => $labelFr,
                    'label_en' => $labelEn,
                    'emoji' => $emoji,
                    'couleur' => $couleur,
                    'ordre' => $ordre,
                    'statut' => 'actif',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('appreciation_id');
        });

        Schema::dropIfExists('appreciations');
    }
};
