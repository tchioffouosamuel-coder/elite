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
                <img src="{{ $logoAbsolute }}">
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
