<?php

namespace Database\Seeders\SmappData;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder généré automatiquement depuis le dump smapp.sql
 * Table: comptes_comptables
 * Lignes: 60
 */
class ComptesComptablesSeeder extends Seeder
{
    public function run(): void
    {
        $table = 'comptes_comptables';

        $rows = [
            ['id' => 1, 'code' => '101', 'libelle' => 'Capital / Fonds de dotation', 'libelle_en' => 'Capital / Founding Fund', 'classe' => 1, 'sens' => 'credit', 'is_active' => 1, 'created_at' => '2026-08-16 13:25:25', 'updated_at' => '2026-08-16 13:25:25'],
            ['id' => 2, 'code' => '106', 'libelle' => 'Réserves', 'libelle_en' => 'Reserves', 'classe' => 1, 'sens' => 'credit', 'is_active' => 1, 'created_at' => '2026-08-16 13:25:25', 'updated_at' => '2026-08-16 13:25:25'],
            ['id' => 3, 'code' => '110', 'libelle' => 'Report à nouveau', 'libelle_en' => 'Retained Earnings', 'classe' => 1, 'sens' => 'credit', 'is_active' => 1, 'created_at' => '2026-08-16 13:25:25', 'updated_at' => '2026-08-16 13:25:25'],
            ['id' => 4, 'code' => '130', 'libelle' => 'Subventions et dons', 'libelle_en' => 'Grants and Donations', 'classe' => 1, 'sens' => 'credit', 'is_active' => 1, 'created_at' => '2026-08-16 13:25:25', 'updated_at' => '2026-08-16 13:25:25'],
            ['id' => 5, 'code' => '161', 'libelle' => 'Emprunts bancaires', 'libelle_en' => 'Bank Loans', 'classe' => 1, 'sens' => 'credit', 'is_active' => 1, 'created_at' => '2026-08-16 13:25:25', 'updated_at' => '2026-08-16 13:25:25'],
            ['id' => 6, 'code' => '166', 'libelle' => 'Autres emprunts', 'libelle_en' => 'Other Borrowings', 'classe' => 1, 'sens' => 'credit', 'is_active' => 1, 'created_at' => '2026-08-16 13:25:25', 'updated_at' => '2026-08-16 13:25:25'],
            ['id' => 7, 'code' => '211', 'libelle' => 'Frais de développement', 'libelle_en' => 'Development Costs', 'classe' => 2, 'sens' => 'debit', 'is_active' => 1, 'created_at' => '2026-08-16 13:25:25', 'updated_at' => '2026-08-16 13:25:25'],
            ['id' => 8, 'code' => '213', 'libelle' => 'Logiciels', 'libelle_en' => 'Software', 'classe' => 2, 'sens' => 'debit', 'is_active' => 1, 'created_at' => '2026-08-16 13:25:25', 'updated_at' => '2026-08-16 13:25:25'],
            ['id' => 9, 'code' => '221', 'libelle' => 'Terrains', 'libelle_en' => 'Land', 'classe' => 2, 'sens' => 'debit', 'is_active' => 1, 'created_at' => '2026-08-16 13:25:25', 'updated_at' => '2026-08-16 13:25:25'],
            ['id' => 10, 'code' => '222', 'libelle' => 'Bâtiments', 'libelle_en' => 'Buildings', 'classe' => 2, 'sens' => 'debit', 'is_active' => 1, 'created_at' => '2026-08-16 13:25:25', 'updated_at' => '2026-08-16 13:25:25'],
            ['id' => 11, 'code' => '241', 'libelle' => 'Mobilier et équipements', 'libelle_en' => 'Furniture and Equipment', 'classe' => 2, 'sens' => 'debit', 'is_active' => 1, 'created_at' => '2026-08-16 13:25:25', 'updated_at' => '2026-08-16 13:25:25'],
            ['id' => 12, 'code' => '245', 'libelle' => 'Matériel de transport', 'libelle_en' => 'Transport Equipment', 'classe' => 2, 'sens' => 'debit', 'is_active' => 1, 'created_at' => '2026-08-16 13:25:25', 'updated_at' => '2026-08-16 13:25:25'],
            ['id' => 13, 'code' => '246', 'libelle' => 'Matériel de bureau', 'libelle_en' => 'Office Equipment', 'classe' => 2, 'sens' => 'debit', 'is_active' => 1, 'created_at' => '2026-08-16 13:25:25', 'updated_at' => '2026-08-16 13:25:25'],
            ['id' => 14, 'code' => '281', 'libelle' => 'Amortissements', 'libelle_en' => 'Depreciation Accounts', 'classe' => 2, 'sens' => 'credit', 'is_active' => 1, 'created_at' => '2026-08-16 13:25:25', 'updated_at' => '2026-08-16 13:25:25'],
            ['id' => 15, 'code' => '311', 'libelle' => 'Matériel pédagogique', 'libelle_en' => 'Teaching Materials', 'classe' => 3, 'sens' => 'debit', 'is_active' => 1, 'created_at' => '2026-08-16 13:25:25', 'updated_at' => '2026-08-16 13:25:25'],
            ['id' => 16, 'code' => '321', 'libelle' => 'Fournitures consommables', 'libelle_en' => 'Consumable Supplies', 'classe' => 3, 'sens' => 'debit', 'is_active' => 1, 'created_at' => '2026-08-16 13:25:25', 'updated_at' => '2026-08-16 13:25:25'],
            ['id' => 17, 'code' => '322', 'libelle' => 'Fournitures de bureau', 'libelle_en' => 'Office Supplies', 'classe' => 3, 'sens' => 'debit', 'is_active' => 1, 'created_at' => '2026-08-16 13:25:25', 'updated_at' => '2026-08-16 13:25:25'],
            ['id' => 18, 'code' => '331', 'libelle' => 'Travaux en cours', 'libelle_en' => 'Work in Progress', 'classe' => 3, 'sens' => 'debit', 'is_active' => 1, 'created_at' => '2026-08-16 13:25:25', 'updated_at' => '2026-08-16 13:25:25'],
            ['id' => 19, 'code' => '391', 'libelle' => 'Provisions sur stocks', 'libelle_en' => 'Inventory Provisions', 'classe' => 3, 'sens' => 'credit', 'is_active' => 1, 'created_at' => '2026-08-16 13:25:25', 'updated_at' => '2026-08-16 13:25:25'],
            ['id' => 20, 'code' => '401', 'libelle' => 'Fournisseurs', 'libelle_en' => 'Suppliers', 'classe' => 4, 'sens' => 'credit', 'is_active' => 1, 'created_at' => '2026-08-16 13:25:25', 'updated_at' => '2026-08-16 13:25:25'],
            ['id' => 21, 'code' => '408', 'libelle' => 'Factures fournisseurs non parvenues', 'libelle_en' => 'Accrued Supplier Invoices', 'classe' => 4, 'sens' => 'credit', 'is_active' => 1, 'created_at' => '2026-08-16 13:25:25', 'updated_at' => '2026-08-16 13:25:25'],
            ['id' => 22, 'code' => '411', 'libelle' => 'Élèves / Clients — créances', 'libelle_en' => 'Students / Customers Receivable', 'classe' => 4, 'sens' => 'debit', 'is_active' => 1, 'created_at' => '2026-08-16 13:25:26', 'updated_at' => '2026-08-16 13:25:26'],
            ['id' => 23, 'code' => '421', 'libelle' => 'Personnel — rémunérations dues', 'libelle_en' => 'Employees Payroll', 'classe' => 4, 'sens' => 'credit', 'is_active' => 1, 'created_at' => '2026-08-16 13:25:26', 'updated_at' => '2026-08-16 13:25:26'],
            ['id' => 24, 'code' => '431', 'libelle' => 'Sécurité sociale (CNPS)', 'libelle_en' => 'Social Security', 'classe' => 4, 'sens' => 'credit', 'is_active' => 1, 'created_at' => '2026-08-16 13:25:26', 'updated_at' => '2026-08-16 13:25:26'],
            ['id' => 25, 'code' => '441', 'libelle' => 'État — impôts et taxes', 'libelle_en' => 'State Taxes', 'classe' => 4, 'sens' => 'credit', 'is_active' => 1, 'created_at' => '2026-08-16 13:25:26', 'updated_at' => '2026-08-16 13:25:26'],
            ['id' => 26, 'code' => '443', 'libelle' => 'TVA collectée', 'libelle_en' => 'VAT Collected', 'classe' => 4, 'sens' => 'credit', 'is_active' => 1, 'created_at' => '2026-08-16 13:25:26', 'updated_at' => '2026-08-16 13:25:26'],
            ['id' => 27, 'code' => '445', 'libelle' => 'TVA récupérable', 'libelle_en' => 'VAT Recoverable', 'classe' => 4, 'sens' => 'debit', 'is_active' => 1, 'created_at' => '2026-08-16 13:25:26', 'updated_at' => '2026-08-16 13:25:26'],
            ['id' => 28, 'code' => '467', 'libelle' => 'Autres débiteurs', 'libelle_en' => 'Other Debtors', 'classe' => 4, 'sens' => 'debit', 'is_active' => 1, 'created_at' => '2026-08-16 13:25:26', 'updated_at' => '2026-08-16 13:25:26'],
            ['id' => 29, 'code' => '471', 'libelle' => 'Autres créditeurs', 'libelle_en' => 'Other Creditors', 'classe' => 4, 'sens' => 'credit', 'is_active' => 1, 'created_at' => '2026-08-16 13:25:26', 'updated_at' => '2026-08-16 13:25:26'],
            ['id' => 30, 'code' => '521', 'libelle' => 'Banque', 'libelle_en' => 'Bank Account', 'classe' => 5, 'sens' => 'debit', 'is_active' => 1, 'created_at' => '2026-08-16 13:25:26', 'updated_at' => '2026-08-16 13:25:26'],
            ['id' => 31, 'code' => '522', 'libelle' => 'Compte épargne', 'libelle_en' => 'Savings Account', 'classe' => 5, 'sens' => 'debit', 'is_active' => 1, 'created_at' => '2026-08-16 13:25:26', 'updated_at' => '2026-08-16 13:25:26'],
            ['id' => 32, 'code' => '531', 'libelle' => 'Virements internes', 'libelle_en' => 'Cash in Transit', 'classe' => 5, 'sens' => 'debit', 'is_active' => 1, 'created_at' => '2026-08-16 13:25:26', 'updated_at' => '2026-08-16 13:25:26'],
            ['id' => 33, 'code' => '571', 'libelle' => 'Caisse', 'libelle_en' => 'Cash on Hand', 'classe' => 5, 'sens' => 'debit', 'is_active' => 1, 'created_at' => '2026-08-16 13:25:26', 'updated_at' => '2026-08-16 13:25:26'],
            ['id' => 34, 'code' => '578', 'libelle' => 'Mobile Money', 'libelle_en' => 'Mobile Money Account', 'classe' => 5, 'sens' => 'debit', 'is_active' => 1, 'created_at' => '2026-08-16 13:25:26', 'updated_at' => '2026-08-16 13:25:26'],
            ['id' => 35, 'code' => '601', 'libelle' => 'Achats de fournitures', 'libelle_en' => 'Purchases of Supplies', 'classe' => 6, 'sens' => 'debit', 'is_active' => 1, 'created_at' => '2026-08-16 13:25:26', 'updated_at' => '2026-08-16 13:25:26'],
            ['id' => 36, 'code' => '602', 'libelle' => 'Achats de matériel pédagogique', 'libelle_en' => 'Teaching Materials Purchased', 'classe' => 6, 'sens' => 'debit', 'is_active' => 1, 'created_at' => '2026-08-16 13:25:26', 'updated_at' => '2026-08-16 13:25:26'],
            ['id' => 37, 'code' => '611', 'libelle' => 'Loyers', 'libelle_en' => 'Rent', 'classe' => 6, 'sens' => 'debit', 'is_active' => 1, 'created_at' => '2026-08-16 13:25:26', 'updated_at' => '2026-08-16 13:25:26'],
            ['id' => 38, 'code' => '613', 'libelle' => 'Entretien et maintenance', 'libelle_en' => 'Maintenance', 'classe' => 6, 'sens' => 'debit', 'is_active' => 1, 'created_at' => '2026-08-16 13:25:26', 'updated_at' => '2026-08-16 13:25:26'],
            ['id' => 39, 'code' => '621', 'libelle' => 'Services extérieurs', 'libelle_en' => 'External Services', 'classe' => 6, 'sens' => 'debit', 'is_active' => 1, 'created_at' => '2026-08-16 13:25:26', 'updated_at' => '2026-08-16 13:25:26'],
            ['id' => 40, 'code' => '631', 'libelle' => 'Eau', 'libelle_en' => 'Water', 'classe' => 6, 'sens' => 'debit', 'is_active' => 1, 'created_at' => '2026-08-16 13:25:26', 'updated_at' => '2026-08-16 13:25:26'],
            ['id' => 41, 'code' => '632', 'libelle' => 'Électricité', 'libelle_en' => 'Electricity', 'classe' => 6, 'sens' => 'debit', 'is_active' => 1, 'created_at' => '2026-08-16 13:25:26', 'updated_at' => '2026-08-16 13:25:26'],
            ['id' => 42, 'code' => '641', 'libelle' => 'Impôts et taxes', 'libelle_en' => 'Taxes', 'classe' => 6, 'sens' => 'debit', 'is_active' => 1, 'created_at' => '2026-08-16 13:25:26', 'updated_at' => '2026-08-16 13:25:26'],
            ['id' => 43, 'code' => '661', 'libelle' => 'Salaires', 'libelle_en' => 'Salaries', 'classe' => 6, 'sens' => 'debit', 'is_active' => 1, 'created_at' => '2026-08-16 13:25:26', 'updated_at' => '2026-08-16 13:25:26'],
            ['id' => 44, 'code' => '664', 'libelle' => 'Charges sociales', 'libelle_en' => 'Social Contributions', 'classe' => 6, 'sens' => 'debit', 'is_active' => 1, 'created_at' => '2026-08-16 13:25:26', 'updated_at' => '2026-08-16 13:25:26'],
            ['id' => 45, 'code' => '671', 'libelle' => 'Charges financières', 'libelle_en' => 'Interest Expense', 'classe' => 6, 'sens' => 'debit', 'is_active' => 1, 'created_at' => '2026-08-16 13:25:26', 'updated_at' => '2026-08-16 13:25:26'],
            ['id' => 46, 'code' => '701', 'libelle' => 'Frais de scolarité', 'libelle_en' => 'Tuition Fees', 'classe' => 7, 'sens' => 'credit', 'is_active' => 1, 'created_at' => '2026-08-16 13:25:26', 'updated_at' => '2026-08-16 13:25:26'],
            ['id' => 47, 'code' => '702', 'libelle' => 'Frais d\'inscription', 'libelle_en' => 'Registration Fees', 'classe' => 7, 'sens' => 'credit', 'is_active' => 1, 'created_at' => '2026-08-16 13:25:26', 'updated_at' => '2026-08-16 13:25:26'],
            ['id' => 48, 'code' => '703', 'libelle' => 'Frais d\'examen', 'libelle_en' => 'Examination Fees', 'classe' => 7, 'sens' => 'credit', 'is_active' => 1, 'created_at' => '2026-08-16 13:25:26', 'updated_at' => '2026-08-16 13:25:26'],
            ['id' => 49, 'code' => '704', 'libelle' => 'Frais de pension', 'libelle_en' => 'Boarding Fees', 'classe' => 7, 'sens' => 'credit', 'is_active' => 1, 'created_at' => '2026-08-16 13:25:26', 'updated_at' => '2026-08-16 13:25:26'],
            ['id' => 50, 'code' => '706', 'libelle' => 'Autres services scolaires', 'libelle_en' => 'Other Educational Services', 'classe' => 7, 'sens' => 'credit', 'is_active' => 1, 'created_at' => '2026-08-16 13:25:26', 'updated_at' => '2026-08-16 13:25:26'],
            ['id' => 51, 'code' => '751', 'libelle' => 'Subventions reçues', 'libelle_en' => 'Grants Received', 'classe' => 7, 'sens' => 'credit', 'is_active' => 1, 'created_at' => '2026-08-16 13:25:26', 'updated_at' => '2026-08-16 13:25:26'],
            ['id' => 52, 'code' => '758', 'libelle' => 'Dons et contributions', 'libelle_en' => 'Donations and Contributions', 'classe' => 7, 'sens' => 'credit', 'is_active' => 1, 'created_at' => '2026-08-16 13:25:26', 'updated_at' => '2026-08-16 13:25:26'],
            ['id' => 53, 'code' => '771', 'libelle' => 'Produits financiers', 'libelle_en' => 'Financial Income', 'classe' => 7, 'sens' => 'credit', 'is_active' => 1, 'created_at' => '2026-08-16 13:25:26', 'updated_at' => '2026-08-16 13:25:26'],
            ['id' => 54, 'code' => '811', 'libelle' => 'Charges exceptionnelles', 'libelle_en' => 'Extraordinary Expenses', 'classe' => 8, 'sens' => 'debit', 'is_active' => 1, 'created_at' => '2026-08-16 13:25:26', 'updated_at' => '2026-08-16 13:25:26'],
            ['id' => 55, 'code' => '821', 'libelle' => 'Produits exceptionnels', 'libelle_en' => 'Extraordinary Income', 'classe' => 8, 'sens' => 'credit', 'is_active' => 1, 'created_at' => '2026-08-16 13:25:26', 'updated_at' => '2026-08-16 13:25:26'],
            ['id' => 56, 'code' => '831', 'libelle' => 'Moins-value de cession', 'libelle_en' => 'Asset Disposal Loss', 'classe' => 8, 'sens' => 'debit', 'is_active' => 1, 'created_at' => '2026-08-16 13:25:26', 'updated_at' => '2026-08-16 13:25:26'],
            ['id' => 57, 'code' => '841', 'libelle' => 'Plus-value de cession', 'libelle_en' => 'Asset Disposal Gain', 'classe' => 8, 'sens' => 'credit', 'is_active' => 1, 'created_at' => '2026-08-16 13:25:26', 'updated_at' => '2026-08-16 13:25:26'],
            ['id' => 58, 'code' => '90', 'libelle' => 'Centres de coûts', 'libelle_en' => 'Cost Centers', 'classe' => 9, 'sens' => 'debit', 'is_active' => 1, 'created_at' => '2026-08-16 13:25:26', 'updated_at' => '2026-08-16 13:25:26'],
            ['id' => 59, 'code' => '91', 'libelle' => 'Département administratif', 'libelle_en' => 'Administrative Department', 'classe' => 9, 'sens' => 'debit', 'is_active' => 1, 'created_at' => '2026-08-16 13:25:26', 'updated_at' => '2026-08-16 13:25:26'],
            ['id' => 60, 'code' => '92', 'libelle' => 'Département pédagogique', 'libelle_en' => 'Academic Department', 'classe' => 9, 'sens' => 'debit', 'is_active' => 1, 'created_at' => '2026-08-16 13:25:26', 'updated_at' => '2026-08-16 13:25:26'],
        ];
        DB::table($table)->insert($rows);

    }
}
