<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Bilan disciplinaire — {{ $classe->nom }}</title>
@include('pdf.partials.styles')
</head>
<body>

@include('pdf.partials.header', ['school' => $classe->school])

<div class="doc-title">
    <div class="fr">Bilan disciplinaire — {{ $classe->nom }}</div>
    <div class="en">Discipline report — {{ $classe->nom }}</div>
    <div class="meta">{{ $trimestre->libelle }} — Année scolaire {{ $trimestre->anneeScolaire->libelle }}</div>
</div>

<table class="datatable">
    <tr>
        <td><span class="box-title" style="display:block;font-size:7.5px;text-transform:uppercase;">Effectif / Size</span><strong>{{ $bilan['effectif'] }}</strong></td>
        <td><span class="box-title" style="display:block;font-size:7.5px;text-transform:uppercase;">Total h. non just.</span><strong>{{ $bilan['total_hnj'] }}h</strong></td>
        <td><span class="box-title" style="display:block;font-size:7.5px;text-transform:uppercase;">Moyenne / élève</span><strong>{{ $bilan['moyenne_hnj'] }}h</strong></td>
        <td><span class="box-title" style="display:block;font-size:7.5px;text-transform:uppercase;">Élève le plus absent</span><strong>{{ $bilan['eleve_plus_absent']['nom_complet'] ?? '—' }}</strong></td>
    </tr>
    <tr>
        <td colspan="2"><span class="box-title" style="display:block;font-size:7.5px;text-transform:uppercase;">Garçons — total / moyenne</span><strong>{{ $bilan['total_hnj_garcons'] }}h / {{ $bilan['moyenne_hnj_garcons'] }}h</strong></td>
        <td colspan="2"><span class="box-title" style="display:block;font-size:7.5px;text-transform:uppercase;">Filles — total / moyenne</span><strong>{{ $bilan['total_hnj_filles'] }}h / {{ $bilan['moyenne_hnj_filles'] }}h</strong></td>
    </tr>
</table>

<table class="datatable">
    <thead>
        <tr>
            <th class="text-left">Élève / Student</th>
            <th>Sexe / Sex</th>
            <th>Heures justifiées / Excused</th>
            <th>Heures non justifiées / Unexcused</th>
        </tr>
    </thead>
    <tbody>
    @foreach ($eleves as $ligne)
        @php
            $hnj = $ligne['hnj'];
            $severite = $hnj > 30 ? 'high-absence' : ($hnj >= 10 ? 'medium-absence' : 'low-absence');
        @endphp
        <tr>
            <td class="text-left">{{ $ligne['eleve']->nomComplet() }}</td>
            <td>{{ $ligne['eleve']->sexe }}</td>
            <td>{{ number_format($ligne['hj'], 1) }}</td>
            <td class="{{ $severite }}">{{ number_format($hnj, 1) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<div class="info-box">
    <span class="box-title">Légende / Legend</span>
    <span class="box-value">
        <span class="low-absence" style="padding:1px 5px;border-radius:3px;">&lt; 10h</span>
        &nbsp;
        <span class="medium-absence" style="padding:1px 5px;border-radius:3px;">10 – 30h</span>
        &nbsp;
        <span class="high-absence" style="padding:1px 5px;border-radius:3px;">&gt; 30h</span>
    </span>
</div>

@include('pdf.partials.signatures', ['roles' => [
    ['fr' => 'Le Surveillant Général', 'en' => 'Discipline Master'],
    ['fr' => "Le Chef d'Établissement", 'en' => "Principal"],
]])

</body>
</html>
