<#
Diagnostique pourquoi la table eleves reste vide en local malgre une
synchronisation "reussie" (aucune erreur affichee). A executer sur le poste
concerne, application fermee de preference.
#>
$ErrorActionPreference = "Continue"

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
$userDataDir = "$env:APPDATA\Elites School"
$dbPath = Join-Path $userDataDir "elites-school.sqlite"
$keyFile = Join-Path $userDataDir "app.key"
$logPath = Join-Path $apiDir "storage\logs\laravel.log"

Write-Host "Dossier API   : $apiDir" -ForegroundColor Cyan
Write-Host "PHP utilise   : $phpExe" -ForegroundColor Cyan
Write-Host "Base locale   : $dbPath (existe : $(Test-Path $dbPath))" -ForegroundColor Cyan

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

Push-Location $apiDir

Write-Host "`n=== 1. Comptage direct dans la base locale ===" -ForegroundColor Yellow
& $phpExe artisan tinker --execute="echo 'eleves: ' . \App\Models\Eleve::count() . PHP_EOL; echo 'classes: ' . \App\Models\Classe::count() . PHP_EOL; echo 'schools: ' . \App\Models\School::count() . PHP_EOL; foreach (\App\Models\School::all() as `$e) { echo `$e->id . ' - ' . `$e->name . PHP_EOL; }"

Write-Host "`n=== 2. Provisioning et curseurs de synchro ===" -ForegroundColor Yellow
& $phpExe artisan tinker --execute="foreach (\App\Models\DesktopProvisioningEcole::all() as `$e) { echo 'ecole ' . `$e->school_id . ' : curseur=' . (`$e->curseur_sync ?? 'null') . ' dernier_pull=' . (`$e->dernier_pull_le ?? 'null') . PHP_EOL; }"

Write-Host "`n=== 3. Dernieres lignes du journal mentionnant 'eleves' ou 'ignoree' ===" -ForegroundColor Yellow
if (Test-Path $logPath) {
    Select-String -Path $logPath -Pattern "eleves|ignoree|ignorée" -SimpleMatch:$false | Select-Object -Last 40 | ForEach-Object { $_.Line }
} else {
    Write-Host "Aucun journal trouve a $logPath"
}

Write-Host "`n=== 4. Test direct de l'entite eleves aupres du serveur distant ===" -ForegroundColor Yellow
& $phpExe artisan tinker --execute="`$p = \App\Models\DesktopProvisioning::first(); if (!`$p) { echo 'aucun provisioning' . PHP_EOL; exit; } `$ecole = `$p->ecoles()->first(); `$reponse = \Illuminate\Support\Facades\Http::withToken(`$p->token)->withHeaders(['X-School-Id' => `$ecole->school_id])->baseUrl(rtrim(`$p->serveur_url, '/').'/api/v1')->acceptJson()->get('sync', ['entites' => 'eleves']); echo 'statut: ' . `$reponse->status() . PHP_EOL; echo `$reponse->body();"

Pop-Location
