@php
    $logoAbsolute = null;
    if (!empty($school->logo_path)) {
        $candidate = storage_path('app/public/' . ltrim($school->logo_path, '/'));
        if (is_file($candidate)) { $logoAbsolute = $candidate; }
    }
    $monogram = mb_strtoupper(mb_substr($school->name ?? '?', 0, 1));
@endphp
<table class="header-table">
    <tr>
        <td class="fr">
            <span class="school-name">{{ $school->name }}</span><br>
            @if(!empty($school->header_fr))
                {!! \App\Support\Pdf\EnTeteHtml::render($school->header_fr) !!}
            @else
                @if(!empty($school->address)) {{ $school->address }}<br> @endif
                @if(!empty($school->phone)) Tél : {{ $school->phone }}<br> @endif
                @if(!empty($school->email)) Email : {{ $school->email }} @endif
            @endif
        </td>
        <td class="logo-cell">
            @if($logoAbsolute)
                {{--
                    mPDF ne fiabilise pas toujours un sélecteur composé à
                    plusieurs niveaux (`table.header-table .logo-cell img`) :
                    sans contrainte portée directement par l'élément, un logo
                    uploadé à sa résolution native (souvent bien au-delà de
                    100px) s'affiche à sa taille réelle et écrase toute la
                    page. Le style inline garantit la largeur quel que soit
                    le moteur de rendu (mPDF ici, dompdf pour palmarès).
                --}}
                <img src="{{ $logoAbsolute }}" style="width:100px;height:auto;max-height:100px;">
            @else
                <div class="monogram">{{ $monogram }}</div>
            @endif
        </td>
        <td class="en">
            <span class="school-name">{{ $school->name }}</span><br>
            @if(!empty($school->header_en))
                {!! \App\Support\Pdf\EnTeteHtml::render($school->header_en) !!}
            @else
                @if(!empty($school->address)) {{ $school->address }}<br> @endif
                @if(!empty($school->phone)) Phone: {{ $school->phone }}<br> @endif
                @if(!empty($school->email)) Email: {{ $school->email }} @endif
            @endif
        </td>
    </tr>
</table>
<hr>
