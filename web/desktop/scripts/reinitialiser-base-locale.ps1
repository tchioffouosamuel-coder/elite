<#
Repart d'une base locale neuve, apres le correctif du bug d'horodatage qui
empechait certaines colonnes de se mettre a jour (cf. commit "stop local
save time from poisoning conflict resolution"). Verifie d'abord qu'aucune
ecriture hors-ligne n'attend d'etre envoyee, pour ne rien perdre.

A executer avec la version 1.2.23 (ou plus recente) deja installee,
application FERMEE.
#>
$ErrorActionPreference = "Stop"

if (Get-Process | Where-Object { $_.ProcessName -like "*Elites*" }) {
    Write-Host "Ferme d'abord Elites School, puis relance ce script." -ForegroundColor Red
    exit 1
}

$candidatsData = @(
    "$env:APPDATA\Elites School",
    "$env:APPDATA\elites-school-desktop"
) | Where-Object { Test-Path $_ }

if (-not $candidatsData) {
    Write-Host "Dossier de donnees introuvable automatiquement." -ForegroundColor Red
    exit 1
}
$userDataDir = $candidatsData[0]
$dbPath = Join-Path $userDataDir "elites-school.sqlite"

if (-not (Test-Path $dbPath)) {
    Write-Host "Aucune base locale trouvee a $dbPath - rien a faire, relance simplement l'application." -ForegroundColor Yellow
    exit 0
}

$candidats = @(
    "$env:LOCALAPPDATA\Programs\Elites School",
    "$env:LOCALAPPDATA\Programs\elites-school-desktop",
    "$env:ProgramFiles\Elites School",
    "${env:ProgramFiles(x86)}\Elites School"
) | Where-Object { Test-Path $_ }
$installDir = if ($candidats) { $candidats[0] } else { Read-Host "Dossier d'installation introuvable. Colle le chemin complet" }
$apiDir = Join-Path $installDir "resources\api"
$phpExe = if ($env:ELITES_PHP_BINARY) { $env:ELITES_PHP_BINARY } else { Join-Path $installDir "resources\php\php.exe" }
$keyFile = Join-Path $userDataDir "app.key"

$env:APP_ENV = "production"; $env:APP_DEBUG = "true"; $env:DB_CONNECTION = "sqlite"; $env:DB_DATABASE = $dbPath
$env:CACHE_STORE = "file"; $env:SESSION_DRIVER = "file"; $env:QUEUE_CONNECTION = "sync"; $env:MAIL_MAILER = "log"; $env:SYNC_LOCAL_REPLICA = "true"
if (Test-Path $keyFile) { $env:APP_KEY = (Get-Content $keyFile -Raw).Trim() }

Write-Host "=== Verification : ecritures hors-ligne en attente d'envoi ===" -ForegroundColor Yellow
Push-Location $apiDir
& $phpExe artisan tinker --execute="echo \App\Models\SyncOutbox::whereNull('pushed_at')->count();"
Pop-Location
Write-Host ""

$reponse = Read-Host "Si ce nombre n'est PAS 0, NE CONTINUE PAS (des saisies locales seraient perdues) - tape OUI pour continuer si c'est bien 0"
if ($reponse -ne "OUI") {
    Write-Host "Annule." -ForegroundColor Yellow
    exit 0
}

Write-Host "`n=== Suppression de la base locale ===" -ForegroundColor Yellow
Remove-Item $dbPath -Force
Write-Host "Base supprimee : $dbPath" -ForegroundColor Green
Write-Host "`nRelance Elites School et reconnecte-toi - un premier clonage complet, propre, va se relancer automatiquement." -ForegroundColor Green
