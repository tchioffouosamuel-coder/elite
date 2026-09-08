
<#
.SYNOPSIS
    Teste l'API SMS de MTN (MADAPI) : récupère un token OAuth2 puis envoie un SMS sortant.
 
.DESCRIPTION
    Basé sur la spec Swagger fournie (host: api.mtn.com, basePath: /v3/sms/) :
      1. POST vers le tokenUrl OAuth2 (flow "application" / client_credentials) pour obtenir un access_token.
      2. POST vers /v3/sms/messages/sms/outbound avec le corps outboundSMSMessageRequest.
 
    Deux modes d'authentification pour l'étape 1 sont proposés via -AuthMode :
      - Basic : envoie client_id/client_secret encodés en Basic Auth (header Authorization: Basic ...)
                => c'est le mode standard OAuth2 "client_credentials" le plus répandu.
      - Body  : envoie client_id et client_secret en tant que champs du corps x-www-form-urlencoded.
    La spec Swagger ne précise pas laquelle des deux MTN attend réellement pour ce endpoint
    (elle ne définit que l'aspect abstrait "oauth2 / application flow") : si un des deux modes
    échoue avec 401/407, essayez l'autre avec -AuthMode Body (ou Basic).
 
.PARAMETER ClientId
    Identifiant client (consumer key) fourni par le portail développeur MTN.
 
.PARAMETER ClientSecret
    Secret client (consumer secret) fourni par le portail développeur MTN.
 
.PARAMETER ServiceCode
    Short code / service code approuvé pour l'envoi (ex: "11221" ou "131").
 
.PARAMETER SenderAddress
    (Optionnel) Nom alphanumérique affiché comme expéditeur (ex: "MTN"). Prend le pas sur ServiceCode si fourni.
 
.PARAMETER ReceiverAddress
    Un ou plusieurs numéros destinataires au format E.164 (ex: "237670000000").
 
.PARAMETER Message
    Contenu du SMS.
 
.PARAMETER RequestDeliveryReceipt
    Active la demande d'accusé de réception (nécessite d'avoir souscrit via /messages/sms/subscription).
 
.PARAMETER AuthMode
    "Basic" (défaut) ou "Body" — méthode d'envoi des identifiants au tokenUrl.
 
.EXAMPLE
    .\Test-MtnSms.ps1 -ClientId "xxx" -ClientSecret "yyy" -ServiceCode "131" `
        -ReceiverAddress "237670000000" -Message "Test ARTISCODE" -Verbose
 
.EXAMPLE
    .\Test-MtnSms.ps1 -ClientId "xxx" -ClientSecret "yyy" -SenderAddress "MTN" `
        -ReceiverAddress "237670000000","237680000000" -Message "Test groupé" -AuthMode Body
#>
 
[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string]$ClientId,
 
    [Parameter(Mandatory = $true)]
    [string]$ClientSecret,
 
    [string]$ServiceCode,
 
    [string]$SenderAddress,
 
    [Parameter(Mandatory = $true)]
    [string[]]$ReceiverAddress,
 
    [Parameter(Mandatory = $true)]
    [string]$Message,
 
    [switch]$RequestDeliveryReceipt,
 
    [ValidateSet('Basic', 'Body')]
    [string]$AuthMode = 'Basic',
 
    [string]$TokenUrl = "https://api.mtn.com/v1/oauth/access_token/accesstoken?grant_type=client_credentials",
 
    [string]$SmsBaseUrl = "https://api.mtn.com/v3/sms"
)
 
$ErrorActionPreference = 'Stop'
 
if (-not $SenderAddress -and -not $ServiceCode) {
    throw "Vous devez fournir -SenderAddress ou -ServiceCode (la spec exige serviceCode ; senderAddress est optionnel mais recommandé s'il est approuvé)."
}
 
function Get-HttpErrorBody {
    # Lit le corps de la réponse d'erreur, que ce soit sous Windows PowerShell 5.1
    # (System.Net.WebException / HttpWebResponse) ou sous PowerShell 7+ (Invoke-RestMethod
    # utilise System.Net.Http -> HttpResponseMessage, dont l'API de lecture est différente).
    param($ErrorRecord)
 
    if ($ErrorRecord.ErrorDetails -and $ErrorRecord.ErrorDetails.Message) {
        return $ErrorRecord.ErrorDetails.Message
    }
 
    $resp = $ErrorRecord.Exception.Response
    if (-not $resp) { return $null }
 
    try {
        if ($resp -is [System.Net.Http.HttpResponseMessage]) {
            return $resp.Content.ReadAsStringAsync().GetAwaiter().GetResult()
        }
        else {
            $stream = $resp.GetResponseStream()
            $reader = New-Object System.IO.StreamReader($stream)
            return $reader.ReadToEnd()
        }
    }
    catch {
        return $null
    }
}
 
