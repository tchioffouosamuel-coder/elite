; Page d'installation optionnelle : chemin d'un PHP déjà installé sur le
; poste, écrit dans la variable d'environnement `ELITES_PHP_BINARY` (cf.
; `resolvePhpBinary()` dans `src/main.cjs`). Sans cela, l'utilisateur devait
; poser cette variable lui-même à la main (`setx`) après coup — un vrai
; obstacle pour un personnel non technique — s'il voulait un filet de
; secours en cas de PHP embarqué supprimé par un antivirus.
;
; Champ laissé vide par défaut : le comportement normal (PHP embarqué) ne
; change pas pour qui ne remplit rien.

!include "WinMessages.nsh"

Var EliteDialog
Var EliteLabelPhp
Var EliteTexteCheminPhp
Var EliteBoutonParcourir
Var EliteCheminPhpChoisi

!macro customPageAfterChangeDir
  Page custom ElitePagePhpCreation ElitePagePhpValidation
!macroend

Function ElitePagePhpCreation
  !insertmacro MUI_HEADER_TEXT "PHP existant (facultatif)" "Utiliser un PHP déjà installé sur ce poste plutôt que celui fourni avec Elites School."

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

; Écrit la variable d'environnement UTILISATEUR (l'installation elle-même
; est `perMachine: false`, cf. package.json) une fois les fichiers copiés —
; validation minimale (le fichier existe et se nomme bien php.exe) : une
; valeur invalide n'a pas à faire échouer toute l'installation, elle ferait
; simplement retomber `resolvePhpBinary()` sur son comportement normal
; (PHP embarqué, ou système via le PATH).
!macro customInstall
  ${If} $EliteCheminPhpChoisi != ""
  ${AndIf} ${FileExists} "$EliteCheminPhpChoisi"
    WriteRegExpandStr HKCU "Environment" "ELITES_PHP_BINARY" "$EliteCheminPhpChoisi"
    SendMessage ${HWND_BROADCAST} ${WM_SETTINGCHANGE} 0 "STR:Environment" /TIMEOUT=5000
  ${EndIf}
!macroend
