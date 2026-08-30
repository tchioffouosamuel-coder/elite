<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

/**
 * Feuille « Import » du modèle d'import des rémunérations — une ligne par
 * rémunération à créer, la colonne Nom restreinte par liste déroulante aux
 * noms de la feuille « Liste » (cf. RemunerationTemplateListeSheet), et la
 * colonne Mode restreinte à mensuel/horaire.
 */
class RemunerationTemplateImportSheet implements FromArray, WithEvents, WithTitle
{
    private const ENTETES = [
        'Nom complet', 'Date effet', 'Mode', 'Salaire de base', 'Taux horaire',
        'Prime anciennete', 'Prime communication', 'Prime transport', 'Prime recherche',
        'Prime performance', 'Categorie',
    ];

    /** Marge au-delà de l'effectif actuel : le fichier reste valide si des agents rejoignent l'école avant d'être réimporté. */
    private const LIGNES_MARGE = 50;

    public function __construct(private readonly int $nombrePersonnels) {}

    public function array(): array
    {
        return [self::ENTETES];
    }

    public function title(): string
    {
        return 'Import';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $feuille = $event->sheet->getDelegate();
                $derniereLigne = max(1, $this->nombrePersonnels) + self::LIGNES_MARGE;

                for ($ligne = 2; $ligne <= $derniereLigne + 1; $ligne++) {
                    $nom = $feuille->getCell("A{$ligne}")->getDataValidation();
                    $nom->setType(DataValidation::TYPE_LIST);
                    $nom->setErrorStyle(DataValidation::STYLE_STOP);
                    $nom->setAllowBlank(true);
                    $nom->setShowDropDown(true);
                    $nom->setShowErrorMessage(true);
                    $nom->setErrorTitle('Nom invalide');
                    $nom->setError("Choisissez un nom dans la liste de la feuille « Liste ».");
                    $nom->setFormula1('=Liste!$A$2:$A$'.($derniereLigne + 1));

                    $mode = $feuille->getCell("C{$ligne}")->getDataValidation();
                    $mode->setType(DataValidation::TYPE_LIST);
                    $mode->setErrorStyle(DataValidation::STYLE_STOP);
                    $mode->setAllowBlank(true);
                    $mode->setShowDropDown(true);
                    $mode->setShowErrorMessage(true);
                    $mode->setErrorTitle('Mode invalide');
                    $mode->setError('Choisissez "mensuel" ou "horaire".');
                    $mode->setFormula1('"mensuel,horaire"');
                }

                // Indications de saisie, en commentaire des en-têtes plutôt que
                // dans leur libellé : un libellé trop long complique le
                // rapprochement des colonnes à l'import (accents, ponctuation).
                $feuille->getComment('B1')->getText()->createTextRun("Format attendu : AAAA-MM-JJ (ex. 2026-09-01).");
                $feuille->getComment('E1')->getText()->createTextRun("Uniquement pour le mode horaire — laisser vide en mode mensuel.");
                $feuille->getComment('D1')->getText()->createTextRun("Uniquement pour le mode mensuel — laisser vide en mode horaire.");
            },
        ];
    }
}
