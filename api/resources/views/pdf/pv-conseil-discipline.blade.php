<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Procès-verbal du conseil de discipline</title>
@include('pdf.partials.styles')
</head>
<body>

@include('pdf.partials.header', ['school' => $school])

<div class="doc-title">
    <div class="fr">Procès-verbal du conseil de discipline</div>
    <div class="en">Disciplinary council minutes</div>
    <div class="meta">
        {{ $trimestre->libelle }} — Année scolaire {{ $trimestre->anneeScolaire->libelle }}
        @if($classe) — {{ $classe->nom }} @endif
    </div>
</div>

<p style="margin: 8px 0 10px;">
    Le conseil de discipline, réuni le <strong>____________________</strong>, a examiné les
    {{ $sanctions->count() }} cas ci-dessous et statué comme suit :
</p>

<table class="datatable">
    <thead>
        <tr>
            <th class="text-left">Élève / Student</th>
            <th class="text-left">Classe / Class</th>
            <th class="text-left">Sanction proposée / Proposed</th>
            <th class="text-left">Motif / Reason</th>
            <th>Date</th>
            <th class="text-left">Décision / Decision</th>
        </tr>
    </thead>
    <tbody>
    @forelse ($sanctions as $sanction)
        <tr>
            <td class="text-left">{{ $sanction->eleve->nom_complet }}</td>
            <td class="text-left">{{ $sanction->classe->nom ?? '—' }}</td>
            <td class="text-left">{{ \App\Models\Sanction::LIBELLES_TYPES[$sanction->type] ?? $sanction->type }}</td>
            <td class="text-left" style="font-size:8.5px;">{{ \Illuminate\Support\Str::limit($sanction->motif, 90) }}</td>
            <td>{{ $sanction->date_sanction->format('d/m/Y') }}</td>
            <td class="text-left" style="white-space:nowrap;">&#9633; Confirmée &nbsp; &#9633; Annulée</td>
        </tr>
    @empty
        <tr>
            <td colspan="6" class="text-left">Aucune sanction en attente de décision pour cette période.</td>
        </tr>
    @endforelse
    </tbody>
</table>

<div class="info-box" style="margin-top:10px;">
    <span class="box-title">Note / Note</span>
    <span class="box-value" style="font-size:8.5px;">
        Une fois la décision du conseil enregistrée sur ce procès-verbal, elle doit être reportée dans la
        plateforme (confirmation ou annulation de chaque sanction) pour mettre à jour le dossier
        disciplinaire des élèves concernés.
    </span>
</div>

@include('pdf.partials.signatures', ['school' => $school, 'roles' => [
    ['fr' => 'Le Surveillant Général', 'en' => 'Discipline Master'],
    ['fr' => 'Le Censeur', 'en' => 'Vice Principal'],
    ['fr' => "Le Chef d'Établissement", 'en' => "Principal"],
]])

</body>
</html>
