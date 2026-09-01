#Requires -Version 5.1
<#
Pousse le code courant sur GitHub, incrémente le patch de version du
desktop, puis build + publie l'installeur en release GitHub — les postes
déjà installés le détecteront via configurerAutoUpdate() dans main.cjs.
#>
$ErrorActionPreference = "Stop"

$desktopDir = Split-Path -Parent $PSScriptRoot
$repoRoot = Split-Path -Parent (Split-Path -Parent $desktopDir)
Set-Location $repoRoot

Write-Host "== 1/4 Poussee du code ==" -ForegroundColor Cyan
git add -A
$statusAvant = git status --porcelain
if ($statusAvant) {
    $message = Read-Host "Message de commit"
    if ([string]::IsNullOrWhiteSpace($message)) { $message = "Update" }
    git commit -m $message
} else {
    Write-Host "Rien a committer."
}
git push origin HEAD
try {
    git push gitlab HEAD 2>$null
} catch {
    Write-Host "(push gitlab ignore : $($_.Exception.Message))" -ForegroundColor DarkYellow
}

Write-Host "== 2/4 Incrementation de version ==" -ForegroundColor Cyan
$pkgPath = Join-Path $desktopDir "package.json"
$content = Get-Content $pkgPath -Raw
if ($content -notmatch '"version":\s*"(\d+)\.(\d+)\.(\d+)"') {
    throw "Impossible de trouver le champ version dans $pkgPath"
}
$nouveauPatch = [int]$Matches[3] + 1
$nouvelleVersion = "$($Matches[1]).$($Matches[2]).$nouveauPatch"
$content = $content -replace '"version":\s*"\d+\.\d+\.\d+"', "`"version`": `"$nouvelleVersion`""
Set-Content -Path $pkgPath -Value $content -NoNewline
Write-Host "Version desktop : $nouvelleVersion"

Set-Location $repoRoot
git add $pkgPath
git commit -m "chore(desktop): bump version to $nouvelleVersion"
git push origin HEAD
try {
    git push gitlab HEAD 2>$null
} catch {
    Write-Host "(push gitlab ignore : $($_.Exception.Message))" -ForegroundColor DarkYellow
}

Write-Host "== 3/4 Jeton GitHub ==" -ForegroundColor Cyan
if (-not $env:GH_TOKEN) {
    $secure = Read-Host "GH_TOKEN (jeton GitHub avec droits 'repo' sur tchioffouosamuel-coder/elite)" -AsSecureString
    $bstr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
    $env:GH_TOKEN = [Runtime.InteropServices.Marshal]::PtrToStringAuto($bstr)
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($bstr)
}

Write-Host "== 4/4 Build + publication de la release ==" -ForegroundColor Cyan
Set-Location $desktopDir
npm run release
if ($LASTEXITCODE -ne 0) {
    throw "npm run release a echoue (code $LASTEXITCODE)"
}

Write-Host "Termine. Les postes installes recevront la mise a jour $nouvelleVersion au prochain controle." -ForegroundColor Green
