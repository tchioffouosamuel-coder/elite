<#
.SYNOPSIS
    Synchronise le monorepo Laragon (elites_school) vers deux dépôts GitLab
    séparés, un pour l'API Laravel (api/) et un pour le frontend React (web/).

.DESCRIPTION
    Pour chaque sous-projet (api et/ou web), le script :
      1. Se place dans le monorepo source.
      2. Supprime l'éventuelle branche locale split-<projet> laissée par un
         run précédent, pour repartir sur une base propre.
      3. Extrait l'historique du sous-dossier via `git subtree split`.
      4. Se déplace dans le dépôt cible correspondant.
      5. Récupère cet historique via `git pull`.
      6. Pousse vers `origin main`. En cas d'échec (le plus souvent une
         branche `main` protégée sur GitLab), le script s'arrête avec un
         message explicite plutôt que de forcer le push de sa propre
         initiative — le --force n'est tenté que si -Force est fourni.
      7. Nettoie la branche split-<projet> du monorepo.

    Le script s'arrête immédiatement (sans continuer sur l'étape ou le
    sous-projet suivant) dès qu'une commande échoue.

.PARAMETER Only
    Restreint la synchronisation à un seul sous-projet : 'api' ou 'web'.
    Sans ce paramètre, les deux sont synchronisés l'un après l'autre.

.PARAMETER Force
    Autorise un `git push --force` si le push normal échoue. Désactivé par
    défaut : sans ce paramètre, un push refusé (branche protégée) arrête le
    script avec des instructions, sans jamais forcer automatiquement.

.EXAMPLE
    .\sync-elites.ps1
    Synchronise api et web.

.EXAMPLE
    .\sync-elites.ps1 -Only web
    Ne synchronise que le frontend.

.EXAMPLE
    .\sync-elites.ps1 -Only api -Force
    Synchronise l'API et force le push si le push normal est rejeté.
#>

[CmdletBinding()]
param(
    [ValidateSet('api', 'web')]
    [string]$Only,

    [switch]$Force
)

$ErrorActionPreference = 'Stop'

# --- Configuration ---------------------------------------------------------

# Dépôt monorepo source : celui d'où partent les `git subtree split`.
$RepoSource = 'C:\laragon\www\elites_school'

# Un dépôt cible par sous-projet : chemin local (déjà cloné au préalable) et
# remote GitLab correspondant, uniquement pour l'affichage des messages.
$Cibles = @{
    api = @{
        Chemin = 'C:\laragon\www\elite-api'
        Remote = 'https://gitlab.com/artiscode/elite/api.git'
    }
    web = @{
        Chemin = 'C:\laragon\www\elite-web'
        Remote = 'https://gitlab.com/artiscode/elite/web.git'
    }
}

# --- Fonctions d'affichage ---------------------------------------------------

function Write-Etape {
    param([Parameter(Mandatory)][string]$Message)
    Write-Host "`n==> $Message" -ForegroundColor Cyan
}

function Write-Succes {
    param([Parameter(Mandatory)][string]$Message)
    Write-Host "[OK] $Message" -ForegroundColor Green
}

function Write-Avertissement {
    param([Parameter(Mandatory)][string]$Message)
    Write-Host "[ATTENTION] $Message" -ForegroundColor Yellow
}

function Write-Erreur {
    param([Parameter(Mandatory)][string]$Message)
    Write-Host "[ERREUR] $Message" -ForegroundColor Red
}

# --- Fonctions utilitaires ---------------------------------------------------

<#
.SYNOPSIS
    Exécute une commande git et arrête le script (exception) si elle échoue.
    Centralise la vérification de $LASTEXITCODE, que PowerShell ne traduit
    jamais en exception automatiquement pour un exécutable externe comme git.
#>
function Invoke-GitOuEchec {
    param(
        [Parameter(Mandatory)][string[]]$Arguments,
        [Parameter(Mandatory)][string]$MessageErreur
    )

    & git @Arguments
    if ($LASTEXITCODE -ne 0) {
        Write-Erreur $MessageErreur
        throw $MessageErreur
    }
}

<#
.SYNOPSIS
    Indique si une branche locale existe dans le dépôt courant.
#>
function Test-BrancheExiste {
    param([Parameter(Mandatory)][string]$Nom)

    git rev-parse --verify --quiet $Nom *> $null
    return ($LASTEXITCODE -eq 0)
}

<#
.SYNOPSIS
    Synchronise un sous-projet (api ou web) du monorepo vers son dépôt cible.
