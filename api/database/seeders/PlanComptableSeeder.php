<?php

namespace Database\Seeders;

use App\Models\CompteComptable;
use Illuminate\Database\Seeder;

/**
 * Plan de comptes du complexe, repris tel quel du classeur « Accounting
 * situation » tenu par l'établissement.
 *
 * On ne réinvente pas la nomenclature : c'est celle que lit le comptable et
 * celle qu'attendent les états à produire. Les comptes de la classe 7
 * (produits) portent les recettes de scolarité, ceux de la classe 6 (charges)
 * les dépenses et les salaires — c'est ce rattachement qui permet au bilan de
 * se construire sans saisie supplémentaire.
 */
class PlanComptableSeeder extends Seeder
{
    /** classe => [libellé de la classe, [code => [libellé fr, libellé en, sens]]]. */
    private const PLAN = [
        1 => ['Capitaux et dettes', [
            '101' => ['Capital / Fonds de dotation', 'Capital / Founding Fund', 'credit'],
            '106' => ['Réserves', 'Reserves', 'credit'],
            '110' => ['Report à nouveau', 'Retained Earnings', 'credit'],
            '130' => ['Subventions et dons', 'Grants and Donations', 'credit'],
            '161' => ['Emprunts bancaires', 'Bank Loans', 'credit'],
            '166' => ['Autres emprunts', 'Other Borrowings', 'credit'],
        ]],
        2 => ['Immobilisations', [
            '211' => ['Frais de développement', 'Development Costs', 'debit'],
            '213' => ['Logiciels', 'Software', 'debit'],
            '221' => ['Terrains', 'Land', 'debit'],
            '222' => ['Bâtiments', 'Buildings', 'debit'],
            '241' => ['Mobilier et équipements', 'Furniture and Equipment', 'debit'],
            '245' => ['Matériel de transport', 'Transport Equipment', 'debit'],
            '246' => ['Matériel de bureau', 'Office Equipment', 'debit'],
            '281' => ['Amortissements', 'Depreciation Accounts', 'credit'],
        ]],
        3 => ['Stocks', [
            '311' => ['Matériel pédagogique', 'Teaching Materials', 'debit'],
            '321' => ['Fournitures consommables', 'Consumable Supplies', 'debit'],
            '322' => ['Fournitures de bureau', 'Office Supplies', 'debit'],
            '331' => ['Travaux en cours', 'Work in Progress', 'debit'],
            '391' => ['Provisions sur stocks', 'Inventory Provisions', 'credit'],
        ]],
        4 => ['Tiers', [
            '401' => ['Fournisseurs', 'Suppliers', 'credit'],
            '408' => ['Factures fournisseurs non parvenues', 'Accrued Supplier Invoices', 'credit'],
            '411' => ['Élèves / Clients — créances', 'Students / Customers Receivable', 'debit'],
            '421' => ['Personnel — rémunérations dues', 'Employees Payroll', 'credit'],
            '431' => ['Sécurité sociale (CNPS)', 'Social Security', 'credit'],
            '441' => ['État — impôts et taxes', 'State Taxes', 'credit'],
            '443' => ['TVA collectée', 'VAT Collected', 'credit'],
            '445' => ['TVA récupérable', 'VAT Recoverable', 'debit'],
            '467' => ['Autres débiteurs', 'Other Debtors', 'debit'],
            '471' => ['Autres créditeurs', 'Other Creditors', 'credit'],
        ]],
        5 => ['Trésorerie', [
            '521' => ['Banque', 'Bank Account', 'debit'],
            '522' => ['Compte épargne', 'Savings Account', 'debit'],
            '531' => ['Virements internes', 'Cash in Transit', 'debit'],
            '571' => ['Caisse', 'Cash on Hand', 'debit'],
            '578' => ['Mobile Money', 'Mobile Money Account', 'debit'],
        ]],
        6 => ['Charges', [
            '601' => ['Achats de fournitures', 'Purchases of Supplies', 'debit'],
            '602' => ['Achats de matériel pédagogique', 'Teaching Materials Purchased', 'debit'],
            '611' => ['Loyers', 'Rent', 'debit'],
            '613' => ['Entretien et maintenance', 'Maintenance', 'debit'],
            '621' => ['Services extérieurs', 'External Services', 'debit'],
            '631' => ['Eau', 'Water', 'debit'],
            '632' => ['Électricité', 'Electricity', 'debit'],
            '641' => ['Impôts et taxes', 'Taxes', 'debit'],
            '661' => ['Salaires', 'Salaries', 'debit'],
            '664' => ['Charges sociales', 'Social Contributions', 'debit'],
            '671' => ['Charges financières', 'Interest Expense', 'debit'],
        ]],
        7 => ['Produits', [
            '701' => ['Frais de scolarité', 'Tuition Fees', 'credit'],
            '702' => ["Frais d'inscription", 'Registration Fees', 'credit'],
            '703' => ["Frais d'examen", 'Examination Fees', 'credit'],
            '704' => ['Frais de pension', 'Boarding Fees', 'credit'],
            '706' => ['Autres services scolaires', 'Other Educational Services', 'credit'],
            '751' => ['Subventions reçues', 'Grants Received', 'credit'],
            '758' => ['Dons et contributions', 'Donations and Contributions', 'credit'],
            '771' => ['Produits financiers', 'Financial Income', 'credit'],
        ]],
        8 => ['Hors activité ordinaire', [
            '811' => ['Charges exceptionnelles', 'Extraordinary Expenses', 'debit'],
            '821' => ['Produits exceptionnels', 'Extraordinary Income', 'credit'],
            '831' => ['Moins-value de cession', 'Asset Disposal Loss', 'debit'],
            '841' => ['Plus-value de cession', 'Asset Disposal Gain', 'credit'],
        ]],
        9 => ['Analytique', [
            '90' => ['Centres de coûts', 'Cost Centers', 'debit'],
            '91' => ['Département administratif', 'Administrative Department', 'debit'],
            '92' => ['Département pédagogique', 'Academic Department', 'debit'],
        ]],
    ];

    public function run(): void
    {
        foreach (self::PLAN as $classe => [, $comptes]) {
            foreach ($comptes as $code => [$libelle, $libelleEn, $sens]) {
                CompteComptable::updateOrCreate(
                    ['code' => (string) $code],
                    ['libelle' => $libelle, 'libelle_en' => $libelleEn, 'classe' => $classe, 'sens' => $sens],
                );
            }
        }
    }
}
