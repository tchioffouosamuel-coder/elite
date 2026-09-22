<?php

namespace App\Support\Ocr;

use App\Models\Personnel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use thiagoalessio\TesseractOCR\TesseractOCR;

/**
 * Lit une photo de la fiche de présence journalière papier (voir
 * FichePresencePersonnelGenerator) et en extrait, ligne par ligne, le nom
 * imprimé le plus proche et les heures manuscrites qui l'accompagnent.
 *
 * Reconnaissance volontairement best-effort : l'écriture manuscrite reste
 * difficile pour Tesseract, donc chaque ligne extraite part avec un niveau
 * de confiance et reste éditable dans la modale de prévisualisation avant
 * import — voir ConfirmationImportOcrPresence.
 */
class PresenceOcrExtractor
{
    public function extraire(UploadedFile $image, int $schoolId): array
    {
        $texte = $this->reconnaitre($image);
        $personnels = Personnel::forSchool($schoolId)
            ->where('statut', 'actif')
            ->orderBy('nom_complet')
            ->get(['id', 'nom_complet']);

        $lignes = [];
        foreach (preg_split('/\r\n|\r|\n/', $texte) ?: [] as $ligneTexte) {
            $ligneTexte = trim($ligneTexte);
            if ($ligneTexte === '') {
                continue;
            }

            $heures = $this->heures($ligneTexte);
            $texteSansHeures = trim(preg_replace('/\d{1,2}\s*[:hH.]\s*\d{2}/', ' ', $ligneTexte) ?? '');
            // Retire le N° de rang imprimé en début de ligne (« 1 », « 12. », …).
            $texteSansHeures = trim(preg_replace('/^\d{1,3}\s*[.\-)]?\s*/', '', $texteSansHeures) ?? '');

            if ($texteSansHeures === '' && $heures === []) {
                continue;
            }

            $personnel = $this->correspondance($texteSansHeures, $personnels);

            $lignes[] = [
                'personnel_id' => $personnel?->id,
                'nom_complet' => $personnel?->nom_complet ?? $texteSansHeures,
                'texte_ocr' => $ligneTexte,
                'heure_arrivee' => $heures[0] ?? null,
                'heure_depart' => $heures[1] ?? null,
                'confiance' => $personnel ? 'haute' : 'faible',
            ];
        }

        return [
            'lignes' => $lignes,
            'personnels' => $personnels
                ->map(fn (Personnel $p) => ['id' => $p->id, 'nom_complet' => $p->nom_complet])
                ->values()
                ->all(),
        ];
    }

    private function reconnaitre(UploadedFile $image): string
    {
        $ocr = new TesseractOCR($image->getRealPath());
        $ocr->lang(config('services.tesseract.lang', 'fra'));
        $ocr->psm((int) config('services.tesseract.psm', 6));

        if ($executable = config('services.tesseract.executable')) {
            $ocr->executable($executable);
        }

        return $ocr->run();
    }

    /** @return string[] Heures « H:i » repérées, dans l'ordre de lecture de la ligne. */
    private function heures(string $ligne): array
    {
        preg_match_all('/(\d{1,2})\s*[:hH.]\s*(\d{2})/', $ligne, $matches, PREG_SET_ORDER);

        return array_map(fn (array $m) => sprintf('%02d:%02d', (int) $m[1], (int) $m[2]), $matches);
    }

    /** @param Collection<int, Personnel> $personnels */
    private function correspondance(string $texte, Collection $personnels): ?Personnel
    {
        $cle = $this->cle($texte);
        if ($cle === '') {
            return null;
        }

        $exact = $personnels->first(fn (Personnel $p) => $this->cle($p->nom_complet) === $cle);
        if ($exact) {
            return $exact;
        }

        // L'OCR avale ou déforme souvent une lettre en bord de mot : on
        // retombe sur une inclusion partielle, puis une similarité globale.
        return $personnels->first(function (Personnel $p) use ($cle) {
            $cleNom = $this->cle($p->nom_complet);
            if ($cleNom === '') {
                return false;
            }
            if (str_contains($cle, $cleNom) || str_contains($cleNom, $cle)) {
                return true;
            }
            similar_text($cle, $cleNom, $pourcentage);

            return $pourcentage >= 75.0;
        });
    }

    private function cle(string $valeur): string
    {
        return preg_replace('/[^a-z0-9]+/', '', Str::lower(Str::ascii($valeur))) ?? '';
    }
}
