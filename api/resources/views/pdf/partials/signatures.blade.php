@php
    // $roles: array of ['fr' => ..., 'en' => ...]
    // $school (optionnel) : cachet + signature scannés, composés en une seule
    // image (la signature traverse le cachet) à afficher sur la case du chef
    // d'établissement, plutôt que de laisser une ligne vide dans un document
    // qui les a déjà en pièce jointe numérique.
    $visa = isset($school) ? (new \App\Services\VisaComposeService)->chemin($school) : null;
@endphp
<table class="signatures">
    <tr>
        @foreach ($roles as $role)
            <td>
                @if (str_contains($role['fr'], "Chef d'Établissement") && $visa)
                    <div><img src="{{ $visa }}" style="height:40px;"></div>
                @endif
                <div class="role-fr">{{ $role['fr'] }}</div>
                <div class="role-en">{{ $role['en'] }}</div>
            </td>
        @endforeach
    </tr>
</table>
