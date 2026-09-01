<?php

namespace App\Exports;

use App\Models\ClasseMatiere;
use App\Support\Progression\ProgressionGabaritColonnes;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Feuille du modèle vide d'une matière, au format du gabarit de
 * l'établissement — reprend le même cartouche et le même en-tête (ligne 7 ou
 * 8 selon le cycle) que la fiche remplie, afin d'être relue sans modification
 * par `ProgressionImport`.
 *
 * Le titre de la feuille porte l'id de l'affectation en préfixe
 * (`"{id} Matière"`) : c'est ce qui permet à l'import groupé
 * (`ProgressionController::importClasse()`) de retrouver la bonne matière
 * même si son nom a changé entre le téléchargement et le renvoi du fichier,
 * ou si l'enseignant a réordonné les feuilles.
 */
class ProgressionModeleMatiereSheet implements FromArray, WithColumnWidths, WithStyles, WithTitle
{
    /** Lignes vides pré-formatées, prêtes à remplir. */
    private const LIGNES_VIDES = 30;

    public function __construct(
        private readonly ClasseMatiere $classeMatiere,
        private readonly string $cycle,
        private readonly ?string $anneeScolaire,
    ) {}

    public function title(): string
    {
        $suffixe = ' '.Str::limit($this->classeMatiere->matiere->nom, 20, '');

        return Str::limit($this->classeMatiere->id.$suffixe, 31, '');
    }

    public function array(): array
    {
        $cm = $this->classeMatiere;
        $enseignant = $cm->enseignant?->nom_complet ?? $cm->classe->titulaire?->nom_complet ?? '—';

        $lignes = [
            ['Fiche de progression — Scheme of Work'],
            ['School', $cm->classe->school->name, 'Teacher', $enseignant],
        ];

        if ($this->cycle === 'secondaire') {
            $lignes[] = ['Department', $cm->matiere->departement?->nom ?? '—', 'Specialty', $cm->specialite ?? '—'];
            $lignes[] = ['Class', $cm->classe->nom, 'Module / Competency', $cm->module_competence ?? '—'];
        } else {
            $lignes[] = ['Class', $cm->classe->nom, 'Subject', $cm->matiere->nom];
        }

        $lignes[] = ['Academic Year', $this->anneeScolaire ?? '—', 'Term', 'Année scolaire complète'];
        $lignes[] = ['Une ligne = une leçon. One row = one lesson.'];
        $lignes[] = [];

        $colonnes = ProgressionGabaritColonnes::pour($this->cycle);
        $lignes[] = array_values($colonnes);

        $nbColonnes = count($colonnes);
        for ($semaine = 1; $semaine <= self::LIGNES_VIDES; $semaine++) {
            $ligne = array_fill(0, $nbColonnes, null);
            $ligne[0] = $semaine;
            $lignes[] = $ligne;
        }

        return $lignes;
    }

    public function styles(Worksheet $sheet): array
    {
        $ligneEntete = ProgressionGabaritColonnes::ligneEntete($this->cycle);
        $nbColonnes = count(ProgressionGabaritColonnes::pour($this->cycle));
        $derniereColonne = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($nbColonnes);

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle("A{$ligneEntete}:{$derniereColonne}{$ligneEntete}")->getFont()->setBold(true);
        $sheet->getStyle("A{$ligneEntete}:{$derniereColonne}{$ligneEntete}")
            ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('E8E4DA');

        return [];
    }

    public function columnWidths(): array
    {
        return ['A' => 10, 'B' => 22, 'C' => 22, 'D' => 22, 'E' => 26, 'F' => 26];
    }
}
