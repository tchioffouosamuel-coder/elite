<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un bulletin doit pouvoir se relire des années plus tard et dire sous quel
 * barème il a été arrêté. L'établissement en applique deux — le barème légal
 * et celui de ses registres — et ils ne répartissent pas la charge de la même
 * façon : dans le second, l'agent perçoit son montant négocié entier et
 * l'école absorbe la part salariale.
 *
 * Sans cette trace, la ventilation comptable d'un bulletin ancien devrait
 * deviner qui a supporté quoi, et le journal cesserait de s'équilibrer dès que
 * le réglage change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bulletins_paie', function (Blueprint $table) {
            $table->string('bareme')->nullable()->after('charges_patronales');
            $table->boolean('charges_salariales_a_charge_employeur')
                ->default(false)->after('bareme');
        });
    }

    public function down(): void
    {
        Schema::table('bulletins_paie', function (Blueprint $table) {
            $table->dropColumn(['bareme', 'charges_salariales_a_charge_employeur']);
        });
    }
};
