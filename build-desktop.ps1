$ErrorActionPreference = "Stop"

Push-Location "$PSScriptRoot\web\desktop"
try {
    Write-Host "Installation des dependances desktop..." -ForegroundColor Cyan
    npm install --no-audit --no-fund
    if ($LASTEXITCODE -ne 0) {
        throw "npm install a echoue (code $LASTEXITCODE). Verifiez la connexion DNS/Internet puis relancez .\build-desktop.ps1."
    }

    if (-not (Test-Path "node_modules\electron-builder\cli.js")) {
        throw "electron-builder est absent apres l'installation. Relancez npm install avec une connexion Internet active."
    }

    if (-not (Test-Path "node_modules\electron\dist\electron.exe")) {
        throw "Le binaire Electron est absent. Le telechargement depuis GitHub a probablement ete bloque. Verifiez DNS, proxy ou pare-feu puis relancez."
    }

    Write-Host "Construction du frontend et de l'installateur Windows..." -ForegroundColor Cyan
    npm run build
    if ($LASTEXITCODE -ne 0) {
        throw "La generation de l'installateur a echoue (code $LASTEXITCODE)."
    }

    Write-Host "Installateur genere dans web\desktop\dist\" -ForegroundColor Green
}
finally {
    Pop-Location
}