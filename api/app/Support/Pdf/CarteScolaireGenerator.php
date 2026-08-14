<?php

namespace App\Support\Pdf;

use App\Models\Classe;
use App\Models\Eleve;
use App\Models\School;
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

    private ?School $school = null;

    public function build(Classe $classe, string $anneeLabel): string
    {
        $this->classeName = $classe->nom;
        $this->anneeLabel = $anneeLabel;
        $this->school = $classe->school;

        $this->SetAutoPageBreak(false);
        $this->SetTitle($this->txt('Cartes scolaires - '.$classe->nom));

        $eleves = Eleve::forSchool($classe->school_id)
            ->where('classe_id', $classe->id)
            ->where('statut', 'actif')
            ->orderBy('nom_complet')
            ->get();

        if ($eleves->isEmpty()) {
            $this->AddPage('L', 'A4');
            $this->drawPageHeaderIfNeeded();
            $this->SetFont('Helvetica', '', 11);
            $this->SetXY(0, 100);
            $this->Cell(297, 8, $this->txt('Aucun élève actif dans cette classe.'), 0, 0, 'C');

            return $this->Output('S');
        }

        // Une planche recto puis sa planche verso, comme
        // generate_IDcards_for_a_class.php : les deux faces s'impriment en
        // recto-verso sur la même feuille avant découpe.
        foreach ($eleves->chunk(self::COLS * self::ROWS) as $planche) {
            $this->AddPage('L', 'A4');
            $this->drawPageHeaderIfNeeded();
            foreach ($planche->values() as $slot => $eleve) {
                [$x, $y] = $this->slotPosition($slot);
                $this->drawCard($x, $y, $eleve);
            }

            $this->AddPage('L', 'A4');
            $this->drawPageHeaderIfNeeded('Verso');
            foreach ($planche->values() as $slot => $unused) {
                [$x, $y] = $this->slotPosition($slot);
                $this->drawCardBack($x, $y);
            }
        }

        return $this->Output('S');
    }

    /** @return array{0: float, 1: float} coin supérieur gauche de la case $slot */
    private function slotPosition(int $slot): array
    {
        $marginX = (297 - (self::COLS * self::CARD_W + (self::COLS - 1) * self::GUTTER_X)) / 2;

        return [
            $marginX + ($slot % self::COLS) * (self::CARD_W + self::GUTTER_X),
            self::MARGIN_TOP + intdiv($slot, self::COLS) * (self::CARD_H + self::GUTTER_Y),
        ];
    }

    private function drawPageHeaderIfNeeded(string $face = 'Recto'): void
    {
        if ($this->headerDrawnOnPage === $this->PageNo()) {
            return;
        }
        $this->headerDrawnOnPage = $this->PageNo();

        $this->SetFont('Helvetica', 'B', 13);
        $this->SetTextColor(...self::SLATE);
        $this->SetXY(0, 8);
        $this->Cell(297, 7, $this->txt('Cartes scolaires — '.$this->classeName.' ('.$face.')'), 0, 0, 'C');

        $this->SetFont('Helvetica', 'I', 9);
        $this->SetTextColor(120, 120, 120);
        $this->SetXY(0, 15);
        $this->Cell(297, 6, $this->txt('Année scolaire '.$this->anneeLabel), 0, 0, 'C');
    }

    /**
     * Verso commun à toutes les cartes : mentions réglementaires, cachet et
     * signature de l'établissement. Aucune donnée propre à l'élève n'y figure,
     * les cases se superposent donc au recto quel que soit le sens de retournement.
     */
    private function drawCardBack(float $x, float $y): void
    {
        $this->SetFillColor(...self::CREAM);
        $this->RoundedRect($x, $y, self::CARD_W, self::CARD_H, 3, 'F');
        $this->SetDrawColor(...self::GOLD);
        $this->SetLineWidth(0.5);
        $this->RoundedRect($x, $y, self::CARD_W, self::CARD_H, 3, 'D');
        $this->SetLineWidth(0.2);

        $this->SetFillColor(...self::SLATE);
        $this->Rect($x + 1, $y + 1, self::CARD_W - 2, 8, 'F');
        $this->SetTextColor(...self::GOLD);
        $this->SetFont('Helvetica', 'B', 6);
        $this->SetXY($x + 2, $y + 3);
        $this->Cell(self::CARD_W - 4, 4, $this->txt('RÈGLEMENT / REGULATIONS'), 0, 0, 'C');

        $this->SetTextColor(...self::SLATE);
        $this->SetFont('Helvetica', '', 4.6);
        $this->SetXY($x + 3, $y + 11);
        $this->MultiCell(self::CARD_W - 6, 2.4, $this->txt(
            'Cette carte est strictement personnelle. Elle doit être présentée '
            ."à toute réquisition des autorités de l'établissement. Toute perte "
            ."doit être déclarée immédiatement à l'administration."
        ), 0, 'J');

        $cachet = $this->cheminImage($this->school?->stamp_path);
        if ($cachet !== null) {
            $this->Image($cachet, $x + 21, $y + 40, 11, 11);
        }

        $signature = $this->cheminImage($this->school?->signature_path);
        if ($signature !== null) {
            $this->Image($signature, $x + 18, $y + 52, 17, 12);
        }

        $this->SetDrawColor(209, 219, 217);
        $this->Line($x + 8, $y + self::CARD_H - 16, $x + self::CARD_W - 8, $y + self::CARD_H - 16);
        $this->SetTextColor(136, 136, 136);
        $this->SetFont('Helvetica', 'I', 5);
        $this->SetXY($x + 2, $y + self::CARD_H - 14);
        $this->Cell(self::CARD_W - 4, 3, $this->txt("Le Chef d'établissement"), 0, 0, 'C');

        $this->SetFont('Helvetica', '', 4.8);
        $this->SetXY($x + 2, $y + self::CARD_H - 9);
        $this->MultiCell(self::CARD_W - 4, 2.4, $this->txt($this->school?->name ?? ''), 0, 'C');
    }

    private function cheminImage(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $absolu = Storage::disk('public')->path($path);

        return is_file($absolu) ? $absolu : null;
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

        $logo = $this->cheminImage($this->school?->logo_path);
        if ($logo !== null) {
            $this->Image($logo, $x + 2.5, $y + 2.5, 9, 9);
        }

        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Helvetica', 'B', 7.5);
        $this->SetXY($x + ($logo !== null ? 12 : 2), $y + 2.5);
        $this->MultiCell(self::CARD_W - ($logo !== null ? 14 : 4), 3.2, $this->txt(mb_strtoupper($this->school?->name ?? '')), 0, 'C');

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
            $initiales = BulletinGenerator::initialesDe($eleve->nom_complet);
            $this->SetTextColor(...self::GOLD);
            $this->SetFont('Helvetica', 'B', 12);
            $this->SetXY($photoX, $photoY + 7);
            $this->Cell($photoSize, 6, $this->txt($initiales), 0, 0, 'C');
        }

        // Nom
        $this->SetTextColor(...self::SLATE);
        $this->SetFont('Helvetica', 'B', 7.5);
        $this->SetXY($x + 2, $y + 41);
        $this->MultiCell(self::CARD_W - 4, 3.2, $this->txt($eleve->nom_complet), 0, 'C');

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
