<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('nom', 100);
            $table->string('description')->nullable();
            $table->unsignedInteger('capacite')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['school_id', 'nom']);
        });

        Schema::table('emplois_du_temps', function (Blueprint $table) {
            $table->foreignId('salle_id')->nullable()->after('salle')->constrained('salles')->nullOnDelete();
        });

        Schema::table('seances', function (Blueprint $table) {
            $table->foreignId('salle_id')->nullable()->after('salle')->constrained('salles')->nullOnDelete();
        });

        DB::table('emplois_du_temps')
            ->whereNotNull('salle')
            ->select(['school_id', 'salle'])
            ->distinct()
            ->orderBy('school_id')
            ->get()
            ->each(function ($row) {
                $nom = trim((string) $row->salle);
                if ($nom === '') {
                    return;
                }

                $salleId = DB::table('salles')->where('school_id', $row->school_id)->where('nom', $nom)->value('id');
                if (! $salleId) {
                    $salleId = DB::table('salles')->insertGetId([
                        'school_id' => $row->school_id,
                        'nom' => $nom,
                        'active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('emplois_du_temps')
                    ->where('school_id', $row->school_id)
                    ->where('salle', $row->salle)
                    ->update(['salle_id' => $salleId, 'salle' => $nom]);

                DB::table('seances')
                    ->where('school_id', $row->school_id)
                    ->where('salle', $row->salle)
                    ->update(['salle_id' => $salleId, 'salle' => $nom]);
            });
    }

    public function down(): void
    {
        Schema::table('seances', fn(Blueprint $table) => $table->dropForeign(['salle_id']));
        Schema::table('seances', fn(Blueprint $table) => $table->dropColumn('salle_id'));
        Schema::table('emplois_du_temps', fn(Blueprint $table) => $table->dropForeign(['salle_id']));
        Schema::table('emplois_du_temps', fn(Blueprint $table) => $table->dropColumn('salle_id'));
        Schema::dropIfExists('salles');
    }
};
