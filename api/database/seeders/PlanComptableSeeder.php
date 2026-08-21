<?php

namespace Database\Seeders;

use App\Models\CompteComptable;
use Illuminate\Database\Seeder;

/**
 * Plan de comptes du complexe.
 *
 * Les classes 6 et 7 reproduisent **la nomenclature réellement tenue par
 * l'établissement**, relevée sur les onze exercices de l'« État de synthèse
 * des charges et dépenses » : mêmes codes, mêmes libellés, même ordre de
 * présentation. C'est à ce document que le comptable compare, et un état qui
 * n'en reprend pas les codes ne se rapproche de rien.
 *
 * Les classes 1 à 5 (capitaux, immobilisations, tiers, trésorerie) n'y
 * figurent pas : le document est un compte d'emploi, pas une balance générale.
 * On les conserve parce que la partie double en a besoin — un encaissement
 * doit bien débiter une caisse — mais elles ne sortent jamais à l'état de
 * synthèse.
 *
 * @see \App\Services\Comptabilite\EtatSyntheseService pour la restitution.
 */
class PlanComptableSeeder extends Seeder
{
    /**
     * Comptes du document, dans son ordre de présentation.
     *
     * [code => [libellé fr, libellé en, nature, assiette, montant unitaire]]
     */
    private const CHARGES = [
        // Le « dépôt initial » ouvre la colonne des dépenses du document, mais
        // un apport de l'exploitant n'est pas une charge : il est ici classé
        // en capital, ce qui le sort du résultat sans le sortir du registre.
        '100' => ["Dépôt initial / apport de l'exploitant", 'Owner capital deposit', 'capital', 'libre', null],

        '603' => ['Achat kits infirmerie', 'Infirmary kits', 'exploitation', 'libre', null],
        '604' => ['Soins médicaux externes', 'External medical care', 'exploitation', 'libre', null],
        '605' => ["Achat matériel d'EPS", 'Sports equipment', 'exploitation', 'libre', null],
        '606' => ['Matériel pédagogique et informatique', 'Teaching and IT equipment', 'exploitation', 'libre', null],
        '607' => ["Frais d'achat et d'entretien des logiciels", 'Software purchase and upkeep', 'exploitation', 'libre', null],
        '611' => ['Fournitures de bureau, tables-bancs, tableaux', 'Office supplies, desks, boards', 'exploitation', 'libre', null],
        '613' => ["Produits d'entretien", 'Cleaning products', 'exploitation', 'libre', null],
        '614' => ['Fournitures diverses', 'Sundry supplies', 'exploitation', 'libre', null],

        // Le poste qui décide de la balance : 38,7 % des dépenses des onze
        // exercices. Classé en investissement, il n'ampute plus le résultat
        // d'un coup — c'est le compte 699 qui l'y ramène, étalé.
        '624' => ['Construction et entretien/réparation bâtiments', 'Construction and building works', 'investissement', 'libre', null],

        '625' => ['Hygiène et salubrité', 'Hygiene and sanitation', 'exploitation', 'libre', null],
        '626' => ['Factures eaux et électricité', 'Water and electricity', 'exploitation', 'libre', null],
        '631' => ['Don accordé aux mouvements et associations', 'Donations to associations', 'exploitation', 'libre', null],
        '632' => ["Frais d'actes, notariés, cadastre et contentieux", 'Legal, notary and land registry fees', 'exploitation', 'libre', null],
        '633' => ['Postes et télécommunications', 'Post and telecommunications', 'exploitation', 'libre', null],
        '635' => ['Voyages et déplacements', 'Travel and transport', 'exploitation', 'libre', null],
        '642' => ['Session de formation pédagogique et séminaire', 'Teacher training and seminars', 'exploitation', 'libre', null],
        '650' => ['Autres dépenses et fonctionnement bureau APEE', 'Other expenses and PTA office', 'exploitation', 'libre', null],
        '651' => ['Prospections et affichages', 'Prospecting and advertising', 'exploitation', 'libre', null],
        '652' => ['Honoraires experts divers', 'Consultancy fees', 'exploitation', 'libre', null],
        '653' => ['Réceptions', 'Receptions', 'exploitation', 'libre', null],

        // Trois prélèvements qui ne s'arbitrent pas : ils suivent l'effectif
        // au tarif fixe, vérifié à l'unité près sur les onze exercices.
        '654' => ['Quote-part SEDUC', 'SEDUC levy', 'exploitation', 'par_eleve', 200],
        '655' => ['Assurance scolaire', 'School insurance', 'exploitation', 'par_eleve', 100],

        '661' => ['Salaires du personnel', 'Staff salaries', 'exploitation', 'libre', null],
        '662' => ['Cotisation CNPS', 'Social security contributions', 'exploitation', 'libre', null],
        '663' => ['Charges fiscales', 'Payroll taxes', 'exploitation', 'libre', null],
        '664' => ['Cotisations frais Fenasco-B', 'Fenasco-B levy', 'exploitation', 'par_eleve', 200],

        '674' => ['Habillement / tenue de travail', 'Work clothing', 'exploitation', 'libre', null],
        '676' => ['Fêtes ou célébrations', 'Celebrations', 'exploitation', 'libre', null],
        '677' => ['Frais fêtes 11 février, 20 mai et journées culturelles', 'National and cultural days', 'exploitation', 'libre', null],
        '678' => ['Détente et excursions, récréation et sorties', 'Outings and excursions', 'exploitation', 'libre', null],
        '679' => ['Primes aux meilleurs élèves', 'Prizes to best pupils', 'exploitation', 'libre', null],
        '680' => ['Frais fêtes des enseignants et galas', 'Staff parties and galas', 'exploitation', 'libre', null],
        '687' => ['Provision pour risque', 'Risk provision', 'exploitation', 'libre', null],
        '691' => ['Timbres fiscaux, entretien boîte postale', 'Stamps and post box', 'exploitation', 'libre', null],
        '693' => ['Services de banque et frais bancaires', 'Bank charges', 'exploitation', 'libre', null],
        '697' => ['Pertes et erreurs comptables', 'Accounting losses and errors', 'exploitation', 'libre', null],

        // Resté à zéro sur les onze exercices, alors que 202 millions de
        // construction passaient en charge la même année. C'est par lui que
        // l'investissement doit revenir au résultat.
        '699' => ['Amortissements bâtiments', 'Building depreciation', 'exploitation', 'libre', null],
    ];

