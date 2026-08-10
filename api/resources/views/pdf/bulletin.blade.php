<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Bulletin — {{ $eleve->nomComplet() }}</title>
@include('pdf.partials.styles')
<style>
    table.notes th, table.notes td { font-size: 8px; padding: 3px 4px; }
    table.notes td.matiere { text-align: left; font-weight: bold; }
    table.notes tr.groupe-sep td { background: #f1f5f1 !important; font-weight: bold; text-align: left; color: #292F36; }
</style>
</head>
<body>

@include('pdf.partials.header', ['school' => $classe->school])

<div class="doc-title">
    <div class="fr">Bulletin de notes du {{ $trimestre->libelle }}</div>
    <div class="en">{{ $trimestre->libelle }} report card</div>
    <div class="meta">Année scolaire / Academic year : <strong>{{ $anneeScolaire->libelle }}</strong></div>
</div>

<table class="datatable">
    <tr>
        <td class="text-left" style="width:25%;"><strong>Élève / Student :</strong> {{ $eleve->nomComplet() }}</td>
        <td class="text-left" style="width:20%;"><strong>Matricule / ID :</strong> {{ $eleve->matricule ?? '—' }}</td>
        <td class="text-left" style="width:15%;"><strong>Sexe / Sex :</strong> {{ $eleve->sexe === 'F' ? 'F' : 'M' }}</td>
        <td class="text-left" style="width:20%;"><strong>Classe / Class :</strong> {{ $classe->nom }}</td>
        <td class="text-left" style="width:20%;"><strong>Effectif / Class size :</strong> {{ $effectif['total'] }} ({{ $effectif['garcons'] }}G / {{ $effectif['filles'] }}F)</td>
    </tr>
</table>

<table class="datatable notes">
    <thead>
        <tr>
            <th class="text-left">Matière / Subject</th>
            <th>Enseignant / Teacher</th>
            @foreach ($trimestre->sequences as $sequence)
                <th>{{ $sequence->libelle }}</th>
            @endforeach
            <th>Moy. / Avg</th>
            <th>Coef</th>
            <th>Total</th>
            <th>Cote</th>
            <th>Rang</th>
            <th>Min</th>
            <th>Max</th>
        </tr>
    </thead>
    <tbody>
    @foreach ($lignes as $groupe => $matieres)
        @foreach ($matieres as $ligne)
            <tr>
                <td class="matiere">{{ $ligne['matiere'] }}</td>
                <td>{{ $ligne['enseignant'] }}</td>
                @foreach ($ligne['notes'] as $note)
                    <td>{{ $note !== null ? number_format($note, 2) : '—' }}</td>
                @endforeach
                <td>{{ $ligne['moyenne'] !== null ? number_format($ligne['moyenne'], 2) : '—' }}</td>
                <td>{{ number_format($ligne['coefficient'], 1) }}</td>
                <td>{{ $ligne['total'] !== null ? number_format($ligne['total'], 2) : '—' }}</td>
                <td>{{ $ligne['cote'] }}</td>
                <td>{{ $ligne['rang'] ?? '—' }}</td>
                <td>{{ $ligne['min'] !== null ? number_format($ligne['min'], 2) : '—' }}</td>
                <td>{{ $ligne['max'] !== null ? number_format($ligne['max'], 2) : '—' }}</td>
            </tr>
        @endforeach
    @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td class="matiere text-left" colspan="2">TOTAUX / TOTALS</td>
            <td colspan="{{ $trimestre->sequences->count() }}"></td>
            <td></td>
            <td>{{ number_format($total_coef, 1) }}</td>
            <td>{{ number_format($total_points, 2) }}</td>
            <td colspan="4"></td>
        </tr>
    </tfoot>
</table>

<table class="datatable" style="margin-bottom:8px;">
    <tr>
        <td style="width:22%;">
            <span class="box-title" style="display:block;font-size:7.5px;text-transform:uppercase;">Moyenne générale / Overall average</span>
            <span class="accent-value">{{ $moyenne_generale !== null ? number_format($moyenne_generale, 2) : '—' }} / 20</span>
        </td>
        <td style="width:14%;">
            <span class="box-title" style="display:block;font-size:7.5px;text-transform:uppercase;">Rang / Rank</span>
            {{ $rang_general ?? '—' }} / {{ $effectif['total'] }}
        </td>
        <td style="width:12%;">
            <span class="box-title" style="display:block;font-size:7.5px;text-transform:uppercase;">Cote / Grade</span>
            {{ $cote_generale }}
        </td>
        <td class="text-left" style="width:26%;">
            <span class="box-title" style="display:block;font-size:7.5px;text-transform:uppercase;">Absences (h) / Absence hours</span>
            Just. : {{ number_format($heures_justifiees, 1) }} — Non just. : {{ number_format($heures_non_justifiees, 1) }}
        </td>
        <td class="text-left" style="width:26%;">
            <span class="box-title" style="display:block;font-size:7.5px;text-transform:uppercase;">Appréciation / Remark</span>
            {{ [
                'tres_bien' => 'Très bien',
                'bien' => 'Bien',
                'assez_bien' => 'Assez bien',
                'passable' => 'Passable',
                'insuffisant' => 'Insuffisant',
            ][$appreciation] ?? '—' }}
        </td>
    </tr>
</table>

@php
    $labelsMention = [
        'felicitations' => 'Félicitations / Congratulations',
        'encouragements' => 'Encouragements',
        'avertissement_travail' => 'Avertissement travail / Work warning',
        'blame_travail' => 'Blâme travail / Work blame',
        'avertissement_conduite' => 'Avertissement conduite / Conduct warning',
        'blame_conduite' => 'Blâme conduite / Conduct blame',
    ];
@endphp
@if ($mention_travail || $mention_conduite)
<div class="info-box">
    <span class="box-title">Distinction</span>
    <span class="box-value">{{ collect([$labelsMention[$mention_travail] ?? null, $labelsMention[$mention_conduite] ?? null])->filter()->implode(' · ') }}</span>
</div>
@endif

@if ($sanctions->isNotEmpty())
<div class="info-box" style="border-color:#ac3527;">
    <span class="box-title">Sanctions du trimestre / Term sanctions</span>
    <span class="box-value">
        @foreach ($sanctions as $sanction)
            {{ $sanction->date_sanction->format('d/m/Y') }} — {{ $sanction->motif }}@if(!$loop->last), @endif
        @endforeach
    </span>
</div>
@endif

@include('pdf.partials.signatures', ['roles' => [
    ['fr' => 'Le Professeur Principal', 'en' => 'Class Master'],
    ['fr' => 'Le Censeur / Surveillant Général', 'en' => 'Vice-Principal / Discipline Master'],
    ['fr' => "Le Chef d'Établissement", 'en' => "Principal"],
]])

</body>
</html>
