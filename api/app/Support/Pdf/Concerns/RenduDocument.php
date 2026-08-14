<?php

namespace App\Support\Pdf\Concerns;

use App\Models\School;
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
    protected const OR = '#d8a02e';

    protected const ARDOISE = '#292F36';

    protected const LOGO_WIDTH = '140px';

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

    /** Repli quand l'établissement n'a pas encore chargé son logo. */
    protected function monogramme(School $school): string
    {
        $mots = preg_split('/\s+/', trim((string) $school->name)) ?: [];
        $lettres = array_map(static fn(string $mot) => mb_substr($mot, 0, 1), array_slice($mots, 0, 3));

        return mb_strtoupper(implode('', $lettres));
    }

    /** Styles partagés : tableaux à en-tête doré, titres bilingues, utilitaires. */
    protected function stylesBase(): string
    {
        return 'body{font-family:dejavusans,sans-serif;font-size:3.2mm;margin:0;padding:0;color:#333}'
            . 'table{width:100%;border-collapse:collapse;margin-top:4px;margin-bottom:6px}'
            . 'th,td{border:0.5px solid #bdc3c7;text-align:center;padding:1px}'
            . 'th{background-color:' . self::OR . ';color:#fff;font-weight:bold;font-size:2.6mm}'
            . '.header-table{width:100%;table-layout:fixed;margin-bottom:6px}'
            . '.header-table td{text-align:center;vertical-align:top;border:none;font-size:2.5mm}'
            . '.lh-1{line-height:1.25}'
            . '.logo{width:' . self::LOGO_WIDTH . ';height:auto}'
            . '.no-border,.no-border td,.no-border tr{border:none!important}'
            . '.titre{color:' . self::OR . ';text-transform:uppercase;font-weight:bold;font-size:3.8mm}'
            . '.titre-en{color:' . self::OR . ';text-transform:uppercase;font-style:italic;font-size:3.2mm}'
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

    /** En-tête bilingue à trois colonnes : mentions FR, logo, mentions EN. */
    protected function enTeteEcole(School $school): string
    {
        $logo = $this->cheminImage($school->logo_path);
        $celluleLogo = $logo !== null
            ? '<img src="' . $this->e($logo) . '" class="logo">'
            : '<div class="value" style="font-size:5mm;color:' . self::OR . ';">' . $this->e($this->monogramme($school)) . '</div>';

        return '<table class="header-table"><tr>'
            . '<td style="width:40%;"><div class="lh-1">' . $this->texteEnTete($school->header_fr, $school->name) . '</div></td>'
            . '<td style="width:20%;">' . $celluleLogo . '</td>'
            . '<td style="width:40%;"><div class="lh-1">' . $this->texteEnTete($school->header_en, $school->name) . '</div></td>'
            . '</tr></table>';
    }
}