<?php

namespace App\Support\Pdf\Concerns;

use App\Models\School;
use App\Services\VisaComposeService;
use App\Support\Pdf\EnTeteHtml;

/**
 * Primitives communes aux documents mPDF de l'établissement : palette, échappement,
 * résolution des images d'établissement et en-tête bilingue à trois colonnes.
 *
 * Extraites de BulletinGenerator pour que tout nouveau document (statistiques,
 * bilans) parte du même rendu plutôt que d'en recopier une variante.
 */
trait RenduDocument
{
    protected const ACCENT = '#39b54a';

    protected const ARDOISE = '#292F36';

    /** Boîte maximale du logo, en mm — un logo carré/large atteint la largeur, un logo tout en hauteur (fréquent : bannières empilées) plafonne sur la hauteur avant. */
    protected const LOGO_MAX_LARGEUR_MM = 30.0;

    protected const LOGO_MAX_HAUTEUR_MM = 18.0;

    protected function e(?string $valeur): string
    {
        return htmlspecialchars((string) $valeur, ENT_QUOTES, 'UTF-8');
    }

    protected function nombre(?float $valeur, int $decimales = 2): string
    {
        return $valeur === null ? '—' : number_format($valeur, $decimales, ',', ' ');
    }

    /**
     * Chemin disque d'une image d'établissement (logo, cachet, signature).
     * mPDF lit le fichier directement : une URL publique obligerait le serveur
     * à se requêter lui-même pendant la génération.
     */
    protected function cheminImage(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $complet = storage_path('app/public/' . ltrim($path, '/'));

        return is_file($complet) ? $complet : null;
    }

    /**
     * Boîte maximale du logo pour ce document, en mm — les documents denses en
     * tableaux (bulletins) la réduisent en la redéfinissant ; les autres
     * gardent ce plafond par défaut.
     *
     * @return array{largeur: float, hauteur: float}
     */
    protected function logoBoiteMax(): array
    {
        return ['largeur' => self::LOGO_MAX_LARGEUR_MM, 'hauteur' => self::LOGO_MAX_HAUTEUR_MM];
    }

    /**
     * Dimensions d'affichage du logo, calculées depuis ses pixels réels plutôt
     * que fixées : un emblème carré/large est borné par la largeur, un logo
     * tout en hauteur (bannières empilées, cas fréquent des cachets d'école)
     * par la hauteur — sans ce second plafond, un tel logo étirait l'en-tête
     * de plusieurs centimètres et poussait un bulletin déjà serré sur une
     * deuxième page.
     *
     * @return array{largeur: float, hauteur: float} en mm
     */
    protected function tailleLogo(string $chemin): array
    {
        $boite = $this->logoBoiteMax();

        $dimensions = @getimagesize($chemin);
        if ($dimensions === false || $dimensions[0] <= 0 || $dimensions[1] <= 0) {
            return ['largeur' => $boite['largeur'], 'hauteur' => $boite['hauteur']];
        }

        $ratio = $dimensions[0] / $dimensions[1];
        $largeur = $boite['largeur'];
        $hauteur = $largeur / $ratio;

        if ($hauteur > $boite['hauteur']) {
            $hauteur = $boite['hauteur'];
            $largeur = $hauteur * $ratio;
        }

        return ['largeur' => round($largeur, 1), 'hauteur' => round($hauteur, 1)];
    }

    /** Repli quand l'établissement n'a pas encore chargé son logo. */
    protected function monogramme(School $school): string
    {
        $mots = preg_split('/\s+/', trim((string) $school->name)) ?: [];
        $lettres = array_map(static fn(string $mot) => mb_substr($mot, 0, 1), array_slice($mots, 0, 3));

        return mb_strtoupper(implode('', $lettres));
    }

    /** Styles partagés : tableaux à en-tête coloré, titres bilingues, utilitaires. */
    protected function stylesBase(): string
    {
        return 'body{font-family:montserrat,sans-serif;font-size:3.2mm;margin:0;padding:0;color:#333}'
            . 'table{width:100%;border-collapse:collapse;margin-top:4px;margin-bottom:6px}'
            . 'th,td{border:0.5px solid #bdc3c7;text-align:center;padding:1px}'
            . 'th{background-color:' . self::ACCENT . ';color:#fff;font-weight:bold;font-size:2.6mm}'
            . '.header-table{width:100%;table-layout:fixed;margin-bottom:6px}'
            . '.header-table td{text-align:center;vertical-align:top;border:none;font-size:2.5mm}'
            . '.lh-1{line-height:1.25}'
            . '.no-border,.no-border td,.no-border tr{border:none!important}'
            . '.titre{color:' . self::ACCENT . ';text-transform:uppercase;font-weight:bold;font-size:3.8mm}'
            . '.titre-en{color:' . self::ACCENT . ';text-transform:uppercase;font-style:italic;font-size:3.2mm}'
            . '.left{text-align:left!important}'
            . '.value{font-weight:bold;color:#000}'
            . '.mini{font-size:2.2mm}'
            . '.legende{font-size:2.1mm;text-align:center;display:block}'
            . '.rouge{color:#ac3527;font-weight:bold}';
    }

    /** En-tête FR/EN de l'établissement, saisi via l'éditeur riche ; replie sur le nom si vide. */
    protected function texteEnTete(?string $valeur, string $repli): string
    {
        $rendu = EnTeteHtml::render($valeur);

        return $rendu !== '' ? $rendu : nl2br($this->e($repli));
    }

    /** Bloc de signature du chef d'établissement : lieu (déduit de l'adresse), date du jour, et cachet/signature scannés ou un espace à signer à la main. */
    protected function signatureChef(School $school): string
    {
        $ville = trim(explode(',', (string) $school->address)[0] ?? '');
        $visa = (new VisaComposeService)->chemin($school);
        $celluleVisa = $visa !== null
            ? '<br><img src="' . $this->e($visa) . '" style="height:46px;">'
            : '<br><br><br><br>';

        return '<table class="no-border" style="margin-top:8mm;"><tr>'
            .'<td class="no-border left" style="width:50%;font-size:2.8mm;vertical-align:top;">'
            .'Fait à '.$this->e($ville !== '' ? $ville : '…………').', le '.date('d/m/Y')
            .'</td>'
            .'<td class="no-border" style="width:50%;text-align:center;font-size:2.8mm;">'
            .'<b>Le Chef d\'Établissement</b><br><i>The Principal</i>'.$celluleVisa
            .'<span style="border-top:0.4px solid #000;">Signature et cachet</span>'
            .'</td></tr></table>';
    }

    /** En-tête bilingue à trois colonnes : mentions FR, logo, mentions EN. */
    protected function enTeteEcole(School $school): string
    {
        $logo = $this->cheminImage($school->logo_path);
        $celluleLogo = $logo !== null
            ? (function () use ($logo) {
                $taille = $this->tailleLogo($logo);

                return '<img src="' . $this->e($logo) . '" style="width:' . $taille['largeur'] . 'mm;height:' . $taille['hauteur'] . 'mm;">';
            })()
            : '<div class="value" style="font-size:5mm;color:' . self::ACCENT . ';">' . $this->e($this->monogramme($school)) . '</div>';

        return '<table class="header-table"><tr>'
            . '<td style="width:40%;"><div class="lh-1">' . $this->texteEnTete($school->header_fr, $school->name) . '</div></td>'
            . '<td style="width:20%;">' . $celluleLogo . '</td>'
            . '<td style="width:40%;"><div class="lh-1">' . $this->texteEnTete($school->header_en, $school->name) . '</div></td>'
            . '</tr></table>';
    }
}