#>
function Sync-SousProjet {
    param(
        [Parameter(Mandatory)][string]$Nom,
        [Parameter(Mandatory)][string]$CheminCible,
        [Parameter(Mandatory)][string]$RemoteCible
    )

    $branche = "split-$Nom"

    Write-Etape "Synchronisation de '$Nom' vers $RemoteCible"

    # 1. Se placer dans le monorepo source.
    if (-not (Test-Path $RepoSource)) {
        Write-Erreur "Dépôt source introuvable : $RepoSource"
        throw "Dépôt source introuvable."
    }
    Set-Location $RepoSource

    # 2. Supprimer l'éventuelle branche split-<projet> d'un run précédent,
    #    pour que `git subtree split -b` ne râle pas sur une branche existante.
    if (Test-BrancheExiste -Nom $branche) {
        Write-Avertissement "La branche locale '$branche' existe déjà (run précédent) : suppression avant de recommencer."
        Invoke-GitOuEchec -Arguments @('branch', '-D', $branche) `
            -MessageErreur "Impossible de supprimer la branche existante '$branche'."
    }

    # 3. Extraire l'historique du sous-dossier dans une branche dédiée.
    Write-Host "Extraction de l'historique de '$Nom/' (git subtree split)…"
    Invoke-GitOuEchec -Arguments @('subtree', 'split', "--prefix=$Nom", '-b', $branche) `
        -MessageErreur "Échec de 'git subtree split --prefix=$Nom'."
    Write-Succes "Branche '$branche' créée à partir de l'historique de '$Nom/'."

    # 4. Se déplacer dans le dépôt cible (doit déjà être cloné localement).
    if (-not (Test-Path $CheminCible)) {
        Write-Erreur "Dépôt cible introuvable : $CheminCible (clonez-le avant de lancer ce script)."
        throw "Dépôt cible introuvable."
    }
    Set-Location $CheminCible

    # 5. Récupérer la branche splittée depuis le monorepo.
    Write-Host "Récupération de la branche '$branche' dans '$CheminCible'…"
    Invoke-GitOuEchec -Arguments @('pull', '..\elites_school', $branche) `
        -MessageErreur "Échec de 'git pull ..\elites_school $branche' dans '$CheminCible'."
    Write-Succes "Dépôt '$Nom' à jour avec les derniers changements de '$Nom/'."

    # 6. Pousser vers origin/main. Le --force n'intervient que si -Force a
    #    été explicitement fourni au script, jamais de sa propre initiative.
    Write-Host "Envoi vers $RemoteCible…"
    & git push origin main
    if ($LASTEXITCODE -ne 0) {
        if ($Force) {
            Write-Avertissement "Le push normal a échoué. Nouvelle tentative en forçant (--force), comme demandé via -Force…"
            & git push --force origin main
            if ($LASTEXITCODE -ne 0) {
                Write-Erreur "Le push forcé a également échoué pour '$Nom'."
                throw "Push forcé échoué pour '$Nom'."
            }
            Write-Succes "Push forcé réussi pour '$Nom'."
        }
        else {
            Write-Erreur "Le push vers '$RemoteCible' a échoué."
            Write-Avertissement "Cause probable : la branche 'main' est protégée sur GitLab."
            Write-Avertissement "  -> Déprotégez-la temporairement : Settings > Repository > Protected branches, puis relancez ce script."
            Write-Avertissement "  -> Ou relancez avec le paramètre -Force pour forcer le push (à utiliser avec prudence)."
            throw "Push refusé pour '$Nom' (probablement une branche protégée)."
        }
    }
    else {
        Write-Succes "Push vers '$RemoteCible' réussi."
    }

    # 7. Nettoyer la branche temporaire dans le monorepo, pour ne pas
    #    l'accumuler à chaque exécution du script.
    Set-Location $RepoSource
    Invoke-GitOuEchec -Arguments @('branch', '-D', $branche) `
        -MessageErreur "Impossible de supprimer la branche temporaire '$branche' — à nettoyer manuellement."
    Write-Succes "Branche temporaire '$branche' supprimée du monorepo."
}

# --- Point d'entrée ----------------------------------------------------------

$projets = if ($Only) { @($Only) } else { @('api', 'web') }

try {
    foreach ($projet in $projets) {
        $cible = $Cibles[$projet]
        Sync-SousProjet -Nom $projet -CheminCible $cible.Chemin -RemoteCible $cible.Remote
    }

    Write-Host ''
    Write-Succes 'Synchronisation terminée avec succès.'
}
catch {
    Write-Host ''
    Write-Erreur "Script interrompu : $($_.Exception.Message)"
    Set-Location $RepoSource -ErrorAction SilentlyContinue
    exit 1
}
