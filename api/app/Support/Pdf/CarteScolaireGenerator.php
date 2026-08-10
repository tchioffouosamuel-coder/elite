<?php

namespace App\Support\Pdf;

use App\Models\Classe;
use App\Models\Eleve;
use FPDF;
use Illuminate\Support\Facades\Storage;

/**
 * Génère UNE planche PDF contenant toutes les cartes scolaires d'une classe,
 * agencées en grille (comme generate_IDcards_for_a_class.php dans _smapp), via FPDF pur
 * (pas de HTML/CSS — dessin en coordonnées absolues, comme le fait _smapp).
 */
class CarteScolaireGenerator extends FPDF
{
    private const CARD_W = 53.0;

    private const CARD_H = 83.0;

    private const COLS = 5;

    private const ROWS = 2;

    private const GUTTER_X = 6.0;

    private const GUTTER_Y = 8.0;

    private const MARGIN_TOP = 26.0;

    private const SLATE = [41, 47, 54];

    private const GOLD = [255, 171, 2];

    private const CREAM = [249, 251, 249];

    private ?string $classeName = null;

    private ?string $anneeLabel = null;

    private ?int $headerDrawnOnPage = null;

    public function build(Classe $classe, string $anneeLabel): string
    {
        $this->classeName = $classe->nom;
        $this->anneeLabel = $anneeLabel;

        $this->AddPage('L', 'A4');
        $this->SetAutoPageBreak(false);
        $this->SetTitle($this->txt('Cartes scolaires - '.$classe->nom));

        $eleves = Eleve::forSchool($classe->school_id)
            ->where('classe_id', $classe->id)
            ->where('statut', 'actif')
            ->orderBy('nom')
            ->get();

        $marginX = (297 - (self::COLS * self::CARD_W + (self::COLS - 1) * self::GUTTER_X)) / 2;

        $index = 0;
        foreach ($eleves as $eleve) {
            $slot = $index % (self::COLS * self::ROWS);
            if ($index > 0 && $slot === 0) {
                $this->AddPage('L', 'A4');
            }
            $col = $slot % self::COLS;
            $row = intdiv($slot, self::COLS);
            $x = $marginX + $col * (self::CARD_W + self::GUTTER_X);
            $y = self::MARGIN_TOP + $row * (self::CARD_H + self::GUTTER_Y);

            $this->drawPageHeaderIfNeeded();
            $this->drawCard($x, $y, $eleve);
            $index++;
        }

        if ($eleves->isEmpty()) {
            $this->drawPageHeaderIfNeeded();
            $this->SetFont('Helvetica', '', 11);
            $this->SetXY(0, 100);
            $this->Cell(297, 8, $this->txt('Aucun élève actif dans cette classe.'), 0, 0, 'C');
        }

        return $this->Output('S');
    }

    private function drawPageHeaderIfNeeded(): void
    {
        if ($this->headerDrawnOnPage === $this->PageNo()) {
            return;
        }
        $this->headerDrawnOnPage = $this->PageNo();

        $this->SetFont('Helvetica', 'B', 13);
        $this->SetTextColor(...self::SLATE);
        $this->SetXY(0, 8);
        $this->Cell(297, 7, $this->txt('Cartes scolaires — '.$this->classeName), 0, 0, 'C');

        $this->SetFont('Helvetica', 'I', 9);
        $this->SetTextColor(120, 120, 120);
        $this->SetXY(0, 15);
        $this->Cell(297, 6, $this->txt('Année scolaire '.$this->anneeLabel), 0, 0, 'C');
    }

