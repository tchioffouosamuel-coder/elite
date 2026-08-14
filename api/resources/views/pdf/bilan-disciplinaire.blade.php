<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Bilan disciplinaire — {{ $classe->nom }}</title>
@include('pdf.partials.styles')
</head>
<body>

@php
    // Le secondaire cumule des heures de cours manquées, le primaire et la
    // maternelle des journées entières : libellés, suffixes et seuils de
    // sévérité suivent l'unité annoncée par le bilan.
    $enJours = ($bilan['unite'] ?? 'heures') === 'jours';
    $suffixe = $enJours ? ' j' : 'h';
    $libelleJust = $enJours ? 'Jours justifiés / Excused days' : 'Heures justifiées / Excused';
    $libelleNonJust = $enJours ? 'Jours non justifiés / Unexcused days' : 'Heures non justifiées / Unexcused';
    $libelleTotal = $enJours ? 'Total j. non just.' : 'Total h. non just.';
    // ~2 et ~6 journées, équivalents d'une semaine de classe aux 10h/30h du secondaire.
    [$seuilMoyen, $seuilHaut] = $enJours ? [2, 6] : [10, 30];
    $decimales = $enJours ? 0 : 1;
@endphp

@include('pdf.partials.header', ['school' => $classe->school])

<div class="doc-title">
    <div class="fr">Bilan disciplinaire — {{ $classe->nom }}</div>
    <div class="en">Discipline report — {{ $classe->nom }}</div>
    <div class="meta">{{ $trimestre->libelle }} — Année scolaire {{ $trimestre->anneeScolaire->libelle }}</div>
</div>

<table class="datatable">
    <tr>
        <td><span class="box-title" style="display:block;font-size:7.5px;text-transform:uppercase;">Effectif / Size</span><strong>{{ $bilan['effectif'] }}</strong></td>
        <td><span class="box-title" style="display:block;font-size:7.5px;text-transform:uppercase;">{{ $libelleTotal }}</span><strong>{{ $bilan['total_hnj'] }}{{ $suffixe }}</strong></td>
        <td><span class="box-title" style="display:block;font-size:7.5px;text-transform:uppercase;">Moyenne / élève</span><strong>{{ $bilan['moyenne_hnj'] }}{{ $suffixe }}</strong></td>
        <td><span class="box-title" style="display:block;font-size:7.5px;text-transform:uppercase;">Élève le plus absent</span><strong>{{ $bilan['eleve_plus_absent']['nom_complet'] ?? '—' }}</strong></td>
    </tr>
    <tr>
        <td colspan="2"><span class="box-title" style="display:block;font-size:7.5px;text-transform:uppercase;">Garçons — total / moyenne</span><strong>{{ $bilan['total_hnj_garcons'] }}{{ $suffixe }} / {{ $bilan['moyenne_hnj_garcons'] }}{{ $suffixe }}</strong></td>
        <td colspan="2"><span class="box-title" style="display:block;font-size:7.5px;text-transform:uppercase;">Filles — total / moyenne</span><strong>{{ $bilan['total_hnj_filles'] }}{{ $suffixe }} / {{ $bilan['moyenne_hnj_filles'] }}{{ $suffixe }}</strong></td>
    </tr>
</table>

<table class="datatable">
    <thead>
        <tr>
            <th class="text-left">Élève / Student</th>
            <th>Sexe / Sex</th>
            <th>{{ $libelleJust }}</th>
            <th>{{ $libelleNonJust }}</th>
        </tr>
    </thead>
    <tbody>
    @foreach ($eleves as $ligne)
        @php
            $hnj = $ligne['hnj'];
            $severite = $hnj > $seuilHaut ? 'high-absence' : ($hnj >= $seuilMoyen ? 'medium-absence' : 'low-absence');
        @endphp
        <tr>
            <td class="text-left">{{ $ligne['eleve']->nom_complet }}</td>
            <td>{{ $ligne['eleve']->sexe }}</td>
            <td>{{ number_format($ligne['hj'], $decimales) }}</td>
            <td class="{{ $severite }}">{{ number_format($hnj, $decimales) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<div class="info-box">
    <span class="box-title">Légende / Legend</span>
    <span class="box-value">
        <span class="low-absence" style="padding:1px 5px;border-radius:3px;">&lt; {{ $seuilMoyen }}{{ $suffixe }}</span>
        &nbsp;
        <span class="medium-absence" style="padding:1px 5px;border-radius:3px;">{{ $seuilMoyen }} – {{ $seuilHaut }}{{ $suffixe }}</span>
        &nbsp;
        <span class="high-absence" style="padding:1px 5px;border-radius:3px;">&gt; {{ $seuilHaut }}{{ $suffixe }}</span>
    </span>
</div>

@include('pdf.partials.signatures', ['roles' => [
    ['fr' => 'Le Surveillant Général', 'en' => 'Discipline Master'],
    ['fr' => "Le Chef d'Établissement", 'en' => "Principal"],
]])

</body>
</html>
