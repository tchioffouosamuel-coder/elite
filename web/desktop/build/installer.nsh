; Page d'installation optionnelle : chemin d'un PHP déjà installé sur le
; poste, écrit dans la variable d'environnement `ELITES_PHP_BINARY` (cf.
; `resolvePhpBinary()` dans `src/main.cjs`). Sans cela, l'utilisateur devait
; poser cette variable lui-même à la main (`setx`) après coup — un vrai
; obstacle pour un personnel non technique — s'il voulait un filet de
; secours en cas de PHP embarqué supprimé par un antivirus.
;
; Champ laissé vide par défaut : le comportement normal (PHP embarqué) ne
; change pas pour qui ne remplit rien.

; Explicite plutôt que suposé disponible depuis le script englobant : `!include`
; a déjà, deux fois de suite (v1.2.12 : `MUI_HEADER_TEXT` de MUI2.nsh absent,
; v1.2.13 : `${If}` de LogicLib.nsh absent), révélé que ce fichier est traité
; par `makensis` dans un contexte plus restreint que le script final assemblé —
; seul `nsDialogs.nsh` s'est avéré déjà chargé (nécessaire à `MUI_PAGE_DIRECTORY`
; juste avant). Tous les en-têtes ont leurs propres gardes anti-double-inclusion
; (`!ifndef`), les réinclure ici est donc sans risque même s'ils le sont déjà.
!include "LogicLib.nsh"
!include "WinMessages.nsh"
!include "nsDialogs.nsh"

; Tout ce bloc (page + ses fonctions) n'a de sens que pour l'INSTALLATION —
; ce même fichier est aussi inclus tel quel lors de la compilation du
; DÉSINSTALLEUR (`sharedHeader` est partagé entre les deux dans
; NsisTarget.js), qui ne déclenche jamais `customPageAfterChangeDir` : sans
; ce garde, `ElitePagePhpCreation` s'y retrouvait définie mais jamais
; appelée, et makensis traite un avertissement « fonction non référencée »
; comme une erreur fatale — un échec de build reproduit trois fois de
; suite avant que la vraie cause (pas un problème de macro non définie,
; cette fois) ne soit isolée en compilant le script réel en local.
!ifndef BUILD_UNINSTALLER
  Var EliteDialog
  Var EliteLabelPhp
  Var EliteTexteCheminPhp
  Var EliteBoutonParcourir
  Var EliteCheminPhpChoisi

  !macro customPageAfterChangeDir
    Page custom ElitePagePhpCreation ElitePagePhpValidation
  !macroend

  Function ElitePagePhpCreation
    nsDialogs::Create 1018
    Pop $EliteDialog

    ${If} $EliteDialog == error
      Abort
    ${EndIf}

    ${NSD_CreateLabel} 0 0 100% 60u "Elites School installe son propre PHP. Si un antivirus venait à le supprimer, l'application ne pourrait plus démarrer.$\r$\n$\r$\nSi ce poste a déjà un PHP installé (ex. Laragon, XAMPP, WampServer) et que vous savez où se trouve son fichier php.exe, indiquez-le ci-dessous : Elites School l'utilisera automatiquement en secours.$\r$\n$\r$\nLaissez ce champ vide si vous ne savez pas de quoi il s'agit — tout fonctionnera normalement."
    Pop $EliteLabelPhp

    ${NSD_CreateText} 0 65u 76% 12u ""
    Pop $EliteTexteCheminPhp

    ${NSD_CreateButton} 79% 65u 21% 12u "Parcourir..."
    Pop $EliteBoutonParcourir
    ${NSD_OnClick} $EliteBoutonParcourir ElitePhpParcourirClic

    nsDialogs::Show
  FunctionEnd

  Function ElitePhpParcourirClic
    nsDialogs::SelectFileDialog open "" "PHP (php.exe)|php.exe|Tous les fichiers|*.*"
    Pop $0
    ${If} $0 != error
      ${NSD_SetText} $EliteTexteCheminPhp "$0"
    ${EndIf}
  FunctionEnd

  Function ElitePagePhpValidation
    ${NSD_GetText} $EliteTexteCheminPhp $EliteCheminPhpChoisi
  FunctionEnd
!endif

!macro customInstall
  ; Écrit la variable d'environnement UTILISATEUR (l'installation elle-même
  ; peut être per-user ou per-machine, cf. package.json/allowElevation) une
  ; fois les fichiers copiés — validation minimale (le fichier existe et se
  ; nomme bien php.exe) : une valeur invalide n'a pas à faire échouer toute
  ; l'installation, elle ferait simplement retomber `resolvePhpBinary()` sur
  ; son comportement normal (PHP embarqué, ou système via le PATH).
  ${If} $EliteCheminPhpChoisi != ""
  ${AndIf} ${FileExists} "$EliteCheminPhpChoisi"
    WriteRegExpandStr HKCU "Environment" "ELITES_PHP_BINARY" "$EliteCheminPhpChoisi"
    SendMessage ${HWND_BROADCAST} ${WM_SETTINGCHANGE} 0 "STR:Environment" /TIMEOUT=5000
  ${EndIf}

  ; Décompresse ICI, pendant l'installation, plutôt qu'au premier lancement
  ; de l'application (cf. `assurerVendorExtrait()` dans `main.cjs`) : une
  ; installation « pour tous les utilisateurs » place `$INSTDIR` sous
  ; `Program Files`, protégé en écriture pour tout process non élevé —
  ; exactement ce qu'est l'application à l'usage normal. Le tenter au
  ; lancement échouait alors avec `PermissionDenied` sur chaque poste installé
  ; ainsi (observé en conditions réelles), quel que soit l'antivirus.
  ; L'installeur, lui, a toujours les droits nécessaires sur le dossier qu'il
  ; vient de créer, qu'il tourne élevé (per-machine) ou non (per-user, où
  ; `$INSTDIR` appartient déjà à l'utilisateur courant).
  IfFileExists "$INSTDIR\resources\api\vendor.zip" 0 +5
    ExecWait 'powershell -NoProfile -Command "Expand-Archive -Path \"$INSTDIR\resources\api\vendor.zip\" -DestinationPath \"$INSTDIR\resources\api\vendor\" -Force"' $0
    ${If} $0 == 0
      Delete "$INSTDIR\resources\api\vendor.zip"
    ${EndIf}
!macroend
