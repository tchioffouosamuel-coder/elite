<?php

namespace App\Support\Pdf;

use Mpdf\Mpdf;

class GenerateurStatistiquesGenerator
{
    protected Mpdf $mpdf;

    public function __construct()
    {
        $this->mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'default_font' => 'dejavusans',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 20,
            'margin_bottom' => 15,
        ]);
    }

    public function build(array $stats): string
    {
        $html = $this->generateHtml($stats);
        $this->mpdf->WriteHTML($html);

        return $this->mpdf->Output('', 'S');
    }

    protected function generateHtml(array $stats): string
    {
        $departement = $stats['departement'] ?? [];
        $trimestre = $stats['trimestre'] ?? [];
        $matieres = $stats['matieres'] ?? [];
        $statsConsolidees = $stats['stats_consolidees'] ?? [];

        $nomDept = $departement['nom'] ?? 'Département';
        $libelleTrimestre = $trimestre['libelle'] ?? 'Trimestre';
        $effectifTotal = $statsConsolidees['effectif_total'] ?? 0;
        $moyenneGenerale = $statsConsolidees['moyenne_generale'] ?? null;
        $tauxReussiteMoyen = $statsConsolidees['taux_reussite_moyen'] ?? null;

        $styleGlobal = <<<'CSS'
        <style>
            body {
                font-family: DejaVuSans;
                font-size: 11pt;
                color: #333;
            }
            .header {
                margin-bottom: 30px;
                border-bottom: 2px solid #1a3a52;
                padding-bottom: 15px;
            }
            .header h1 {
                margin: 0;
                color: #1a3a52;
                font-size: 20pt;
                text-align: center;
            }
            .header p {
                margin: 5px 0 0 0;
                text-align: center;
                color: #666;
                font-size: 10pt;
            }
            .stats-container {
                margin-bottom: 25px;
            }
            .stats-title {
                background-color: #1a3a52;
                color: white;
                padding: 10px 15px;
                margin-bottom: 15px;
                font-weight: bold;
                font-size: 12pt;
            }
            .stats-grid {
                display: table;
                width: 100%;
                margin-bottom: 20px;
            }
            .stat-item {
                display: table-cell;
                width: 33%;
                padding: 15px;
                background-color: #f5f5f5;
                border: 1px solid #ddd;
                text-align: center;
            }
            .stat-label {
                font-size: 10pt;
                color: #666;
                margin-bottom: 8px;
            }
            .stat-value {
                font-size: 18pt;
                font-weight: bold;
                color: #1a3a52;
            }
            .matieres-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 15px;
            }
            .matieres-table th {
                background-color: #1a3a52;
                color: white;
                padding: 10px;
                text-align: left;
                font-weight: bold;
                font-size: 10pt;
                border: 1px solid #1a3a52;
            }
            .matieres-table td {
                padding: 10px;
                border: 1px solid #ddd;
                font-size: 10pt;
            }
            .matieres-table tr:nth-child(even) {
                background-color: #f9f9f9;
            }
            .text-right {
                text-align: right;
            }
            .text-center {
                text-align: center;
            }
            .footer {
                margin-top: 40px;
                text-align: center;
                font-size: 9pt;
                color: #999;
                border-top: 1px solid #ddd;
                padding-top: 15px;
            }
        </style>
        CSS;

        $html = $styleGlobal;
        $html .= <<<HTML
        <div class="header">
            <h1>Rapport de Statistiques Pédagogiques</h1>
            <p>Département: <strong>{$nomDept}</strong> | Trimestre: <strong>{$libelleTrimestre}</strong></p>
        </div>

        <div class="stats-container">
            <div class="stats-title">Statistiques Consolidées</div>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-label">Effectif Total</div>
                    <div class="stat-value">{$effectifTotal}</div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">Moyenne Générale</div>
                    <div class="stat-value">
                        {$this->formatNumber($moyenneGenerale)}
                    </div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">Taux de Réussite Moyen</div>
                    <div class="stat-value">
                        {$this->formatNumber($tauxReussiteMoyen, 1)}%
                    </div>
                </div>
            </div>
        </div>

        <div class="stats-container">
            <div class="stats-title">Détails par Matière</div>
            <table class="matieres-table">
                <thead>
                    <tr>
                        <th>Matière</th>
                        <th class="text-center">Effectif</th>
                        <th class="text-center">Moyenne</th>
                        <th class="text-center">Taux de Réussite</th>
                    </tr>
                </thead>
                <tbody>
                    {$this->generateMatieresRows($matieres)}
                </tbody>
            </table>
        </div>

        <div class="footer">
            <p>Rapport généré le: <strong>{$this->getCurrentDate()}</strong></p>
        </div>
        HTML;

        return $html;
    }

    protected function generateMatieresRows(array $matieres): string
    {
        $rows = '';
        foreach ($matieres as $matiere) {
            $nom = $matiere['nom'] ?? '—';
            $effectif = $matiere['effectif_eleves'] ?? 0;
            $moyenne = $this->formatNumber($matiere['moyenne'] ?? null);
            $tauxReussite = $this->formatNumber($matiere['taux_reussite'] ?? null, 1);

            $rows .= <<<HTML
            <tr>
                <td>{$nom}</td>
                <td class="text-center">{$effectif}</td>
                <td class="text-center">{$moyenne}</td>
                <td class="text-center">{$tauxReussite}%</td>
            </tr>
            HTML;
        }

        return $rows;
    }

    protected function formatNumber(?float $value, int $decimals = 2): string
    {
        return $value !== null ? number_format($value, $decimals, ',', ' ') : '—';
    }

    protected function getCurrentDate(): string
    {
        $date = new \DateTime('now', new \DateTimeZone('Africa/Kinshasa'));

        return $date->format('d/m/Y H:i');
    }
}