function Get-MtnAccessToken {
    param(
        [string]$TokenUrl,
        [string]$ClientId,
        [string]$ClientSecret,
        [string]$AuthMode
    )
 
    Write-Verbose "Demande de token via $AuthMode auth -> $TokenUrl"
 
    if ($AuthMode -eq 'Basic') {
        $pair  = "$($ClientId):$($ClientSecret)"
        $bytes = [System.Text.Encoding]::UTF8.GetBytes($pair)
        $b64   = [Convert]::ToBase64String($bytes)
 
        $headers = @{
            Authorization = "Basic $b64"
        }
 
        $response = Invoke-RestMethod -Method Post -Uri $TokenUrl -Headers $headers `
            -ContentType 'application/x-www-form-urlencoded' -Body @{}
    }
    else {
        $body = @{
            grant_type    = 'client_credentials'
            client_id     = $ClientId
            client_secret = $ClientSecret
        }
 
        $response = Invoke-RestMethod -Method Post -Uri $TokenUrl `
            -ContentType 'application/x-www-form-urlencoded' -Body $body
    }
 
    # La forme exacte de la réponse (access_token / token) dépend de l'implémentation MTN ;
    # on gère les deux noms de champ les plus courants.
    $token = $response.access_token
    if (-not $token) { $token = $response.token }
 
    if (-not $token) {
        throw "Impossible de trouver le token dans la réponse : $($response | ConvertTo-Json -Depth 5)"
    }
 
    return $token
}
 
function Send-MtnSms {
    param(
        [string]$SmsBaseUrl,
        [string]$AccessToken,
        [string]$ServiceCode,
        [string]$SenderAddress,
        [string[]]$ReceiverAddress,
        [string]$Message,
        [bool]$RequestDeliveryReceipt
    )
 
    $uri = "$SmsBaseUrl/messages/sms/outbound"
 
    $bodyObj = [ordered]@{
        receiverAddress        = $ReceiverAddress
        message                 = $Message
        clientCorrelatorId     = [guid]::NewGuid().ToString()
        requestDeliveryReceipt = $RequestDeliveryReceipt
    }
 
    if ($ServiceCode)    { $bodyObj.serviceCode    = $ServiceCode }
    if ($SenderAddress)  { $bodyObj.senderAddress  = $SenderAddress }
 
    $json = $bodyObj | ConvertTo-Json -Depth 5
 
    Write-Verbose "POST $uri"
    Write-Verbose $json
 
    $headers = @{
        Authorization = "Bearer $AccessToken"
    }
 
    return Invoke-RestMethod -Method Post -Uri $uri -Headers $headers `
        -ContentType 'application/json; charset=utf-8' -Body $json
}
 
try {
    Write-Host "1/2 - Récupération du token OAuth2 ($AuthMode)..." -ForegroundColor Cyan
    $token = Get-MtnAccessToken -TokenUrl $TokenUrl -ClientId $ClientId -ClientSecret $ClientSecret -AuthMode $AuthMode
    Write-Host "   Token obtenu (longueur $($token.Length) car.)" -ForegroundColor Green
 
    Write-Host "2/2 - Envoi du SMS vers $($ReceiverAddress -join ', ')..." -ForegroundColor Cyan
    $result = Send-MtnSms -SmsBaseUrl $SmsBaseUrl -AccessToken $token -ServiceCode $ServiceCode `
        -SenderAddress $SenderAddress -ReceiverAddress $ReceiverAddress -Message $Message `
        -RequestDeliveryReceipt:$RequestDeliveryReceipt.IsPresent
 
    Write-Host "`nRéponse de l'API :" -ForegroundColor Green
    $result | ConvertTo-Json -Depth 5
}
catch {
    Write-Host "`nErreur pendant le test :" -ForegroundColor Red
    Write-Host $_.Exception.Message -ForegroundColor Red
 
    if ($_.Exception.Response -and $_.Exception.Response.StatusCode) {
        Write-Host "Code HTTP : $([int]$_.Exception.Response.StatusCode) ($($_.Exception.Response.StatusCode))" -ForegroundColor Yellow
    }
 
    $errBody = Get-HttpErrorBody -ErrorRecord $_
    if ($errBody) {
        Write-Host "Corps de la réponse d'erreur :" -ForegroundColor Yellow
        Write-Host $errBody
    }
    else {
        Write-Host "(Impossible de lire le corps de la réponse d'erreur)" -ForegroundColor Yellow
    }
}
 