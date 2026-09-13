<#
Remet a zero le curseur de synchronisation de chaque ecole du poste, pour
forcer un reclonage complet au prochain "Synchroniser maintenant" (ou au
prochain lancement de l'app). Necessaire une seule fois pour recuperer des
colonnes ajoutees au registre de synchronisation apres coup (ex.
preinscriptions.annee_scolaire_id) sur des donnees deja synchronisees -
sans ca, seules les lignes modifiees depuis le dernier pull seraient
retelechargees, laissant les anciennes lignes avec la colonne toujours vide.

A executer application FERMEE.
#>
$ErrorActionPreference = "Stop"

$candidats = @(
    "$env:LOCALAPPDATA\Programs\Elites School",
    "$env:LOCALAPPDATA\Programs\elites-school-desktop",
    "$env:ProgramFiles\Elites School",
    "${env:ProgramFiles(x86)}\Elites School"
) | Where-Object { Test-Path $_ }

if (-not $candidats) {
    $installDir = Read-Host "Dossier d'installation introuvable automatiquement. Colle le chemin complet"
} else {
    $installDir = $candidats[0]
}

$apiDir = Join-Path $installDir "resources\api"
$phpExe = if ($env:ELITES_PHP_BINARY) { $env:ELITES_PHP_BINARY } else { Join-Path $installDir "resources\php\php.exe" }

$candidatsData = @(
    "$env:APPDATA\Elites School",
    "$env:APPDATA\elites-school-desktop"
) | Where-Object { Test-Path $_ }
$userDataDir = if ($candidatsData) { $candidatsData[0] } else { Read-Host "Dossier de donnees introuvable. Colle le chemin complet (contient elites-school.sqlite)" }

$dbPath = Join-Path $userDataDir "elites-school.sqlite"
$keyFile = Join-Path $userDataDir "app.key"

Write-Host "Base locale : $dbPath" -ForegroundColor Cyan

if (Get-Process | Where-Object { $_.ProcessName -like "*Elites*" }) {
    Write-Host "Ferme d'abord Elites School, puis relance ce script." -ForegroundColor Red
    exit 1
}

$env:APP_ENV = "production"
$env:APP_DEBUG = "true"
$env:DB_CONNECTION = "sqlite"
$env:DB_DATABASE = $dbPath
$env:CACHE_STORE = "file"
$env:SESSION_DRIVER = "file"
$env:QUEUE_CONNECTION = "sync"
$env:MAIL_MAILER = "log"
$env:SYNC_LOCAL_REPLICA = "true"
if (Test-Path $keyFile) { $env:APP_KEY = (Get-Content $keyFile -Raw).Trim() }

Push-Location $apiDir

Write-Host "`n=== Remise a zero des curseurs ===" -ForegroundColor Yellow
& $phpExe artisan tinker --execute="`$n = \App\Models\DesktopProvisioningEcole::query()->update(['curseur_sync' => null]); echo `$n . ' ecole(s) reinitialisee(s).' . PHP_EOL;"

Write-Host "`n=== Reclonage complet (peut prendre plusieurs minutes) ===" -ForegroundColor Yellow
& $phpExe artisan sync:pull

Write-Host "`n=== Verification : annee_scolaire_id sur les preinscriptions ===" -ForegroundColor Yellow
& $phpExe artisan tinker --execute="echo 'toujours null: ' . \App\Models\Preinscription::whereNull('annee_scolaire_id')->count() . ' / ' . \App\Models\Preinscription::count() . PHP_EOL;"

Pop-Location

Write-Host "`nTermine. Relance Elites School normalement." -ForegroundColor Green