    /** @var array<string, array{0: string, 1: string}> */
    private const PRODUITS = [
        '700' => ['Inscriptions', 'Registration fees'],
        '701' => ['Scolarité', 'Tuition fees'],
        '702' => ['APEE', 'PTA contributions'],
        // Absent du document, qui ne connaît que quatre produits. L'application
        // suit pourtant le transport et les frais annexes à la ligne : leur
        // donner un compte propre vaut mieux que de les noyer dans 701.
        '703' => ['Frais annexes et transport', 'Ancillary fees and transport'],
        '721' => ["Subventions de l'État et autres", 'State grants and others'],
    ];

    /**
     * Comptes techniques, hors document : la partie double en a besoin pour
     * loger la contrepartie de chaque mouvement.
     *
     * @var array<int, array{0: string, 1: array<string, array{0: string, 1: string, 2: string}>}>
     */
    private const TECHNIQUES = [
        1 => ['Capitaux et dettes', [
            '101' => ['Capital / fonds de dotation', 'Capital / founding fund', 'credit'],
            '108' => ['Apport personnel du fondateur', "Owner's contribution", 'credit'],
            '110' => ['Report à nouveau', 'Retained earnings', 'credit'],
            '130' => ['Subventions et dons reçus', 'Grants and donations received', 'credit'],
            '161' => ['Emprunts bancaires', 'Bank loans', 'credit'],
        ]],
        2 => ['Immobilisations', [
            '221' => ['Terrains', 'Land', 'debit'],
            '222' => ['Bâtiments', 'Buildings', 'debit'],
            '241' => ['Mobilier et équipements', 'Furniture and equipment', 'debit'],
            '245' => ['Matériel de transport', 'Transport equipment', 'debit'],
            '281' => ['Amortissements cumulés', 'Accumulated depreciation', 'credit'],
        ]],
        4 => ['Tiers', [
            '401' => ['Fournisseurs', 'Suppliers', 'credit'],
            '411' => ['Élèves — créances de scolarité', 'Student receivables', 'debit'],
            '421' => ['Personnel — rémunérations dues', 'Payroll payable', 'credit'],
            '431' => ['CNPS — cotisations à reverser', 'Social security payable', 'credit'],
            '441' => ['État — impôts et taxes à reverser', 'Taxes payable', 'credit'],
            '467' => ['Autres débiteurs', 'Other debtors', 'debit'],
            '471' => ['Autres créditeurs', 'Other creditors', 'credit'],
        ]],
        5 => ['Trésorerie', [
            '521' => ['Banque', 'Bank account', 'debit'],
            '522' => ['Compte épargne', 'Savings account', 'debit'],
            '531' => ['Virements internes', 'Cash in transit', 'debit'],
            '571' => ['Caisse', 'Cash on hand', 'debit'],
            '578' => ['Mobile Money', 'Mobile money account', 'debit'],
        ]],
    ];

