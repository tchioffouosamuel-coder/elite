<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class NotesEnseignantSheet extends DefaultValueBinder implements FromArray, WithColumnWidths, WithCustomValueBinder, WithStyles, WithTitle
{
    private const PALETTE_COLONNES = [
        ['C6D9F1', 'EAF2FF', 'DCE8F8'],
        ['C6E8D5', 'EAF7EF', 'D9F0E2'],
        ['FAD7B5', 'FFF3E5', 'FCE6D1'],
        ['DCCCF2', 'F3EDFC', 'E8DDF7'],
        ['BFE5E8', 'E4F5F6', 'D1ECEF'],
        ['F2C9D6', 'FCECF1', 'F7DDE6'],
        ['D8E5C3', 'F0F5E8', 'E3ECD5'],
        ['D4C7E7', 'F0ECF7', 'E2DAF0'],
        ['F5E79C', 'FFFBE2', 'FFF5C5'],
        ['BFE2D9', 'E4F4F0', 'D1EBE4'],
        ['F0CEC6', 'FBEEEB', 'F5DFD9'],
        ['C9D0EB', 'ECEFFA', 'DCE1F3'],
    ];

    /** @param list<list<mixed>> $lignes */
    public function __construct(
        private readonly string $nom,
        private readonly array $lignes,
        private readonly int $entetes = 1,
        private readonly bool $colorerColonnes = false,
    ) {}

    public function title(): string
    {
        // Le préfixe numérique identifie l'affectation (secondaire) ou la classe.
        return mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/u', '-', $this->nom), 0, 31);
    }

    public function array(): array
    {
        return $this->lignes;
    }

    public function bindValue(Cell $cell, $value): bool
    {
        // Conserve notamment les zéros initiaux des matricules et les noms
        // commençant par « = » comme du texte, sans en faire des formules.
        if (is_string($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function columnWidths(): array
    {
        return ['A' => 23, 'B' => 35];
    }

    public function styles(Worksheet $sheet): array
    {
        $last = $sheet->getHighestColumn();
        $sheet->freezePane('C'.($this->entetes + 1));
        $sheet->getStyle("A1:{$last}{$this->entetes}")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$last}{$this->entetes}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E8E4DA');
        $sheet->getStyle("A1:{$last}{$this->entetes}")->getAlignment()->setWrapText(true);
        $sheet->getRowDimension(1)->setRowHeight($this->entetes === 2 ? 44 : 24);
        $sheet->setAutoFilter("A{$this->entetes}:{$last}".max($this->entetes, $sheet->getHighestRow()));

        if ($this->entetes === 2) {
            $sheet->mergeCells('A1:A2');
            $sheet->mergeCells('B1:B2');
            $start = 3;
            $columns = count($this->lignes[0]);
            for ($i = 4; $i <= $columns + 1; $i++) {
                if ($i <= $columns && ($this->lignes[0][$i - 1] ?? null) === null) {
                    continue;
                }
                if ($i - 1 > $start) {
                    $sheet->mergeCells(Coordinate::stringFromColumnIndex($start).'1:'.Coordinate::stringFromColumnIndex($i - 1).'1');
                }
                $start = $i;
            }
        }

        $lastColumn = Coordinate::columnIndexFromString($last);
        if ($this->colorerColonnes) {
            $debutDonnees = $this->entetes + 1;
            $derniereLigne = $sheet->getHighestRow();

            for ($i = 1; $i <= $lastColumn; $i++) {
                [$entete, $fond, $fondAlterne] = self::PALETTE_COLONNES[($i - 1) % count(self::PALETTE_COLONNES)];
                $colonne = Coordinate::stringFromColumnIndex($i);
                $sheet->getStyle("{$colonne}{$this->entetes}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($entete);
                if ($this->entetes === 2 && $i <= 2) {
                    $sheet->getStyle("{$colonne}1")->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($entete);
                }
                if ($debutDonnees <= $derniereLigne) {
                    $sheet->getStyle("{$colonne}{$debutDonnees}:{$colonne}{$derniereLigne}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($fond);
                    for ($ligne = $debutDonnees + 1; $ligne <= $derniereLigne; $ligne += 2) {
                        $sheet->getStyle("{$colonne}{$ligne}")->getFill()
                            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($fondAlterne);
                    }
                }
            }

            if ($this->entetes === 2) {
                $debutGroupe = 3;
                $colonnes = count($this->lignes[0]);
                for ($i = 4; $i <= $colonnes + 1; $i++) {
                    if ($i <= $colonnes && ($this->lignes[0][$i - 1] ?? null) === null) {
                        continue;
                    }
                    $finGroupe = $i - 1;
                    if ($finGroupe >= $debutGroupe) {
                        $couleur = self::PALETTE_COLONNES[($debutGroupe - 1) % count(self::PALETTE_COLONNES)][0];
                        $debut = Coordinate::stringFromColumnIndex($debutGroupe);
                        $fin = Coordinate::stringFromColumnIndex($finGroupe);
                        $sheet->getStyle("{$debut}1:{$fin}1")->getFill()
                            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($couleur);
                    }
                    $debutGroupe = $i;
                }
            }
        }

        for ($i = 3; $i <= $lastColumn; $i++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setWidth(18);
        }

        return [];
    }
}
