@component('mail::message')
# {{ $titre }}

{{ $message }}

Merci,<br>
{{ config('app.name') }}
@endcomponent
