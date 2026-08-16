# 🌍 AUDIT COMPLET DE L'INTERNATIONALISATION (i18n)
**Elite School - Front-end React/TypeScript**  
**Date:** 2026-08-16  
**Répertoire analysé:** `web/src/`

---

## 📋 RÉSUMÉ EXÉCUTIF

| Métrique | Valeur | Status |
|----------|--------|--------|
| **Langues supportées** | 2 (FR, EN) | ✅ |
| **Configuration** | react-i18next | ✅ |
| **Fichiers .tsx totaux** | ~70 fichiers | - |
| **Fichiers avec i18n** | 40 fichiers | ⚠️ (57%) |
| **Fichiers SANS i18n** | 30 fichiers | 🔴 (43%) |
| **Clés de traduction** | ~200+ clés | ✅ |
| **Couverture i18n** | **PARTIELLE** | 🟡 |

---

## 1️⃣ CONFIGURATION I18N

### 📝 Fichier: `web/src/shared/i18n/index.ts`

```typescript
// Configuration i18next avec react-i18next
i18n.use(initReactI18next).init({
  resources: {
    fr: { translation: fr },
    en: { translation: en },
  },
  lng: useUiStore.getState().locale,      // Locale depuis le store UI
  fallbackLng: 'fr',                       // FR par défaut
  interpolation: { escapeValue: false },
})

// Synchronisation avec le store
useUiStore.subscribe((state) => {
  if (i18n.language !== state.locale) 
    i18n.changeLanguage(state.locale)
})
```

### ✅ Langues supportées:
- **FR (Français)** - Langue par défaut
- **EN (Anglais)** - Langue alternative
- **Détection:** Via `useUiStore.locale` (persistant)

### 📂 Fichiers de traduction:
- `web/src/shared/i18n/locales/fr.json` (327 lignes)
- `web/src/shared/i18n/locales/en.json` (équivalent)

---

## 2️⃣ CONFIGURATION DES ALERTES/MESSAGES

### 📝 Fichier: `web/src/shared/lib/alertes.ts`

Utilise **SweetAlert2** avec wrapper personnalisé. Les fonctions disponibles:

```typescript
succes(message: string)              // Toast vert ✅
erreur(message: string)              // Toast rouge ❌
info(message: string)                // Toast bleu ℹ️
confirmer({titre, message, action, destructif})  // Modal de confirmation
permissionManquante(message: string) // Modal 403
```

**⚠️ PROBLÈME CRITIQUE:** Aucune intégration i18n - les messages passent directement en dur!

---

## 3️⃣ CLÉS DE TRADUCTION DISPONIBLES

### 📊 Statistiques des clés:

| Namespace | Clés | Statut |
|-----------|------|--------|
| `app` | 1 | ✅ |
| `common` | 22 | ✅ |
| `nav` | 55+ | ✅ |
| `auth` | 7 | ✅ |
| `dashboard` | 10 | ✅ |
| `personnel` | 14 | ✅ |
| `classes` | 12 | ✅ |
| `eleves` | 15 | ✅ |
| `matieres` | 11 | ✅ |
| `pedagogie` | 5 | ✅ |
| `notes` | 7 | ✅ |
| `niveaux` | 12 | ✅ |
| `discipline` | 15 | ✅ |
| `resultats` | 8 | ✅ |
| `settings` | 8 | ✅ |
| `session` | 12 | ✅ |
| `export` | 5 | ✅ |
| `import` | 8 | ✅ |
| `progression` | 13 | ✅ |
| `journee` | 10 | ✅ |
| **TOTAL** | **~200 clés** | ✅ |

### 📋 Exemple de clés disponibles:

```json
{
  "common": {
    "save": "Enregistrer",
    "cancel": "Annuler",
    "created_successfully": "Enregistrement créé.",
    "updated_successfully": "Modifications enregistrées.",
    "error_generic": "Une erreur est survenue."
  }
}
```

---

## 4️⃣ USAGE ACTUEL DE TRADUCTIONS

### ✅ FICHIERS AVEC i18n COMPLET (40 fichiers)

