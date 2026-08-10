@php
    // $roles: array of ['fr' => ..., 'en' => ...]
@endphp
<table class="signatures">
    <tr>
        @foreach ($roles as $role)
            <td>
                <div class="role-fr">{{ $role['fr'] }}</div>
                <div class="role-en">{{ $role['en'] }}</div>
            </td>
        @endforeach
    </tr>
</table>
