<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Procès-verbal du conseil de classe</title>
@include('pdf.partials.styles')
</head>
<body>

@include('pdf.partials.header', ['school' => $school])

<div class="doc-title">
    <div class="fr">Procès-verbal du conseil de classe</div>
    <div class="en">Class council minutes</div>
    <div class="meta">
        {{ $classe->nom }} — {{ $trimestre->libelle }} — Année scolaire {{ $trimestre->anneeScolaire->libelle }}
    </div>
</div>

<p style="margin: 8px 0 10px;">
    Le conseil de classe, réuni le <strong>____________________</strong>, a examiné la situation des
    {{ $eleves->count() }} élèves de la classe <strong>{{ $classe->nom }}</strong> pour le
    {{ $trimestre->libelle }} et formulé les décisions suivantes :
</p>

<table class="datatable">
    <thead>
        <tr>
            <th class="text-left">Rang</th>
            <th class="text-left">Élève / Student</th>
            <th>Moy. / Av</th>
            <th>Cote</th>
            <th class="text-left">Mention travail</th>
            <th class="text-left">Mention conduite</th>
            <th class="text-left">Décision du conseil / Decision</th>
        </tr>
    </thead>
    <tbody>
    @php
        $libellesMention = [
            'felicitations' => 'Félicitations',
            'encouragements' => 'Encouragements',
            'avertissement_travail' => 'Avertissement travail',
            'blame_travail' => 'Blâme travail',
            'avertissement_conduite' => 'Avertissement conduite',
            'blame_conduite' => 'Blâme conduite',
        ];
    @endphp
    @forelse ($eleves as $bulletin)
        <tr>
            <td>{{ $bulletin['rang'] ?? '—' }}</td>
            <td class="text-left">{{ $bulletin['eleve']->nom_complet }}</td>
            <td>{{ $bulletin['moyenne_generale'] !== null ? number_format($bulletin['moyenne_generale'], 2) : '—' }}</td>
            <td>{{ $bulletin['cote'] }}</td>
            <td class="text-left">{{ $libellesMention[$bulletin['mention_travail']] ?? '—' }}</td>
            <td class="text-left">{{ $libellesMention[$bulletin['mention_conduite']] ?? '—' }}</td>
            <td class="text-left" style="font-size:8px;">{{ $bulletin['conseil'] }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="7" class="text-left">Aucun élève actif dans cette classe.</td>
        </tr>
    @endforelse
    </tbody>
</table>

<div class="stats-banner">
    Moyenne de classe : {{ $stats['moyenne_classe'] !== null ? number_format($stats['moyenne_classe'], 2) : '—' }}
    &nbsp;·&nbsp; Taux de réussite : {{ $stats['pourcentage_reussite'] }}%
    &nbsp;·&nbsp; Premier : {{ $stats['premier'] !== null ? number_format($stats['premier'], 2) : '—' }}
    &nbsp;·&nbsp; Dernier : {{ $stats['dernier'] !== null ? number_format($stats['dernier'], 2) : '—' }}
</div>

<div class="info-box">
    <span class="box-title">Note / Note</span>
    <span class="box-value" style="font-size:8.5px;">
        La colonne « Décision du conseil » reprend le constat calculé par la plateforme à titre indicatif :
        le conseil peut le confirmer tel quel ou porter une décision différente à la main avant signature.
    </span>
</div>

@include('pdf.partials.signatures', ['school' => $school, 'roles' => [
    ['fr' => 'Le Professeur Principal', 'en' => 'Class Master'],
    ['fr' => 'Le Censeur', 'en' => 'Vice Principal'],
    ['fr' => "Le Chef d'Établissement", 'en' => "Principal"],
]])

</body>
</html>
