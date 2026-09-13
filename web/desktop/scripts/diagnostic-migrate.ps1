<#
Reproduit exactement la commande que l'application lance au demarrage
(artisan migrate --force) avec le PHP indique par ELITES_PHP_BINARY, pour
voir le vrai message d'erreur que la boite de dialogue ne montre pas.
#>
$ErrorActionPreference = "Continue"

Write-Host "ELITES_PHP_BINARY actuel : $env:ELITES_PHP_BINARY" -ForegroundColor Cyan

$candidats = @(
    "$env:LOCALAPPDATA\Programs\Elites School",
    "$env:LOCALAPPDATA\Programs\elites-school-desktop",
    "$env:ProgramFiles\Elites School",
    "${env:ProgramFiles(x86)}\Elites School"
) | Where-Object { Test-Path $_ }

if (-not $candidats) {
    $installDir = Read-Host "Dossier d'installation introuvable automatiquement. Colle le chemin complet (dossier contenant Elites School.exe)"
} else {
    $installDir = $candidats[0]
}

$apiDir = Join-Path $installDir "resources\api"
Write-Host "Dossier API : $apiDir (existe : $(Test-Path $apiDir))" -ForegroundColor Cyan

$phpBinaire = if ($env:ELITES_PHP_BINARY) { $env:ELITES_PHP_BINARY } else { "C:\php\php.exe" }
Write-Host "PHP utilise : $phpBinaire (existe : $(Test-Path $phpBinaire))" -ForegroundColor Cyan

Write-Host "`n--- Version de ce PHP ---" -ForegroundColor Cyan
& $phpBinaire -v

Write-Host "`n--- Extensions chargees ---" -ForegroundColor Cyan
& $phpBinaire -m

$userDataDir = "$env:APPDATA\Elites School"
$dbPath = Join-Path $userDataDir "elites-school.sqlite"
$keyFile = Join-Path $userDataDir "app.key"

$env:APP_ENV = "production"
$env:APP_DEBUG = "true"
$env:APP_URL = "http://127.0.0.1:8973"
$env:DB_CONNECTION = "sqlite"
$env:DB_DATABASE = $dbPath
$env:CACHE_STORE = "file"
$env:SESSION_DRIVER = "file"
$env:QUEUE_CONNECTION = "sync"
$env:MAIL_MAILER = "log"
$env:SYNC_LOCAL_REPLICA = "true"
if (Test-Path $keyFile) { $env:APP_KEY = (Get-Content $keyFile -Raw).Trim() }

Write-Host "`n--- artisan migrate --force (la vraie erreur doit apparaitre ci-dessous) ---" -ForegroundColor Cyan
Push-Location $apiDir
& $phpBinaire artisan migrate --force
Write-Host "`nCode de sortie : $LASTEXITCODE" -ForegroundColor Cyan
Pop-Location
