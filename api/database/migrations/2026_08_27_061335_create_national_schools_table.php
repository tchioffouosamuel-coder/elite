<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('national_schools', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // ex: AD08270B01, 10030001
            $table->string('name');
            $table->timestamps();

            $table->index('name');
        });

        // Seed the reference data right after creating the table.
        // Kept in the migration so a fresh `migrate` always leaves the
        // table populated; the parsing logic itself lives in the seeder
        // so it can also be re-run independently via `db:seed`.
        (new \Database\Seeders\NationalSchoolSeeder())->run();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('national_schools');
    }
};