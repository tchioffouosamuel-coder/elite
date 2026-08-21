<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Permet de ranger le matériel médical (pansements, médicaments...) à part du mobilier/informatique existant. */
return new class extends Migration
{
    public function up(): void
    {
        // SQLite (tests) ne sait pas modifier un enum en place ; MySQL a besoin
        // du SQL brut pour ça (Doctrine DBAL ne gère pas non plus les enums).
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE inventaire_articles MODIFY categorie ENUM('mobilier', 'informatique', 'pedagogique', 'sport', 'medical', 'autre') NOT NULL DEFAULT 'autre'");
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::table('inventaire_articles')->where('categorie', 'medical')->update(['categorie' => 'autre']);
            DB::statement("ALTER TABLE inventaire_articles MODIFY categorie ENUM('mobilier', 'informatique', 'pedagogique', 'sport', 'autre') NOT NULL DEFAULT 'autre'");
        }
    }
};
