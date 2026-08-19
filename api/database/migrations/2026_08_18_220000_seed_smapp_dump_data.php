<?php

use Illuminate\Database\Migrations\Migration;
use Database\Seeders\SmappDataSeeder;

/**
 * Migration de données : réinjecte l'intégralité du dump smapp.sql
 * (72 tables, ~6973 lignes) via SmappDataSeeder.
 *
 * IMPORTANT :
 * - Cette migration suppose que le SCHEMA des 72 tables existe déjà
 *   (créé par vos migrations de structure habituelles). Elle n'insère
 *   QUE les données.
 * - Les IDs sont réinjectés tels quels (mêmes valeurs que dans le dump).
 *   Si les tables contiennent déjà des données avec des IDs qui se
 *   chevauchent, l'insertion échouera sur les clés primaires/uniques.
 *   Exécutez cette migration sur une base vide ou fraîchement migrée.
 * - down() supprime les lignes insérées, table par table, dans l'ordre
 *   inverse, avec les FK checks désactivés.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Rejouer un dump de production (72 tables, ~6973 lignes avec leurs
        // identifiants d'origine) dans la base de test n'a pas de sens : elle
        // est recréée à vide à chaque test, et les identifiants du dump
        // entrent en collision avec les référentiels que les migrations de
        // structure viennent d'alimenter (fonctions, niveaux…).
        if (app()->environment('testing')) {
            return;
        }

        (new SmappDataSeeder())->run();
    }

    public function down(): void
    {
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();

        $tables = [
            'visites_infirmerie', 'versement_lignes', 'versements', 'settings', 'sessions',
            'school_niveau', 'sanctions', 'role_has_permissions', 'revendications', 'remunerations',
            'presences', 'personal_access_tokens', 'password_reset_tokens', 'notifications_internes',
            'notes', 'model_has_roles', 'model_has_permissions', 'migrations', 'lecon_seance',
            'seances', 'jobs', 'job_batches', 'inventaire_articles', 'grilles_frais', 'fonctions',
            'fonction_permission', 'permissions', 'failed_jobs', 'evaluation_questions', 'evaluations',
            'progression_items', 'sequences', 'emplois_du_temps', 'eleve_tuteur', 'tuteurs',
            'ecritures_comptables', 'dossier_frais_annexes', 'frais_annexes', 'dossiers_scolarite',
            'document_references', 'depenses', 'comptes_comptables', 'champs_personnalises',
            'classes', 'classe_matieres', 'matieres', 'cache_locks', 'cache', 'bus_affectations',
            'bus_arrets', 'bus_trajets', 'bus_vehicules', 'bulletin_paie_lignes', 'bulletins_paie',
            'avance_remboursements', 'avances_salaire', 'annonces', 'activity_logs',
            'absence_trimestres', 'trimestres', 'eleves', 'niveau_scolaires', 'personnels',
            'users', 'niveaux', 'sous_systemes', 'fonction_referentiel', 'departements',
            'annee_scolaires', 'roles', 'schools', 'complexes',
        ];

        foreach ($tables as $table) {
            \Illuminate\Support\Facades\DB::table($table)->truncate();
        }

        \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();
    }
};