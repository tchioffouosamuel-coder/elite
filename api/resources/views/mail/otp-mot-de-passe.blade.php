@component('mail::message')
# Réinitialisation de votre mot de passe

Bonjour {{ $nom }},

Voici votre code de vérification pour réinitialiser votre mot de passe :

@component('mail::panel')
<div style="text-align:center; font-size: 28px; font-weight: 700; letter-spacing: 6px;">
{{ $otp }}
</div>
@endcomponent

Ce code est valable {{ $validiteMinutes }} minutes. Si vous n'êtes pas à l'origine de cette demande, ignorez simplement ce message : votre mot de passe restera inchangé.

Merci,<br>
{{ config('app.name') }}
@endcomponent
