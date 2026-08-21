<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Refonte du plan de comptes sur la nomenclature réellement tenue par
 * l'établissement.
 *
 * Le plan livré jusqu'ici était un plan OHADA générique dont les codes
 * contredisaient ceux de l'« État de synthèse des charges et dépenses » :
 * 611 y valait « Loyers » quand l'école y porte ses tables-bancs, 664
 * « Charges sociales » quand l'école y porte la Fenasco, et ni 700
 * (inscriptions), ni 624 (construction), ni 699 (amortissements) n'existaient.
 * Un état produit sur cette base ne pouvait pas se rapprocher du document que
 * lit le comptable.
 *
 * Trois notions manquaient par ailleurs, et ce sont elles qui décident du
 * résultat :
 *
 *  - `nature` sépare ce qui use l'exercice (exploitation) de ce qui le
 *    dépasse (investissement immobilier, mouvements de capital). Sur onze
 *    exercices, le compte 624 pèse 38,7 % des dépenses et le « dépôt initial »
 *    25,1 millions : les confondre avec des charges transforme un excédent
 *    d'exploitation cumulé de +188 millions en déficit de −38,6 millions.
 *  - `assiette` marque les trois prélèvements qui ne s'arbitrent pas mais se
 *    calculent — SEDUC, Fenasco et assurance scolaire, à tant par élève.
 *  - `ordre` fige la présentation de l'état, qui doit rester comparable d'un
 *    exercice à l'autre.
 */
return new class extends Migration
{
    /**
     * Anciens codes vers leur équivalent dans la nomenclature de l'école.
     * Les comptes techniques (classes 1 à 5 : tiers, trésorerie, capitaux) ne
     * figurent pas au document et ne bougent pas.
     */
    private const REMAP = [
        '601' => '614',  // Achats de fournitures      → Fournitures diverses
        '602' => '606',  // Matériel pédagogique       → Matériel pédagogique et informatique
        '611' => '650',  // Loyers                     → Autres dépenses (l'école est propriétaire)
        '613' => '624',  // Entretien et maintenance   → Construction et entretien des bâtiments
        '621' => '652',  // Services extérieurs        → Honoraires experts divers
        '631' => '626',  // Eau                        → Factures eaux et électricité
        '632' => '626',  // Électricité                → Factures eaux et électricité
        '641' => '663',  // Impôts et taxes            → Charges fiscales
        '664' => '662',  // Charges sociales           → Cotisation CNPS
        '702' => '700',  // Frais d'inscription        → Inscriptions
        '703' => '703',  // Frais d'examen             → Frais annexes et transport
        '704' => '703',  // Frais de pension           → Frais annexes et transport
        '706' => '703',  // Autres services scolaires  → Frais annexes et transport
        '751' => '721',  // Subventions reçues         → Subventions de l'État et autres
        '758' => '721',  // Dons et contributions      → Subventions de l'État et autres
    ];

    /** Comptes cibles du remappage, créés ici si le seeder n'est pas encore passé. */
    private const CIBLES = [
        '606' => ['Matériel pédagogique et informatique', 6],
        '614' => ['Fournitures diverses', 6],
        '624' => ['Construction et entretien/réparation bâtiments', 6],
        '626' => ['Factures eaux et électricité', 6],
        '650' => ['Autres dépenses', 6],
        '652' => ['Honoraires experts divers', 6],
        '662' => ['Cotisation CNPS', 6],
        '663' => ['Charges fiscales', 6],
        '700' => ['Inscriptions', 7],
        '703' => ['Frais annexes et transport', 7],
        '721' => ["Subventions de l'État et autres", 7],
    ];

    public function up(): void
    {
        Schema::table('comptes_comptables', function (Blueprint $table) {
            /*
             * Ce qu'un compte fait au résultat de l'exercice :
             *  - exploitation  : il entre dans la balance de fin d'exercice ;
             *  - investissement: il construit un actif, et ne pèse sur le
             *                    résultat que par son amortissement ;
             *  - capital       : apport ou dépôt de l'exploitant, mouvement de
             *                    haut de bilan qui n'est ni charge ni produit.
             */
            $table->enum('nature', ['exploitation', 'investissement', 'capital'])
                ->default('exploitation')->after('sens');

            // « par_eleve » : le montant ne se saisit pas, il se calcule sur
            // l'effectif de l'exercice au tarif unitaire ci-dessous.
            $table->enum('assiette', ['libre', 'par_eleve'])->default('libre')->after('nature');
            $table->unsignedInteger('montant_unitaire')->nullable()->after('assiette');

            $table->unsignedSmallInteger('ordre')->default(0)->after('montant_unitaire');
        });

        // Les cibles doivent exister avant qu'on y pointe les écritures.
        foreach (self::CIBLES as $code => [$libelle, $classe]) {
            DB::table('comptes_comptables')->updateOrInsert(
                ['code' => $code],
                [
                    'libelle' => $libelle,
                    'classe' => $classe,
                    'sens' => $classe === 6 ? 'debit' : 'credit',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }

        $ids = DB::table('comptes_comptables')->pluck('id', 'code');

        foreach (self::REMAP as $ancien => $nouveau) {
            if (! isset($ids[$ancien], $ids[$nouveau]) || $ids[$ancien] === $ids[$nouveau]) {
                continue;
            }

            foreach (['ecritures_comptables', 'depenses'] as $table) {
                DB::table($table)
                    ->where('compte_comptable_id', $ids[$ancien])
                    ->update(['compte_comptable_id' => $ids[$nouveau]]);
            }

            // L'ancien code disparaît : le laisser actif le proposerait encore
            // à la saisie, avec un libellé que le document ne connaît pas.
            DB::table('comptes_comptables')->where('id', $ids[$ancien])->delete();
        }
    }

    public function down(): void
    {
        Schema::table('comptes_comptables', function (Blueprint $table) {
            $table->dropColumn(['nature', 'assiette', 'montant_unitaire', 'ordre']);
        });
    }
};
