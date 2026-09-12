<#
Ajoute une exclusion Windows Defender pour Elites School, pour que php.exe
ne soit plus supprime au prochain lancement/reinstallation. Necessite un
clic sur "Oui" a la demande d'autorisation Windows (droits administrateur).
#>
$ErrorActionPreference = "Stop"

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

Write-Host "Dossier a exclure : $installDir" -ForegroundColor Cyan

$scriptElevation = {
    param($chemin)
    Add-MpPreference -ExclusionPath $chemin
    Write-Host "Exclusion ajoutee avec succes pour : $chemin" -ForegroundColor Green
}

try {
    $estAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltinRole]::Administrator)
} catch {
    $estAdmin = $false
}

if ($estAdmin) {
    Add-MpPreference -ExclusionPath $installDir
    Write-Host "Exclusion ajoutee avec succes pour : $installDir" -ForegroundColor Green
} else {
    Write-Host "Ce script doit s'executer en administrateur pour ajouter l'exclusion." -ForegroundColor Yellow
    Write-Host "Une fenetre va s'ouvrir pour demander l'autorisation - clique sur Oui." -ForegroundColor Yellow
    Start-Process powershell -Verb RunAs -ArgumentList @(
        "-NoExit",
        "-Command",
        "Add-MpPreference -ExclusionPath `"$installDir`"; Write-Host 'Exclusion ajoutee avec succes.' -ForegroundColor Green"
    )
}

Write-Host ""
Write-Host "Etape suivante : reinstalle Elites School (relance le fichier .exe d'installation)." -ForegroundColor Cyan
Write-Host "php.exe ne devrait plus etre supprime cette fois, l'exclusion etant deja en place." -ForegroundColor Cyan
