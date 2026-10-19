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
    /** @param list<list<mixed>> $lignes */
    public function __construct(
        private readonly string $nom,
        private readonly array $lignes,
        private readonly int $entetes = 1,
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

        for ($i = 3; $i <= Coordinate::columnIndexFromString($last); $i++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setWidth(18);
        }

        return [];
    }
}
