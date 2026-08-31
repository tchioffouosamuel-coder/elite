<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * `SyncController::lot()` trie et pagine CHAQUE entité du registre sur
 * `ORDER BY updated_at, id LIMIT 501`, à chaque page, pour chaque appel
 * `/sync` — mobile comme desktop. Aucune des 45 tables du registre n'avait
 * d'index couvrant `updated_at` : sur les tables volumineuses (`notes`,
 * `presences`, `seances`, `versements`, `ecritures_comptables`...), chaque
 * page déclenche un balayage complet + tri sur disque. Observé en
 * conditions réelles sur l'établissement le plus ancien/volumineux : une
 * page qui prend 17 secondes à répondre pour 330 Ko de données, largement
 * suffisant pour déclencher des timeouts réseau en cascade alors que la
 * connexion elle-même n'a rien d'anormal — le goulot est le calcul de la
 * requête côté serveur, pas le transfert.
 */
return new class extends Migration
{
    private const TABLES = [
        'annee_scolaires', 'trimestres', 'sequences', 'niveaux', 'matieres', 'competences',
        'classes', 'classe_matieres', 'emplois_du_temps', 'progression_items',
        'eleves', 'personnels', 'fonction_referentiel',
        'seances', 'presences', 'notes', 'sanctions',
        'annonces', 'notifications_internes',
        'grilles_frais', 'frais_annexes', 'dossiers_scolarite', 'dossier_frais_annexes',
        'versements', 'versement_lignes', 'moratoires', 'remises', 'dettes_anterieures', 'tranches_scolarite',
        'comptes_comptables', 'ecritures_comptables', 'budgets_fonctionnement',
        'avances_salaire', 'demandes_avance_salaire', 'budgets_personnel',
        'bus_vehicules', 'bus_trajets', 'bus_arrets', 'bus_affectations', 'bus_versements',
        'visites_infirmerie', 'visite_infirmerie_materiels',
        'inventaire_articles', 'infrastructures', 'equipements_mobiliers',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || $this->possedeDejaUnIndexSur($table, 'updated_at')) {
                continue;
            }

            Schema::table($table, fn ($t) => $t->index('updated_at'));
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || ! $this->possedeDejaUnIndexSur($table, 'updated_at')) {
                continue;
            }

            Schema::table($table, fn ($t) => $t->dropIndex(['updated_at']));
        }
    }

    /** Idempotent : une exécution partielle précédente ne doit pas faire échouer un ré-essai. */
    private function possedeDejaUnIndexSur(string $table, string $colonne): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if ($index['columns'] === [$colonne] || (in_array($colonne, $index['columns'], true) && $index['columns'][0] === $colonne)) {
                return true;
            }
        }

        return false;
    }
};
