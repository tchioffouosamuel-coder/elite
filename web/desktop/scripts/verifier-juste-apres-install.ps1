<#
A lancer IMMEDIATEMENT apres avoir termine l'installation d'Elites School,
AVANT d'ouvrir l'application (avant meme de cliquer sur le raccourci).
Dis juste si "vendor existe" est True ou False.
#>
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

$vendorDir = Join-Path $installDir "resources\api\vendor"
$phpExe = Join-Path $installDir "resources\php\php.exe"
$autoload = Join-Path $vendorDir "autoload.php"

Write-Host ""
Write-Host "Dossier install       : $installDir"
Write-Host "vendor existe         : $(Test-Path $vendorDir)"
if (Test-Path $vendorDir) {
    Write-Host "nombre de fichiers    : $((Get-ChildItem $vendorDir -Recurse -File -ErrorAction SilentlyContinue).Count)"
}
Write-Host "vendor/autoload.php   : $(Test-Path $autoload)"
Write-Host "php.exe embarque      : $(Test-Path $phpExe)"
Write-Host ""
Write-Host "Espace disque libre sur C: : $([math]::Round((Get-PSDrive C).Free / 1GB, 1)) Go"
