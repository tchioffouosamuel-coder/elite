@php
    // Chemin disque plutôt qu'URL : dompdf se requêterait lui-même pour aller
    // chercher l'image pendant la génération.
    $filigrane = null;
    if (!empty($school->logo_path)) {
        $candidat = storage_path('app/public/' . ltrim($school->logo_path, '/'));
        if (is_file($candidat)) { $filigrane = $candidat; }
    }
@endphp
@if($filigrane)
    {{--
        `position: fixed` est répété sur chaque page par dompdf, et sort du
        flux : le filigrane se superpose au contenu sans décaler la mise en
        page. `z-index` négatif le maintient derrière le texte.
    --}}
    <div class="filigrane"><img src="{{ $filigrane }}" alt=""></div>
@endif
