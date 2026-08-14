<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Palmarès — {{ $trimestre->libelle }}</title>
@include('pdf.partials.styles')
<style>
    .rang { font-weight: bold; color: #a56f18; }
</style>
</head>
<body>

@include('pdf.partials.header', ['school' => $school])

<div class="doc-title">
    <div class="fr">Palmarès — {{ $trimestre->libelle }}</div>
    <div class="en">Honour roll — {{ $trimestre->libelle }}</div>
    <div class="meta">
        Année scolaire {{ $trimestre->anneeScolaire->libelle }}
        @if($classeLabel) · {{ $classeLabel }} @endif
        — seuil : moyenne ≥ {{ $seuilMoyenne }}, absences non justifiées &lt; {{ $seuilAbsences }}h
    </div>
</div>

<table class="datatable">
    <thead>
        <tr>
            <th style="width:8%;">#</th>
            <th class="text-left">Élève / Student</th>
            <th>Classe / Class</th>
            <th style="width:16%;">Moyenne / Average</th>
        </tr>
    </thead>
    <tbody>
    @forelse ($eleves as $i => $row)
        <tr>
            <td class="rang">{{ $i + 1 }}</td>
            <td class="text-left">{{ $row['eleve']->nom_complet }}</td>
            <td>{{ $row['eleve']->classe?->nom }}</td>
            <td><strong>{{ number_format($row['moyenne'], 2) }}</strong></td>
        </tr>
    @empty
        <tr><td colspan="4" style="color:#888;">Aucun élève ne remplit les conditions ce trimestre.</td></tr>
    @endforelse
    </tbody>
</table>

@include('pdf.partials.signatures', ['roles' => [
    ['fr' => 'Le Censeur / Surveillant Général', 'en' => 'Vice-Principal / Discipline Master'],
    ['fr' => "Le Chef d'Établissement", 'en' => "Principal"],
]])

</body>
</html>
