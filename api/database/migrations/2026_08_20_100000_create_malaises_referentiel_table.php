<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Référentiel des malaises/symptômes que l'infirmier peut cocher lors d'une
 * visite (fièvre, maux de tête...), sur le même modèle que fonction_referentiel :
 * une liste par établissement, éditable par l'infirmier plutôt que figée dans
 * le code, avec un jeu de valeurs par défaut pour ne pas partir d'une liste vide.
 */
return new class extends Migration
{
    private const DEFAUTS = [
        ['Fièvre', 'Fever'],
        ['Maux de tête', 'Headache'],
        ['Maux de ventre', 'Stomach ache'],
        ['Nausées', 'Nausea'],
        ['Vomissements', 'Vomiting'],
        ['Diarrhée', 'Diarrhea'],
        ['Toux', 'Cough'],
        ['Rhume', 'Cold'],
        ['Mal de gorge', 'Sore throat'],
        ['Plaie ou blessure', 'Wound or injury'],
        ['Chute', 'Fall'],
        ["Réaction allergique", 'Allergic reaction'],
        ["Piqûre d'insecte", 'Insect bite'],
        ["Malaise / évanouissement", 'Fainting spell'],
        ['Douleur dentaire', 'Toothache'],
        ['Douleur auriculaire', 'Earache'],
        ['Fatigue / somnolence', 'Fatigue / drowsiness'],
        ["Crise d'asthme", 'Asthma attack'],
        ['Saignement de nez', 'Nosebleed'],
        ['Autre', 'Other'],
    ];

    public function up(): void
    {
        Schema::create('malaises_referentiel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('label_fr');
            $table->string('label_en')->nullable();
            $table->unique(['school_id', 'label_fr']);
        });

        foreach (DB::table('schools')->pluck('id') as $schoolId) {
            foreach (self::DEFAUTS as [$labelFr, $labelEn]) {
                DB::table('malaises_referentiel')->updateOrInsert(
                    ['school_id' => $schoolId, 'label_fr' => $labelFr],
                    ['label_en' => $labelEn]
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('malaises_referentiel');
    }
};
