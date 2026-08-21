<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Compte de produit des ventes du point de vente, et son pendant en charge
 * pour le stock acheté.
 *
 * Sans eux, une vente de fournitures serait créditée sur « Frais annexes et
 * transport » et se confondrait dans l'état de synthèse avec le transport
 * scolaire — l'établissement ne pourrait plus lire la marge de sa boutique.
 *
 * `updateOrInsert` par code, comme la refonte du plan comptable : la migration
 * doit être rejouable et ne rien écraser d'un plan déjà ajusté à la main.
 */
return new class extends Migration
{
    private const COMPTES = [
        '707' => ['Ventes de fournitures scolaires', 'School Supplies Sales', 7, 'credit'],
        '607' => ['Achats de fournitures destinées à la vente', 'Supplies Purchased for Resale', 6, 'debit'],
    ];

    public function up(): void
    {
        foreach (self::COMPTES as $code => [$libelle, $libelleEn, $classe, $sens]) {
            DB::table('comptes_comptables')->updateOrInsert(
                ['code' => $code],
                [
                    'libelle' => $libelle,
                    'libelle_en' => $libelleEn,
                    'classe' => $classe,
                    'sens' => $sens,
                    // Exploitation : la boutique use l'exercice, elle ne
                    // construit pas d'actif immobilisé.
                    'nature' => 'exploitation',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }

    public function down(): void
    {
        // Seulement s'ils n'ont rien porté : supprimer un compte mouvementé
        // laisserait des écritures orphelines dans le journal.
        foreach (array_keys(self::COMPTES) as $code) {
            $id = DB::table('comptes_comptables')->where('code', $code)->value('id');

            if ($id === null || DB::table('ecritures_comptables')->where('compte_comptable_id', $id)->exists()) {
                continue;
            }

            DB::table('comptes_comptables')->where('id', $id)->delete();
        }
    }
};
