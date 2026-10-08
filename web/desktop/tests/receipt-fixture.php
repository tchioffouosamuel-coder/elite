<?php

require dirname(__DIR__, 3).'/api/vendor/autoload.php';

$pdf = new Dompdf\Dompdf;
$pdf->loadHtml('<html><head><style>body { font-family: DejaVu Sans; } h1 { color: #481e67; } table { width: 100%; border-collapse: collapse; } td { padding: 12px; border: 1px solid #888; }</style></head><body><h1>ELITES SCHOOL</h1><h2>RECU TEST 2026-001</h2><p>Eleve : Alice Ngono</p><table><tr><td>Scolarite</td><td>25 000 FCFA</td></tr><tr><td>Total verse</td><td>25 000 FCFA</td></tr></table><p>Paiement en especes - 08/10/2026</p><div style="page-break-before: always"><h2>Deuxieme page</h2><p>Copie du recu - 25 000 FCFA</p></div></body></html>');
$pdf->setPaper('A5');
$pdf->render();
echo $pdf->output();
