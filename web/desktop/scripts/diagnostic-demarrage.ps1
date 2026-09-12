<#
Diagnostic du blocage "Le serveur local n'a pas demarre a temps" au premier
lancement d'Elites School Desktop. A executer sur le poste concerne, dans un
PowerShell normal (pas besoin d'administrateur), puis coller toute la sortie.
#>
$ErrorActionPreference = "Continue"

Write-Host "== 1. Recherche du dossier d'installation ==" -ForegroundColor Cyan
$candidats = @(
    "$env:LOCALAPPDATA\Programs\Elites School",
    "$env:LOCALAPPDATA\Programs\elites-school-desktop",
    "$env:ProgramFiles\Elites School",
    "${env:ProgramFiles(x86)}\Elites School"
) | Where-Object { Test-Path $_ }

if (-not $candidats) {
    Write-Host "Introuvable aux emplacements habituels. Indique le chemin d'installation choisi lors du setup." -ForegroundColor Yellow
    $installDir = Read-Host "Chemin d'installation (dossier contenant Elites School.exe)"
} else {
    $installDir = $candidats[0]
}
Write-Host "Dossier retenu : $installDir"

$phpExe = Join-Path $installDir "resources\php\php.exe"
$apiDir = Join-Path $installDir "resources\api"

Write-Host "`n== 2. Presence des fichiers embarques ==" -ForegroundColor Cyan
Write-Host "php.exe existe : $(Test-Path $phpExe)"
Write-Host "dossier api existe : $(Test-Path $apiDir)"
Write-Host "artisan existe : $(Test-Path (Join-Path $apiDir 'artisan'))"

Write-Host "`n== 3. php.exe s'execute-t-il ? ==" -ForegroundColor Cyan
if (Test-Path $phpExe) {
    & $phpExe -v
} else {
    Write-Host "php.exe absent : probablement supprime ou mis en quarantaine par l'antivirus." -ForegroundColor Red
}

Write-Host "`n== 4. Port 8973 deja utilise par autre chose ? ==" -ForegroundColor Cyan
try {
    Get-NetTCPConnection -LocalPort 8973 -ErrorAction Stop | Format-Table -AutoSize
} catch {
    Write-Host "Rien n'ecoute actuellement sur le port 8973 (normal si l'app est fermee)."
}

Write-Host "`n== 5. Migration + demarrage manuel du serveur (comme le fait l'app) ==" -ForegroundColor Cyan
if ((Test-Path $phpExe) -and (Test-Path $apiDir)) {
    $userDataDir = "$env:APPDATA\Elites School"
    $dbPath = Join-Path $userDataDir "elites-school.sqlite"
    Write-Host "Base locale : $dbPath (existe : $(Test-Path $dbPath))"

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
    if (-not $env:APP_KEY) {
        $keyFile = Join-Path $userDataDir "app.key"
        if (Test-Path $keyFile) { $env:APP_KEY = (Get-Content $keyFile -Raw).Trim() }
    }

    Push-Location $apiDir
    Write-Host "--- artisan migrate --force ---"
    & $phpExe artisan migrate --force
    Write-Host "Code de sortie migrate : $LASTEXITCODE"

    Write-Host "--- demarrage du serveur (Ctrl+C pour arreter apres quelques secondes) ---"
    & $phpExe -S 127.0.0.1:8973 -t public
    Pop-Location
}