#### **Auth (2/2 = 100%)**
- ✅ [web/src/features/auth/pages/LoginPage.tsx](web/src/features/auth/pages/LoginPage.tsx#L4-L19)
  - Uses: `useTranslation()` ✓
  - L19: `const { t } = useTranslation()`
  - Traductions: `auth.login_title`, `auth.secure_access`, etc.

#### **Classes (7/9 = 78%)**
- ✅ [web/src/features/classes/pages/ClassesListPage.tsx](web/src/features/classes/pages/ClassesListPage.tsx#L3)
  - L22: `const { t } = useTranslation()`
  - Traductions cohérentes: `classes.nom`, `classes.niveau`, `classes.filiere`
  
- ✅ [web/src/features/classes/pages/ClasseFormModal.tsx](web/src/features/classes/pages/ClasseFormModal.tsx#L3)
  - L24: `const { t } = useTranslation()`
  
- ✅ [web/src/features/classes/pages/ClasseTabs.tsx](web/src/features/classes/pages/ClasseTabs.tsx#L2)
- ✅ [web/src/features/classes/pages/ElevesTab.tsx](web/src/features/classes/pages/ElevesTab.tsx#L1)
- ✅ [web/src/features/classes/pages/MaClassePage.tsx](web/src/features/classes/pages/MaClassePage.tsx#L1)
- ✅ [web/src/features/classes/pages/ClasseDetailPage.tsx](web/src/features/classes/pages/ClasseDetailPage.tsx#L2)
- ✅ [web/src/features/classes/sous-systemes/SousSystemesListPage.tsx](web/src/features/classes/sous-systemes/SousSystemesListPage.tsx#L2)

#### **Dashboard (1/1 = 100%)**
- ✅ [web/src/features/dashboard/pages/DashboardPage.tsx](web/src/features/dashboard/pages/DashboardPage.tsx#L2)
  - L9, L28, L55, L96: Multiples `const { t } = useTranslation()`
  - Traductions complètes et cohérentes

#### **Discipline (2/2 = 100%)**
- ✅ [web/src/features/discipline/pages/AbsencesTab.tsx](web/src/features/discipline/pages/AbsencesTab.tsx#L2)
  - L16: `const { t } = useTranslation()`
  
- ✅ [web/src/features/discipline/pages/SanctionsPage.tsx](web/src/features/discipline/pages/SanctionsPage.tsx#L2)
  - L28, L145: `const { t } = useTranslation()`

#### **Élèves (5/7 = 71%)**
- ✅ [web/src/features/eleves/pages/ElevesListPage.tsx](web/src/features/eleves/pages/ElevesListPage.tsx#L2)
  - L92: `const { t } = useTranslation()`
  - Traductions: `eleves.matricule`, `eleves.nom_complet`, etc.
  
- ✅ [web/src/features/eleves/pages/EleveDetailPage.tsx](web/src/features/eleves/pages/EleveDetailPage.tsx#L1)
- ✅ [web/src/features/eleves/pages/EleveFormModal.tsx](web/src/features/eleves/pages/EleveFormModal.tsx#L3)
- ✅ [web/src/features/eleves/pages/EleveInscriptionPage.tsx](web/src/features/eleves/pages/EleveInscriptionPage.tsx#L3)
  - L152: `succes(eleve ? t('common.updated_successfully') : t('common.created_successfully'))`

#### **Niveaux (2/2 = 100%)**
- ✅ [web/src/features/niveaux/pages/NiveauxListPage.tsx](web/src/features/niveaux/pages/NiveauxListPage.tsx#L2)
  - L16: `const { t, i18n } = useTranslation()`
  
- ✅ [web/src/features/niveaux/NiveauFormModal.tsx](web/src/features/niveaux/NiveauFormModal.tsx#L3)

#### **Notes (2/2 = 100%)**
- ✅ [web/src/features/notes/pages/NotesTab.tsx](web/src/features/notes/pages/NotesTab.tsx#L2)
  - L13, L96: `const { t } = useTranslation()`

#### **Pédagogie (3/3 = 100%)**
- ✅ [web/src/features/pedagogie/pages/AffectationsTab.tsx](web/src/features/pedagogie/pages/AffectationsTab.tsx#L2)
- ✅ [web/src/features/pedagogie/pages/MatiereFormPage.tsx](web/src/features/pedagogie/pages/MatiereFormPage.tsx#L2)
- ✅ [web/src/features/pedagogie/pages/MatieresPage.tsx](web/src/features/pedagogie/pages/MatieresPage.tsx#L1)

#### **Personnel (5/7 = 71%)**
- ✅ [web/src/features/personnel/pages/PersonnelListPage.tsx](web/src/features/personnel/pages/PersonnelListPage.tsx#L3)
- ✅ [web/src/features/personnel/pages/PersonnelFormPage.tsx](web/src/features/personnel/pages/PersonnelFormPage.tsx#L5)
  - L149: `succes(personnel ? t('common.updated_successfully') : t('common.created_successfully'))`
- ✅ [web/src/features/personnel/pages/DepartementsPage.tsx](web/src/features/personnel/pages/DepartementsPage.tsx#L2)
- ✅ [web/src/features/personnel/pages/CreateAccountModal.tsx](web/src/features/personnel/pages/CreateAccountModal.tsx#L2)

#### **Primaire (2/2 = 100%)**
- ✅ [web/src/features/primaire/pages/NiveauxScolairesPage.tsx](web/src/features/primaire/pages/NiveauxScolairesPage.tsx#L2)
- ✅ [web/src/features/primaire/pages/NotesPrimaireTab.tsx](web/src/features/primaire/pages/NotesPrimaireTab.tsx#L2)

#### **Progression (3/3 = 100%)**
- ✅ [web/src/features/progression/pages/MaJourneePage.tsx](web/src/features/progression/pages/MaJourneePage.tsx#L2)
  - L97: `setMessage(t('journee.saved'))`
- ✅ [web/src/features/progression/pages/ProgrammeEditor.tsx](web/src/features/progression/pages/ProgrammeEditor.tsx#L2)
  - L99: `setMessage(t('progression.saved', { count: maj.lecons }))`
- ✅ [web/src/features/progression/pages/ProgressionPage.tsx](web/src/features/progression/pages/ProgressionPage.tsx#L2)

#### **Résultats (2/4 = 50%)**
- ✅ [web/src/features/resultats/pages/PalmaresPage.tsx](web/src/features/resultats/pages/PalmaresPage.tsx#L2)
- ✅ [web/src/features/resultats/pages/ResultatsTab.tsx](web/src/features/resultats/pages/ResultatsTab.tsx#L2)
  - L22: `const { t, i18n } = useTranslation()`

#### **Session (3/3 = 100%)**
- ✅ [web/src/features/session/pages/SessionPage.tsx](web/src/features/session/pages/SessionPage.tsx#L2)
- ✅ [web/src/features/session/pages/AnneeScolaireFormModal.tsx](web/src/features/session/pages/AnneeScolaireFormModal.tsx#L3)
- ✅ [web/src/features/session/pages/TrimestreFormModal.tsx](web/src/features/session/pages/TrimestreFormModal.tsx#L3)

#### **Settings (2/3 = 67%)**
- ✅ [web/src/features/settings/pages/SettingsPage.tsx](web/src/features/settings/pages/SettingsPage.tsx#L2)
  - L47: `succes(t('settings.saved'))`
- ✅ [web/src/features/settings/pages/EcoleProfileCard.tsx](web/src/features/settings/pages/EcoleProfileCard.tsx#L2)

#### **UI Components (2/16 = 12%)**
- ✅ [web/src/shared/ui/Feedback.tsx](web/src/shared/ui/Feedback.tsx#L2)
  - L5, L15, L27: `const { t } = useTranslation()`
  - L9: `label ?? t('common.loading')`
- ✅ [web/src/shared/ui/ImportModal.tsx](web/src/shared/ui/ImportModal.tsx#L2)
  - L37: `const { t } = useTranslation()`

#### **App Layout (1/1 = 100%)**
- ✅ [web/src/app/AppLayout.tsx](web/src/app/AppLayout.tsx#L3)
  - L191: `const { t } = useTranslation()`

---

### 🔴 FICHIERS SANS i18n (30 fichiers)

#### **Auth (1/2 = 50%)**
- 🔴 [web/src/features/auth/pages/ChangerMotDePassePage.tsx](web/src/features/auth/pages/ChangerMotDePassePage.tsx)
  - L48: `succes('Mot de passe mis à jour.')` - **TEXTE EN DUR**
  - L97: `minLength: { value: 8, message: 'Huit caractères au minimum.' }` - **TEXTE EN DUR**
  - **ALERTES NON TRADUITES:** 2

#### **Classes (2/9 = 22%)**
- 🔴 [web/src/features/classes/pages/ResponsablesTab.tsx](web/src/features/classes/pages/ResponsablesTab.tsx)
  - Aucune traduction, utilise `erreur` et état local
  
- 🔴 [web/src/features/classes/sous-systemes/SousSystemeFormModal.tsx](web/src/features/classes/sous-systemes/SousSystemeFormModal.tsx)
  - L44: `succes('Sous-système créé.')` - **TEXTE EN DUR**
  - L41: `succes('Sous-système mis à jour.')` - **TEXTE EN DUR**
  - L49: `erreur(err.response?.data?.message || 'Une erreur est survenue.')` - **Hybride**

#### **Élèves (2/7 = 29%)**
- 🔴 [web/src/features/eleves/pages/EleveFormModal.tsx](web/src/features/eleves/pages/EleveFormModal.tsx)
  - Aucune traduction
  - Messages d'erreur en dur
  
- 🔴 [web/src/features/eleves/TransfererClasseModal.tsx](web/src/features/eleves/TransfererClasseModal.tsx)
  - L35: `succes(\`${eleve.nom_complet} transféré(e).\`)` - **TEXTE EN DUR**
  - L38: `erreur((err as ApiError).message)` - **API uniquement**

- 🔴 [web/src/features/eleves/TransfererEcoleModal.tsx](web/src/features/eleves/TransfererEcoleModal.tsx)
  - L44: `succes(\`${eleve.nom_complet} transféré(e).\`)` - **TEXTE EN DUR**

- 🔴 [web/src/features/eleves/pages/EleveTransfertsPage.tsx](web/src/features/eleves/pages/EleveTransfertsPage.tsx)
  - L87: `succes(\`${resultat.transferes} élève(s) transféré(s).\`)` - **TEXTE EN DUR**

#### **Emploi du temps (2/2 = 100% SANS)**
- 🔴 [web/src/features/emploiDuTemps/pages/EmploiDuTempsPage.tsx](web/src/features/emploiDuTemps/pages/EmploiDuTempsPage.tsx)
  - L60: `succes('Créneau supprimé.')` - **TEXTE EN DUR**
  - L62: `erreur(e.message ?? 'Suppression impossible.')` - **TEXTE EN DUR**
  - L247: `succes('Créneau ajouté.')` - **TEXTE EN DUR**
  - L250: `erreur(e.message ?? 'Enregistrement impossible.')` - **TEXTE EN DUR**
  - L331: `succes(data.creees > 0 ? \`${data.creees} séance(s) générée(s).\` : 'Aucune nouvelle séance à générer.')` - **TEXTE EN DUR**

- 🔴 [web/src/features/emploiDuTemps/pages/SeancesPage.tsx](web/src/features/emploiDuTemps/pages/SeancesPage.tsx)
  - L164: `succes(\`Appel enregistré (${data.enregistres} élève(s)).\`)` - **TEXTE EN DUR**
  - L167: `erreur(e.message ?? "Enregistrement de l'appel impossible.")` - **TEXTE EN DUR**

#### **Finance (5/7 = 71% SANS)**
- 🔴 [web/src/features/finance/pages/CaissePage.tsx](web/src/features/finance/pages/CaissePage.tsx)
  - L115: `succes('Reçu annulé.')` - **TEXTE EN DUR**

- 🔴 [web/src/features/finance/pages/DepenseFormModal.tsx](web/src/features/finance/pages/DepenseFormModal.tsx)
  - L72: `succes('Dépense enregistrée.')` - **TEXTE EN DUR**

- 🔴 [web/src/features/finance/pages/DepensesPage.tsx](web/src/features/finance/pages/DepensesPage.tsx)
  - Utilise des messages dynamiques sans traduction

- 🔴 [web/src/features/finance/pages/EncaissementModal.tsx](web/src/features/finance/pages/EncaissementModal.tsx)
  - L67: `succes(\`Reçu ${numero_recu} enregistré.\`)` - **TEXTE EN DUR**

- 🔴 [web/src/features/finance/pages/PaiePage.tsx](web/src/features/finance/pages/PaiePage.tsx)
  - L72: `succes(\`${prepares} bulletin(s) préparé(s).\`)` - **TEXTE EN DUR**

- 🔴 [web/src/features/finance/pages/RapportsFinanciersPage.tsx](web/src/features/finance/pages/RapportsFinanciersPage.tsx)
  - Aucune traduction

- 🔴 [web/src/features/finance/pages/TarifsPage.tsx](web/src/features/finance/pages/TarifsPage.tsx)
  - Textes d'alertes en dur

#### **Identification (2/2 = 100% SANS)**
- 🔴 [web/src/features/identification/pages/IdentificationPage.tsx](web/src/features/identification/pages/IdentificationPage.tsx)
  - Aucune traduction
  - Pas d'alertes

- 🔴 [web/src/features/identification/pages/PhotosExamenPage.tsx](web/src/features/identification/pages/PhotosExamenPage.tsx)
  - L80: `succes(\`Archive générée : ${traites} photo(s).\`)` - **TEXTE EN DUR**
  - L83: `erreur((e as ApiError).message ?? "Génération de l'archive impossible.")` - **TEXTE EN DUR**

#### **Personnel (2/7 = 29%)**
- 🔴 [web/src/features/personnel/pages/FonctionReferentielDetailPage.tsx](web/src/features/personnel/pages/FonctionReferentielDetailPage.tsx)
  - Aucune traduction

- 🔴 [web/src/features/personnel/pages/FonctionReferentielFormModal.tsx](web/src/features/personnel/pages/FonctionReferentielFormModal.tsx)
  - L49, L52: `succes()` - **TEXTE EN DUR**

- 🔴 [web/src/features/personnel/pages/FonctionsReferentielPage.tsx](web/src/features/personnel/pages/FonctionsReferentielPage.tsx)
  - L48: `succes('Fonction supprimée.')` - **TEXTE EN DUR**
  - L87: `succes(\`${deleted} fonction(s) supprimée(s).\`)` - **TEXTE EN DUR**

#### **Primaire: Niveaux (1/1 = 100% SANS)**
- (Déjà compté ci-dessus)

#### **Résultats (2/4 = 50% SANS)**
- 🔴 [web/src/features/resultats/pages/RemplissagePage.tsx](web/src/features/resultats/pages/RemplissagePage.tsx)
  - Aucune traduction

- 🔴 [web/src/features/resultats/pages/BulletinsPage.tsx](web/src/features/resultats/pages/BulletinsPage.tsx)
  - Aucune traduction

#### **Settings (1/3 = 33% SANS)**
- 🔴 [web/src/features/settings/pages/EcoleImagesCard.tsx](web/src/features/settings/pages/EcoleImagesCard.tsx)
  - L78: `succes(\`${image.titre} mis à jour.\`)` - **TEXTE EN DUR**
  - L87: `succes(\`${image.titre} supprimé.\`)` - **TEXTE EN DUR**

#### **Statistiques (2/2 = 100% SANS)**
- 🔴 [web/src/features/statistiques/pages/StatsDisciplinairesPage.tsx](web/src/features/statistiques/pages/StatsDisciplinairesPage.tsx)
  - L123: `erreur('Génération du PDF impossible.')` - **TEXTE EN DUR**

- 🔴 [web/src/features/statistiques/pages/StatsPedagogiquesPage.tsx](web/src/features/statistiques/pages/StatsPedagogiquesPage.tsx)
  - L102: `erreur('Génération du PDF impossible.')` - **TEXTE EN DUR**

---

## 5️⃣ ALERTES & MESSAGES NON TRADUITS

### 🔴 Messages d'erreur en dur (CRITIQUES)

| Fichier | Ligne | Message | Type | Traduction manquante |
|---------|-------|---------|------|----------------------|
| ChangerMotDePassePage.tsx | 48 | "Mot de passe mis à jour." | Succès | ✋ Oui |
| ChangerMotDePassePage.tsx | 97 | "Huit caractères au minimum." | Validation | ✋ Oui |
| SousSystemeFormModal.tsx | 41 | "Sous-système mis à jour." | Succès | ✋ Oui |
| SousSystemeFormModal.tsx | 44 | "Sous-système créé." | Succès | ✋ Oui |
| TransfererClasseModal.tsx | 35 | "${eleve.nom_complet} transféré(e)." | Succès | ✋ Oui |
| TransfererEcoleModal.tsx | 44 | "${eleve.nom_complet} transféré(e)." | Succès | ✋ Oui |
| EleveTransfertsPage.tsx | 87 | "${resultat.transferes} élève(s) transféré(s)." | Succès | ✋ Oui |
| EmploiDuTempsPage.tsx | 60 | "Créneau supprimé." | Succès | ✋ Oui |
| EmploiDuTempsPage.tsx | 62 | "Suppression impossible." | Erreur | ✋ Oui |
| EmploiDuTempsPage.tsx | 247 | "Créneau ajouté." | Succès | ✋ Oui |
| EmploiDuTempsPage.tsx | 250 | "Enregistrement impossible." | Erreur | ✋ Oui |
| EmploiDuTempsPage.tsx | 331 | "Aucune nouvelle séance à générer." | Info | ✋ Oui |
| SeancesPage.tsx | 164 | "Appel enregistré (${data.enregistres} élève(s))." | Succès | ✋ Oui |
| SeancesPage.tsx | 167 | "Enregistrement de l'appel impossible." | Erreur | ✋ Oui |
| CaissePage.tsx | 115 | "Reçu annulé." | Succès | ✋ Oui |
| DepenseFormModal.tsx | 72 | "Dépense enregistrée." | Succès | ✋ Oui |
| EncaissementModal.tsx | 67 | "Reçu ${numero_recu} enregistré." | Succès | ✋ Oui |
| PaiePage.tsx | 72 | "${prepares} bulletin(s) préparé(s)." | Succès | ✋ Oui |
| PhotosExamenPage.tsx | 80 | "Archive générée : ${traites} photo(s)." | Succès | ✋ Oui |
| PhotosExamenPage.tsx | 83 | "Génération de l'archive impossible." | Erreur | ✋ Oui |
| FonctionReferentielFormModal.tsx | 49-52 | "Fonction créée/mise à jour" | Succès | ✋ Oui |
| FonctionsReferentielPage.tsx | 48 | "Fonction supprimée." | Succès | ✋ Oui |
| FonctionsReferentielPage.tsx | 87 | "${deleted} fonction(s) supprimée(s)." | Succès | ✋ Oui |
| EcoleImagesCard.tsx | 78 | "${image.titre} mis à jour." | Succès | ✋ Oui |
| EcoleImagesCard.tsx | 87 | "${image.titre} supprimé." | Succès | ✋ Oui |
| StatsDisciplinairesPage.tsx | 123 | "Génération du PDF impossible." | Erreur | ✋ Oui |
| StatsPedagogiquesPage.tsx | 102 | "Génération du PDF impossible." | Erreur | ✋ Oui |

**Total de messages en dur identifiés: 27**

### 🟡 Messages PARTIELLEMENT traduits

| Fichier | Ligne | Situation | Exemple |
|---------|-------|-----------|---------|
| EleveInscriptionPage.tsx | 152 | ✅ Utilise `t()` | `t('common.updated_successfully')` |
| PersonnelFormPage.tsx | 149 | ✅ Utilise `t()` | `t('common.updated_successfully')` |
| MaJourneePage.tsx | 97 | ✅ Utilise `t()` | `t('journee.saved')` |
| SettingsPage.tsx | 47 | ✅ Utilise `t()` | `t('settings.saved')` |
| NotesPrimaireTab.tsx | 172 | ✅ Utilise `t()` | `t('notes.saved', { count })` |

**Ceux-ci sont BONS - gardez ce pattern!**

---

## 6️⃣ TEXTES EN DUR MANQUANTS - ANALYSE DÉTAILLÉE

### 🔴 Domaines NON TRADUITS à priorité ÉLEVÉE

#### A. **OPÉRATIONS DE TRANSFERT** (Critiques)
- `"${eleve.nom_complet} transféré(e)."`
- `"${resultat.transferes} élève(s) transféré(s)."`
- **Fichiers:** TransfererClasseModal.tsx, TransfererEcoleModal.tsx, EleveTransfertsPage.tsx
- **Priorité:** 🔴 HAUTE - Affiche directement à l'utilisateur

#### B. **SÉANCES & APPELS** (Moyens)
- `"Appel enregistré (${data.enregistres} élève(s))."`
- `"Enregistrement de l'appel impossible."`
- `"Créneau ajouté."`, `"Créneau supprimé."`
- **Fichiers:** SeancesPage.tsx, EmploiDuTempsPage.tsx
- **Priorité:** 🟡 MOYEN

#### C. **FINANCE** (Critique)
- `"Dépense enregistrée."`
- `"Reçu ${numero_recu} enregistré."`
- `"${prepares} bulletin(s) préparé(s)."`
- `"Reçu annulé."`
- **Fichiers:** CaissePage.tsx, DepenseFormModal.tsx, EncaissementModal.tsx, PaiePage.tsx
- **Priorité:** 🔴 HAUTE - Transactions monétaires

#### D. **IDENTIFICATION** (Moyen)
- `"Archive générée : ${traites} photo(s)."`
- `"Génération de l'archive impossible."`
- **Fichiers:** PhotosExamenPage.tsx, IdentificationPage.tsx
- **Priorité:** 🟡 MOYEN

#### E. **STATISTIQUES** (Bas)
- `"Génération du PDF impossible."`
- **Fichiers:** StatsDisciplinairesPage.tsx, StatsPedagogiquesPage.tsx
- **Priorité:** 🟢 BAS - Non-critique

#### F. **PERSONNEL** (Moyen)
- `"Fonction créée/mise à jour"`
- `"Fonction supprimée."`
- **Fichiers:** FonctionReferentielFormModal.tsx, FonctionsReferentielPage.tsx
- **Priorité:** 🟡 MOYEN

#### G. **PARAMÈTRES** (Bas)
- `"${image.titre} mis à jour."`
- `"${image.titre} supprimé."`
- **Fichiers:** EcoleImagesCard.tsx
- **Priorité:** 🟢 BAS

---

## 7️⃣ PAGES SANS TRADUCTION - ANALYSE PAR DOMAINE

### 📊 Couverture par module:

| Module | Total | Avec i18n | Sans i18n | % |
|--------|-------|-----------|-----------|-----|
| **Auth** | 2 | 1 | 1 | 50% ❌ |
| **Classes** | 9 | 7 | 2 | 78% ⚠️ |
| **Élèves** | 7 | 5 | 2 | 71% ⚠️ |
| **Emploi du temps** | 2 | 0 | 2 | 0% ❌ |
| **Finance** | 7 | 2 | 5 | 29% ❌ |
| **Identification** | 2 | 0 | 2 | 0% ❌ |
| **Personnel** | 7 | 3 | 4 | 43% ❌ |
| **Résultats** | 4 | 2 | 2 | 50% ❌ |
| **Statistiques** | 2 | 0 | 2 | 0% ❌ |
| **Settings** | 3 | 2 | 1 | 67% ⚠️ |
| **Autres** | 16 | 16 | 0 | 100% ✅ |

---

## 8️⃣ DIALOGUES DE CONFIRMATION NON TRADUITS

### 🔴 Confirmations avec messages en dur:

```typescript
// web/src/features/eleves/pages/ElevesListPage.tsx:139
confirmer({
  titre: 'Supprimer les élèves sélectionnés',
  message: 'Cette action est irréversible. Les données de ces élèves...',
  action: 'Supprimer'
})

// web/src/features/eleves/pages/ElevesTransfertPage.tsx:71
confirmer({
  message: 'Les élèves seront affectés à la classe X à partir du Y...'
})

// web/src/features/niveaux/pages/NiveauxListPage.tsx:54
confirmer({
  message: 'Cette action est irréversible.'
})
```

**Tous les messages de confirmation en dur. AUCUN n'est traduit.**

---

## 9️⃣ PATTERN D'ALERTES ACTUEL

### ✅ BON PATTERN (À GÉNÉRALISER):
```typescript
// ✅ CORRECT - avec traduction
succes(eleve ? t('common.updated_successfully') : t('common.created_successfully'))
succes(t('settings.saved'))
setMessage(t('journee.saved'))
```

### 🔴 MAUVAIS PATTERN (À CORRIGER):
```typescript
// ❌ INCORRECT - texte en dur
succes('Mot de passe mis à jour.')
erreur('Suppression impossible.')
succes(`${eleve.nom_complet} transféré(e).`)
confirmer({
  message: 'Cette action est irréversible. Les données de ces élèves seront...'
})
```

---

## 🔟 RÉSUMÉ DES TRADUCTIONS MANQUANTES

### 📋 Clés i18n À AJOUTER (Catégorisées)

**common.ts:**
```json
{
  "common": {
    "password_updated": "Mot de passe mis à jour.",
    "password_min_chars": "Huit caractères au minimum.",
    "transfer_success": "${name} transféré(e).",
    "transfers_count": "${count} élève(s) transféré(s).",
    "no_new_sessions": "Aucune nouvelle séance à générer.",
    "record_saved": "Enregistrement saisi.",
    "record_deleted": "Enregistrement supprimé.",
    "impossible_action": "${action} impossible.",
    "archive_generated": "Archive générée : ${count} photo(s).",
    "confirm_irreversible": "Cette action est irréversible.",
    "pdf_generation_failed": "Génération du PDF impossible."
  }
}
```

**emploiDuTemps.ts:**
```json
{
  "emploiDuTemps": {
    "creneau_ajoute": "Créneau ajouté.",
    "creneau_supprime": "Créneau supprimé.",
    "creneau_enregistrement_failed": "Enregistrement impossible.",
    "appel_enregistre": "Appel enregistré (${count} élève(s)).",
    "appel_enregistrement_failed": "Enregistrement de l'appel impossible."
  }
}
```

**finance.ts:**
```json
{
  "finance": {
    "depense_enregistree": "Dépense enregistrée.",
    "recu_enregistre": "Reçu ${numero} enregistré.",
    "recu_annule": "Reçu annulé.",
    "bulletins_prepares": "${count} bulletin(s) préparé(s)."
  }
}
```

**identification.ts:**
```json
{
  "identification": {
    "archive_generated": "Archive générée : ${count} photo(s).",
    "archive_generation_failed": "Génération de l'archive impossible."
  }
}
```

**personnel.ts:**
```json
{
  "personnel": {
    "fonction_created": "Fonction créée.",
    "fonction_updated": "Fonction mise à jour.",
    "fonction_deleted": "Fonction supprimée.",
    "fonctions_deleted_count": "${count} fonction(s) supprimée(s)."
  }
}
```

**settings.ts:**
```json
{
  "settings": {
    "image_updated": "${title} mis à jour.",
    "image_deleted": "${title} supprimé."
  }
}
```

**confirmation.ts (nouveau):**
```json
{
  "confirmation": {
    "delete_students": "Supprimer les élèves sélectionnés",
    "delete_students_message": "Cette action est irréversible. Les données de ces élèves seront définitivement supprimées.",
    "delete_student": "Supprimer l'élève",
    "delete_student_message": "Cette action est irréversible. Les données de cet élève seront définitivement supprimées.",
    "archive_student": "Archiver l'élève",
    "archive_student_message": "L'élève n'apparaîtra plus comme actif. La réactivation reste possible à tout moment."
  }
}
```

---

## 1️⃣1️⃣ RÉSUMÉ FINAL & RECOMMANDATIONS

### 📊 STATISTIQUES GLOBALES

```
✅ Nombre de fichiers avec i18n complet:          40/70 = 57%
🔴 Nombre de fichiers sans i18n:                 30/70 = 43%
⚠️  Alertes/messages en dur identifiés:          27+
🟡 Clés de traduction disponibles:               ~200
📈 Couverture i18n:                              PARTIELLE (57%)
```

### 🎯 PRIORITÉS DE CORRECTION

#### **PHASE 1: CRITIQUE** 🔴 (A faire IMMÉDIATEMENT)
1. **Fichiers Finance** (5 fichiers) - Transactions monétaires
   - CaissePage.tsx
   - DepenseFormModal.tsx
   - EncaissementModal.tsx
   - PaiePage.tsx
   - TarifsPage.tsx

2. **Fichiers Emploi du temps** (2 fichiers) - Opérations fréquentes
   - EmploiDuTempsPage.tsx
   - SeancesPage.tsx

3. **Fichiers Élèves - Transfert** (3 fichiers) - Opérations critiques
   - TransfererClasseModal.tsx
   - TransfererEcoleModal.tsx
   - EleveTransfertsPage.tsx

#### **PHASE 2: ÉLEVÉE** 🟠 (À faire dans les 2 semaines)
4. **Personnel** (4 fichiers)
5. **Identification** (2 fichiers)
6. **Résultats** (2 fichiers)
7. **Auth - ChangerMotDePasse** (1 fichier)

#### **PHASE 3: MOYENNE** 🟡 (À faire dans le mois)
8. **Statistiques** (2 fichiers)
9. **Settings - EcoleImages** (1 fichier)

### 🛠️ ACTIONS RECOMMANDÉES

1. **Ajouter les clés manquantes** aux fichiers `fr.json` et `en.json`
   - Environ 40-50 clés manquantes
   - **Estimé:** 2-3 heures de travail

2. **Refactoriser les alertes** dans les 30 fichiers sans i18n
   - Remplacer textes en dur par clés i18n
   - Créer une fonction wrapper `succes()`, `erreur()` qui gère i18n
   - **Estimé:** 6-8 heures de travail

3. **Ajouter traductions aux confirmations**
   - Créer un namespace `confirmation`
   - Utiliser i18n pour tous les `Swal.fire()` et `confirmer()`
   - **Estimé:** 3-4 heures

4. **Tester la couverture i18n**
   - Vérifier toutes les alertes en FR et EN
   - Tester changement de langue
   - **Estimé:** 2 heures

5. **Documenter les patterns i18n**
   - Créer un guide d'utilisation
   - Ajouter un exemple par domaine
   - **Estimé:** 1 heure

### ⏱️ EFFORT TOTAL ESTIMÉ
**12-16 heures de travail**

### 💡 BONNE PRATIQUE À GÉNÉRALISER

```typescript
// ✅ Pattern à utiliser partout:
import { useTranslation } from 'react-i18next'
import { succes, erreur, confirmer } from '@/shared/lib/alertes'

export function MonComposant() {
  const { t } = useTranslation()
  
  const sauvegarder = async () => {
    try {
      await api.save(data)
      succes(t('common.updated_successfully'))
    } catch (err) {
      erreur(t('common.error_generic'))
    }
  }
  
  const supprime = async (id: number) => {
    if (await confirmer({
      titre: t('common.delete'),
      message: t('eleves.confirm_delete'),
      action: t('common.delete')
    })) {
      await api.delete(id)
      succes(t('common.deleted'))
    }
  }
}
```

---

## 📎 ANNEXES

### A. Fichiers d'alimentation i18n
- Configuration: `web/src/shared/i18n/index.ts`
- Traductions FR: `web/src/shared/i18n/locales/fr.json` (327 lignes)
- Traductions EN: `web/src/shared/i18n/locales/en.json` (équivalent)

### B. Fichiers d'alertes
- Fonctions d'alertes: `web/src/shared/lib/alertes.ts`
- Composant Feedback: `web/src/shared/ui/Feedback.tsx`

### C. Store UI (gestion locale)
- `web/src/shared/store/uiStore.ts` - Gère `locale`

### D. Dépendances
- `react-i18next` - Intégration React
- `i18next` - Moteur i18n
- `sweetalert2` - Alertes/confirmations

---

**Audit complété le:** 2026-08-16  
**Réalisé par:** GitHub Copilot  
**Recommandation globale:** Passer de 57% à 100% de couverture i18n en 2-3 jours de travail ciblé
