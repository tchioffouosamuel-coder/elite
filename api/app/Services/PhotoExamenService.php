<?php

namespace App\Services;

use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Setting;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Photos de candidats aux examens officiels (DECC / OBC).
 *
 * Portage de `photo_examZIP.php` (_smapp) : chaque photo d'élève est recomposée
 * sur un fond blanc de 400×500 px, la date en haut, le nom du candidat et le
 * code d'examen en bas. Le legacy construisait bien l'image mais n'appelait
 * jamais `imagejpeg()` — rien n'était écrit sur disque. On corrige ce défaut,
 * et le code du centre, codé en dur à « CR 1357 », devient un réglage
 * d'établissement.
 *
 * Le livrable reste une archive ZIP d'images individuelles : les organismes
 * d'examen ingèrent un fichier par candidat, un document unique ne conviendrait
 * pas ici.
 */
class PhotoExamenService extends BaseService
{
    private const LARGEUR = 400;

    private const HAUTEUR = 500;

    /** Hauteur réservée à la date, au-dessus de la photo. */
    private const MARGE_HAUT = 50;

    private const TAILLE_POLICE = 15;

    /** DejaVu est fourni par mPDF, déjà installé — pas de police à embarquer. */
    private const POLICE = 'vendor/mpdf/mpdf/ttfonts/DejaVuSans.ttf';

    /**
     * Candidats d'une classe d'examen, avec l'état de leur photo.
     *
     * @return array{classe: array, code_examen: ?string, centre: string, candidats: array}
     */
    public function candidats(Classe $classe): array
    {
        $eleves = $classe->eleves()
            ->where('statut', 'actif')
            ->orderBy('nom')
            ->orderBy('prenom')
            ->get();

        $candidats = $eleves->map(fn (Eleve $e) => [
            'eleve_id' => $e->id,
            'matricule' => $e->matricule,
            'nom_complet' => $e->nomComplet(),
            'sexe' => $e->sexe,
            'photo_url' => $e->photo_path ? asset('storage/'.$e->photo_path) : null,
            'photo_prete' => $this->photoUtilisable($e),
        ])->all();

        return [
            'classe' => ['id' => $classe->id, 'nom' => $classe->nom],
            'code_examen' => $classe->code_examen,
            'centre' => (string) Setting::get($classe->school_id, 'centre_examen', ''),
            'candidats' => $candidats,
            'prets' => collect($candidats)->where('photo_prete', true)->count(),
            'manquants' => collect($candidats)->where('photo_prete', false)->count(),
        ];
    }