    public function run(): void
    {
        $ordre = 0;
        $connus = [];

        foreach (self::CHARGES as $code => [$libelle, $libelleEn, $nature, $assiette, $unitaire]) {
            $connus[] = $code;
            CompteComptable::updateOrCreate(['code' => $code], [
                'libelle' => $libelle,
                'libelle_en' => $libelleEn,
                // Le dépôt de l'exploitant est un compte de capitaux, même s'il
                // ouvre la colonne des dépenses du document.
                'classe' => $nature === 'capital' ? 1 : 6,
                'sens' => 'debit',
                'nature' => $nature,
                'assiette' => $assiette,
                'montant_unitaire' => $unitaire,
                'ordre' => $ordre += 10,
                'is_active' => true,
            ]);
        }

        foreach (self::PRODUITS as $code => [$libelle, $libelleEn]) {
            $connus[] = $code;
            CompteComptable::updateOrCreate(['code' => $code], [
                'libelle' => $libelle,
                'libelle_en' => $libelleEn,
                'classe' => 7,
                'sens' => 'credit',
                'nature' => 'exploitation',
                'assiette' => 'libre',
                'montant_unitaire' => null,
                'ordre' => $ordre += 10,
                'is_active' => true,
            ]);
        }

        foreach (self::TECHNIQUES as $classe => [, $comptes]) {
            foreach ($comptes as $code => [$libelle, $libelleEn, $sens]) {
                $connus[] = $code;
                CompteComptable::updateOrCreate(['code' => $code], [
                    'libelle' => $libelle,
                    'libelle_en' => $libelleEn,
                    'classe' => $classe,
                    'sens' => $sens,
                    // Un compte de tiers ou de trésorerie n'est ni une charge
                    // ni un produit : il ne pèse sur aucun résultat.
                    'nature' => $classe === 1 ? 'capital' : 'exploitation',
                    'assiette' => 'libre',
                    'montant_unitaire' => null,
                    'ordre' => $ordre += 10,
                    'is_active' => true,
                ]);
            }
        }

        /*
         * Reliquats du plan générique livré auparavant — comptes de stocks, de
         * classe 8 ou analytiques que ni le document ni la partie double
         * n'utilisent. Ils ne sont pas supprimés : une écriture ancienne peut
         * encore y pointer, et un compte effacé emporterait sa piste. Les
         * désactiver suffit à les sortir de la saisie.
         */
        CompteComptable::whereNotIn('code', $connus)->update(['is_active' => false]);
    }
}