    private function drawCard(float $x, float $y, Eleve $eleve): void
    {
        $this->SetFillColor(...self::CREAM);
        $this->RoundedRect($x, $y, self::CARD_W, self::CARD_H, 3, 'F');
        $this->SetDrawColor(...self::GOLD);
        $this->SetLineWidth(0.5);
        $this->RoundedRect($x, $y, self::CARD_W, self::CARD_H, 3, 'D');
        $this->SetLineWidth(0.2);

        // Bandeau
        $this->SetFillColor(...self::SLATE);
        $this->Rect($x + 1, $y + 1, self::CARD_W - 2, 15, 'F');

        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Helvetica', 'B', 7.5);
        $this->SetXY($x + 2, $y + 2.5);
        $this->MultiCell(self::CARD_W - 4, 3.2, $this->txt(mb_strtoupper($eleve->school->name ?? '')), 0, 'C');

        $this->SetTextColor(...self::GOLD);
        $this->SetFont('Helvetica', 'B', 6);
        $this->SetXY($x + 2, $y + 11.5);
        $this->Cell(self::CARD_W - 4, 3.5, $this->txt('CARTE SCOLAIRE '.$this->anneeLabel), 0, 0, 'C');

        // Photo / avatar
        $photoSize = 20;
        $photoX = $x + (self::CARD_W - $photoSize) / 2;
        $photoY = $y + 19;
        $absolutePhoto = $eleve->photo_path ? Storage::disk('public')->path($eleve->photo_path) : null;

        if ($absolutePhoto && is_file($absolutePhoto)) {
            $this->Image($absolutePhoto, $photoX, $photoY, $photoSize, $photoSize);
            $this->SetDrawColor(...self::GOLD);
            $this->Rect($photoX, $photoY, $photoSize, $photoSize, 'D');
        } else {
            $this->SetFillColor(...self::SLATE);
            $this->Rect($photoX, $photoY, $photoSize, $photoSize, 'F');
            $initiales = mb_strtoupper(mb_substr($eleve->prenom, 0, 1).mb_substr($eleve->nom, 0, 1));
            $this->SetTextColor(...self::GOLD);
            $this->SetFont('Helvetica', 'B', 12);
            $this->SetXY($photoX, $photoY + 7);
            $this->Cell($photoSize, 6, $this->txt($initiales), 0, 0, 'C');
        }

        // Nom
        $this->SetTextColor(...self::SLATE);
        $this->SetFont('Helvetica', 'B', 7.5);
        $this->SetXY($x + 2, $y + 41);
        $this->MultiCell(self::CARD_W - 4, 3.2, $this->txt($eleve->nomComplet()), 0, 'C');

        // Champs
        $this->SetFont('Helvetica', '', 6);
        $lignes = [
            'Matricule : '.($eleve->matricule ?? '—'),
            'Classe : '.$this->classeName,
            'Sexe : '.($eleve->sexe === 'F' ? 'F' : 'M'),
            'Né(e) le : '.($eleve->date_naissance?->format('d/m/Y') ?? '—'),
        ];
        $fieldY = $y + 50;
        foreach ($lignes as $ligne) {
            $this->SetXY($x + 3, $fieldY);
            $this->Cell(self::CARD_W - 6, 3.6, $this->txt($ligne), 0, 0, 'C');
            $fieldY += 4;
        }

        // Pied de carte
        $this->SetDrawColor(209, 219, 217);
        $this->Line($x + 3, $y + self::CARD_H - 10, $x + self::CARD_W - 3, $y + self::CARD_H - 10);
        $this->SetTextColor(136, 136, 136);
        $this->SetFont('Helvetica', 'I', 4.8);
        $this->SetXY($x + 2, $y + self::CARD_H - 8);
        $this->MultiCell(self::CARD_W - 4, 2.6, $this->txt('En cas de perte, retourner à l\'établissement'), 0, 'C');
    }

    private function txt(string $value): string
    {
        return iconv('UTF-8', 'CP1252//TRANSLIT', $value) ?: $value;
    }

    /**
     * Rectangle aux coins arrondis (FPDF ne le propose pas nativement).
     */
    private function RoundedRect(float $x, float $y, float $w, float $h, float $r, string $style = ''): void
    {
        $k = $this->k;
        $hp = $this->h;
        $op = match ($style) {
            'F' => 'f',
            'FD', 'DF' => 'B',
            default => 'S',
        };
        $myArc = 4 / 3 * (sqrt(2) - 1);

        $this->_out(sprintf('%.2F %.2F m', ($x + $r) * $k, ($hp - $y) * $k));
        $xc = $x + $w - $r;
        $yc = $y + $r;
        $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - $y) * $k));
        $this->arc($xc + $r * $myArc, $yc - $r, $xc + $r, $yc - $r * $myArc, $xc + $r, $yc);
        $xc = $x + $w - $r;
        $yc = $y + $h - $r;
        $this->_out(sprintf('%.2F %.2F l', ($x + $w) * $k, ($hp - $yc) * $k));
        $this->arc($xc + $r, $yc + $r * $myArc, $xc + $r * $myArc, $yc + $r, $xc, $yc + $r);
        $xc = $x + $r;
        $yc = $y + $h - $r;
        $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - ($y + $h)) * $k));
        $this->arc($xc - $r * $myArc, $yc + $r, $xc - $r, $yc + $r * $myArc, $xc - $r, $yc);
        $xc = $x + $r;
        $yc = $y + $r;
        $this->_out(sprintf('%.2F %.2F l', $x * $k, ($hp - $yc) * $k));
        $this->arc($xc - $r, $yc - $r * $myArc, $xc - $r * $myArc, $yc - $r, $xc, $yc - $r);
        $this->_out($op);
    }

    private function arc(float $x1, float $y1, float $x2, float $y2, float $x3, float $y3): void
    {
        $h = $this->h;
        $k = $this->k;
        $this->_out(sprintf(
            '%.2F %.2F %.2F %.2F %.2F %.2F c ',
            $x1 * $k,
            ($h - $y1) * $k,
            $x2 * $k,
            ($h - $y2) * $k,
            $x3 * $k,
            ($h - $y3) * $k
        ));
    }
}