    /**
     * Archive ZIP des photos traitées, un JPEG par candidat.
     *
     * Les élèves sans photo exploitable sont ignorés : mieux vaut un envoi
     * partiel identifiable qu'une vignette vide déposée chez l'organisme.
     *
     * @return array{contenu: string, traites: int, ignores: array<int, string>}
     */
    public function archive(Classe $classe): array
    {
        $donnees = $this->candidats($classe);
        $chemin = tempnam(sys_get_temp_dir(), 'decc');

        $zip = new ZipArchive;
        if ($zip->open($chemin, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Impossible de créer l'archive des photos.");
        }

        $traites = 0;
        $ignores = [];
        $utilises = [];

        foreach ($classe->eleves()->where('statut', 'actif')->orderBy('nom')->get() as $eleve) {
            if (! $this->photoUtilisable($eleve)) {
                $ignores[] = $eleve->nomComplet();

                continue;
            }

            $jpeg = $this->composer($eleve, $donnees['code_examen'], $donnees['centre']);
            if ($jpeg === null) {
                $ignores[] = $eleve->nomComplet();

                continue;
            }

            $zip->addFromString($this->nomFichier($eleve, $utilises), $jpeg);
            $traites++;
        }

        $zip->close();

        $contenu = (string) file_get_contents($chemin);
        unlink($chemin);

        return ['contenu' => $contenu, 'traites' => $traites, 'ignores' => $ignores];
    }

    /**
     * Nom du fichier candidat. _smapp nommait d'après le seul nom de l'élève,
     * ce qui écrasait silencieusement les homonymes dans l'archive ; on préfixe
     * par le matricule quand il existe et on suffixe en dernier recours.
     */
    private function nomFichier(Eleve $eleve, array &$utilises): string
    {
        $base = $eleve->matricule
            ? Str::slug($eleve->matricule.'-'.$eleve->nomComplet())
            : Str::slug($eleve->nomComplet());

        $nom = $base;
        $suffixe = 2;
        while (isset($utilises[$nom])) {
            $nom = $base.'-'.$suffixe++;
        }

        $utilises[$nom] = true;

        return $nom.'.jpg';
    }

    /** Une photo n'est exploitable que si le fichier existe et se décode. */
    private function photoUtilisable(Eleve $eleve): bool
    {
        $chemin = $this->cheminPhoto($eleve);

        return $chemin !== null && @getimagesize($chemin) !== false;
    }

    private function cheminPhoto(Eleve $eleve): ?string
    {
        if (! $eleve->photo_path) {
            return null;
        }

        $chemin = storage_path('app/public/'.ltrim($eleve->photo_path, '/'));

        return is_file($chemin) ? $chemin : null;
    }

    /** Compose la vignette du candidat et renvoie le JPEG encodé. */
    private function composer(Eleve $eleve, ?string $codeExamen, string $centre): ?string
    {
        $chemin = $this->cheminPhoto($eleve);
        if ($chemin === null) {
            return null;
        }

        $source = $this->ouvrir($chemin);
        if ($source === null) {
            return null;
        }

        $canvas = imagecreatetruecolor(self::LARGEUR, self::HAUTEUR);
        imagefilledrectangle($canvas, 0, 0, self::LARGEUR, self::HAUTEUR, imagecolorallocate($canvas, 255, 255, 255));

        // La photo occupe un carré : la source est recadrée au centre plutôt
        // qu'étirée, _smapp déformait les portraits non carrés.
        $cote = min(imagesx($source), imagesy($source));
        imagecopyresampled(
            $canvas,
            $source,
            0,
            self::MARGE_HAUT,
            (int) ((imagesx($source) - $cote) / 2),
            (int) ((imagesy($source) - $cote) / 2),
            self::LARGEUR,
            self::LARGEUR,
            $cote,
            $cote
        );

        $bleu = imagecolorallocate($canvas, 0, 0, 255);
        $police = base_path(self::POLICE);

        $this->texteCentre($canvas, $police, $bleu, date('d/m/Y'), 30);
        $this->texteCentre($canvas, $police, $bleu, mb_strtoupper($eleve->nomComplet()), 470);
        $this->texteCentre($canvas, $police, $bleu, trim(($codeExamen ?? '').'  '.$centre), 490);

        ob_start();
        imagejpeg($canvas, null, 90);
        $jpeg = (string) ob_get_clean();

        imagedestroy($canvas);
        imagedestroy($source);

        return $jpeg;
    }

    /** @return \GdImage|null */
    private function ouvrir(string $chemin)
    {
        $type = @exif_imagetype($chemin);

        $image = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($chemin),
            IMAGETYPE_PNG => @imagecreatefrompng($chemin),
            IMAGETYPE_WEBP => @imagecreatefromwebp($chemin),
            default => false,
        };

        return $image === false ? null : $image;
    }

    /**
     * Écrit un texte centré. La chaîne est rognée si elle dépasse la largeur —
     * un nom très long débordait du cadre dans le legacy.
     */
    private function texteCentre(\GdImage $canvas, string $police, int $couleur, string $texte, int $y): void
    {
        if ($texte === '') {
            return;
        }

        while ($texte !== '' && $this->largeurTexte($police, $texte) > self::LARGEUR - 10) {
            $texte = mb_substr($texte, 0, mb_strlen($texte) - 1);
        }

        $x = (int) ((self::LARGEUR - $this->largeurTexte($police, $texte)) / 2);
        imagettftext($canvas, self::TAILLE_POLICE, 0, $x, $y, $couleur, $police, $texte);
    }

    private function largeurTexte(string $police, string $texte): int
    {
        $boite = imagettfbbox(self::TAILLE_POLICE, 0, $police, $texte);

        return $boite === false ? 0 : (int) ($boite[2] - $boite[0]);
    }
}
