<?php

namespace Database\Seeders\SmappData;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder généré automatiquement depuis le dump smapp.sql
 * Table: migrations
 * Lignes: 82
 */
class MigrationsSeeder extends Seeder
{
    public function run(): void
    {
        $table = 'migrations';

        $rows = [
            ['id' => 1, 'migration' => '0001_01_01_000000_create_users_table', 'batch' => 1],
            ['id' => 2, 'migration' => '0001_01_01_000001_create_cache_table', 'batch' => 1],
            ['id' => 3, 'migration' => '0001_01_01_000002_create_jobs_table', 'batch' => 1],
            ['id' => 4, 'migration' => '2026_08_09_201925_create_personal_access_tokens_table', 'batch' => 1],
            ['id' => 5, 'migration' => '2026_08_09_201926_create_permission_tables', 'batch' => 1],
            ['id' => 6, 'migration' => '2026_08_09_202252_create_schools_table', 'batch' => 1],
            ['id' => 7, 'migration' => '2026_08_09_202253_create_niveaux_table', 'batch' => 1],
            ['id' => 8, 'migration' => '2026_08_09_202254_create_annee_scolaires_table', 'batch' => 1],
            ['id' => 9, 'migration' => '2026_08_09_202255_create_trimestres_table', 'batch' => 1],
            ['id' => 10, 'migration' => '2026_08_09_202516_add_tenant_and_profile_columns_to_users_table', 'batch' => 1],
            ['id' => 11, 'migration' => '2026_08_09_203008_create_departements_table', 'batch' => 1],
            ['id' => 12, 'migration' => '2026_08_09_203009_create_personnels_table', 'batch' => 1],
            ['id' => 13, 'migration' => '2026_08_09_203536_create_classes_table', 'batch' => 1],
            ['id' => 14, 'migration' => '2026_08_09_203537_create_eleves_table', 'batch' => 1],
            ['id' => 15, 'migration' => '2026_08_09_203538_create_tuteurs_table', 'batch' => 1],
            ['id' => 16, 'migration' => '2026_08_09_203539_create_eleve_tuteur_table', 'batch' => 1],
            ['id' => 17, 'migration' => '2026_08_09_230049_create_settings_table', 'batch' => 1],
            ['id' => 18, 'migration' => '2026_08_09_230135_create_matieres_table', 'batch' => 1],
            ['id' => 19, 'migration' => '2026_08_09_230136_create_classe_matieres_table', 'batch' => 1],
            ['id' => 20, 'migration' => '2026_08_09_230137_add_head_personnel_id_to_departements_table', 'batch' => 1],
            ['id' => 21, 'migration' => '2026_08_09_230923_create_sequences_table', 'batch' => 1],
            ['id' => 22, 'migration' => '2026_08_09_230924_create_notes_table', 'batch' => 1],
            ['id' => 23, 'migration' => '2026_08_09_231809_create_absence_trimestres_table', 'batch' => 1],
            ['id' => 24, 'migration' => '2026_08_09_232057_create_sanctions_table', 'batch' => 1],
            ['id' => 25, 'migration' => '2026_08_10_090000_create_complexes_table', 'batch' => 1],
            ['id' => 26, 'migration' => '2026_08_10_090100_add_complexe_and_type_to_schools_table', 'batch' => 1],
            ['id' => 27, 'migration' => '2026_08_10_090200_add_responsables_to_classes_table', 'batch' => 1],
            ['id' => 28, 'migration' => '2026_08_10_090300_create_emplois_du_temps_table', 'batch' => 1],
            ['id' => 29, 'migration' => '2026_08_10_090400_create_seances_table', 'batch' => 1],
            ['id' => 30, 'migration' => '2026_08_10_090500_create_presences_table', 'batch' => 1],
            ['id' => 31, 'migration' => '2026_08_10_100000_create_niveau_scolaires_table', 'batch' => 1],
            ['id' => 32, 'migration' => '2026_08_10_100100_add_primaire_columns_to_classes_table', 'batch' => 1],
            ['id' => 33, 'migration' => '2026_08_10_100200_add_primaire_columns_to_matieres_table', 'batch' => 1],
            ['id' => 34, 'migration' => '2026_08_10_100300_add_composante_to_notes_table', 'batch' => 1],
            ['id' => 35, 'migration' => '2026_08_10_120000_add_code_examen_to_classes_table', 'batch' => 1],
            ['id' => 36, 'migration' => '2026_08_11_090000_create_progression_items_table', 'batch' => 2],
            ['id' => 37, 'migration' => '2026_08_11_090100_create_lecon_seance_table', 'batch' => 2],
            ['id' => 38, 'migration' => '2026_08_11_090200_add_motif_to_presences_table', 'batch' => 2],
            ['id' => 39, 'migration' => '2026_08_11_140000_detacher_niveaux_des_ecoles_maternelles', 'batch' => 3],
            ['id' => 40, 'migration' => '2026_08_11_160000_create_fonctions_table', 'batch' => 4],
            ['id' => 41, 'migration' => '2026_08_11_160100_rattacher_personnels_aux_fonctions', 'batch' => 4],
            ['id' => 42, 'migration' => '2026_08_12_000000_create_sous_systemes_table', 'batch' => 4],
            ['id' => 43, 'migration' => '2026_08_12_000001_add_sigle_sous_systeme_to_classes_table', 'batch' => 4],
            ['id' => 44, 'migration' => '2026_08_12_000002_fix_sous_systemes_unique_constraint', 'batch' => 5],
            ['id' => 45, 'migration' => '2026_08_12_000003_remove_ordre_from_sous_systemes_table', 'batch' => 6],
            ['id' => 46, 'migration' => '2026_08_12_000000_add_label_and_sous_systeme_to_niveaux', 'batch' => 7],
            ['id' => 47, 'migration' => '2026_08_12_000001_add_school_id_to_niveaux', 'batch' => 8],
            ['id' => 48, 'migration' => '2026_08_12_010000_create_fonction_referentiel_table', 'batch' => 9],
            ['id' => 49, 'migration' => '2026_08_12_020000_add_repartition_volets_to_matieres_table', 'batch' => 10],
            ['id' => 50, 'migration' => '2026_08_12_000000_add_fields_to_eleves_table', 'batch' => 11],
            ['id' => 51, 'migration' => '2026_08_12_030000_merge_nom_prenom_eleves', 'batch' => 12],
            ['id' => 52, 'migration' => '2026_08_12_030100_merge_nom_prenom_personnels', 'batch' => 12],
            ['id' => 53, 'migration' => '2026_08_12_030200_merge_nom_prenom_tuteurs', 'batch' => 12],
            ['id' => 54, 'migration' => '2026_08_12_000002_remove_label_from_niveaux', 'batch' => 13],
            ['id' => 55, 'migration' => '2026_08_12_053319_make_niveau_id_nullable_on_classes_table', 'batch' => 14],
            ['id' => 56, 'migration' => '2026_08_15_000000_create_fonction_permission_table', 'batch' => 15],
            ['id' => 57, 'migration' => '2026_08_16_000000_add_etat_civil_to_personnels_table', 'batch' => 16],
            ['id' => 58, 'migration' => '2026_08_16_000100_add_affectation_to_personnels_table', 'batch' => 17],
            ['id' => 59, 'migration' => '2026_08_16_000000_change_settings_value_to_text', 'batch' => 18],
            ['id' => 60, 'migration' => '2026_08_16_000001_create_document_references_table', 'batch' => 19],
            ['id' => 61, 'migration' => '2026_08_16_000200_add_doit_changer_mot_de_passe_to_users_table', 'batch' => 20],
            ['id' => 62, 'migration' => '2026_08_16_100000_create_finance_scolarite_tables', 'batch' => 21],
            ['id' => 63, 'migration' => '2026_08_16_100100_create_finance_depenses_tables', 'batch' => 21],
            ['id' => 64, 'migration' => '2026_08_16_100200_create_finance_paie_tables', 'batch' => 21],
            ['id' => 65, 'migration' => '2026_08_17_000000_add_preparation_fields_to_progression_items_table', 'batch' => 22],
            ['id' => 66, 'migration' => '2026_08_17_000100_create_evaluations_tables', 'batch' => 22],
            ['id' => 67, 'migration' => '2026_08_17_000200_add_observations_and_champs_to_seances', 'batch' => 22],
            ['id' => 68, 'migration' => '2026_08_17_000300_create_champs_personnalises_table', 'batch' => 22],
            ['id' => 69, 'migration' => '2026_08_17_000400_add_qr_token_to_classes_table', 'batch' => 22],
            ['id' => 70, 'migration' => '2026_08_17_000500_create_visites_infirmerie_table', 'batch' => 22],
            ['id' => 71, 'migration' => '2026_08_17_100000_create_bus_tables', 'batch' => 22],
            ['id' => 72, 'migration' => '2026_08_17_100100_add_option_trajet_to_bus_affectations_table', 'batch' => 23],
            ['id' => 73, 'migration' => '2026_08_17_100200_add_numero_permis_to_personnels_table', 'batch' => 23],
            ['id' => 74, 'migration' => '2026_08_17_200000_add_tarifs_to_bus_trajets_table', 'batch' => 24],
            ['id' => 75, 'migration' => '2026_08_17_200100_add_bus_to_versement_lignes_affectation', 'batch' => 24],
            ['id' => 76, 'migration' => '2026_08_18_100000_create_avances_salaire_tables', 'batch' => 25],
            ['id' => 77, 'migration' => '2026_08_18_100100_create_inventaire_articles_table', 'batch' => 25],
            ['id' => 78, 'migration' => '2026_08_18_200000_create_annonces_table', 'batch' => 26],
            ['id' => 79, 'migration' => '2026_08_18_200100_create_notifications_internes_table', 'batch' => 26],
            ['id' => 80, 'migration' => '2026_08_18_200200_enrichir_sanctions_table', 'batch' => 27],
            ['id' => 81, 'migration' => '2026_08_17_230909_create_revendications_table', 'batch' => 28],
            ['id' => 82, 'migration' => '2026_08_18_210000_create_activity_logs_table', 'batch' => 29],
        ];
        DB::table($table)->insert($rows);

    }
}
