# Elite School — Reste à faire

Ce document liste ce qu'il reste à construire après le Palier A (Fondations) et le cœur académique + outillage du Palier B (Collège), tous livrés et vérifiés. Il sert de reprise de contexte — chaque section donne le modèle de données, les endpoints API et les pages frontend, sur le pattern de l'existant (`Controller → FormRequest → Service → Repository → Model`, RBAC par permission dot-notation, i18n FR/EN, scoping multi-école systématique).

Dernière mise à jour : 10 août 2026 (nuit, branche `claude/primaire-maternelle-archange`).

---

## 1. État actuel

### Palier A — Fondations (terminé)
**API** (`api/`, Laravel 13 + Sanctum + spatie/laravel-permission, MySQL) :
- Auth, RBAC (7 rôles × permissions dot-notation), multi-tenant `école + niveau` via le middleware `tenant`.
- Référentiel (écoles, niveaux, années scolaires, trimestres), personnel, départements, classes, élèves, tuteurs.
- Dashboard de statistiques, i18n FR/EN sur toute la validation.

**Web** : connexion, layout filtré par permission, Dashboard/Personnel/Départements/Classes/Élèves.

### Palier B — Cœur académique Collège (terminé), basé sur le flux _smapp
Construit en s'appuyant sur une lecture détaillée de `_smapp` (moyennes pondérées par coefficient, rangs façon classement sportif avec ex-aequo, absences en heures cumulées par trimestre, structure de bulletin) — porté sur un schéma normalisé, à l'opposé de l'anti-pattern `_smapp` (tables dynamiques par classe, JSON, FK en texte libre).

**Pédagogie** : `Matiere` (catalogue par école), `ClasseMatiere` (pivot classe↔matière avec coefficient/enseignant/quota/groupe d'affichage bulletin), chef de département (`Departement.head_personnel_id`). Pages : Matières (CRUD), onglet Affectations sur la fiche classe.

**Évaluations** : `Sequence` (générées automatiquement par trimestre selon `Setting::num_sequences`), `Note` (élève × classe_matiere × séquence, /20). `MoyenneService` calcule à la demande (pas de cache dénormalisé) : moyenne matière, moyenne générale pondérée, rangs ex-aequo, rang/min/max par matière, cote (A+→D), appréciation, **mentions "travail" et "conduite"** (félicitations/encouragements/avertissement/blâme — seuils configurables, cf. Settings ci-dessous ; _smapp avait ces réglages mais ne les câblait jamais réellement).

**Résultats** : état de remplissage des notes (seuil configurable), classement de classe, palmarès (seuil moyenne **et** seuil d'assiduité, comme `_smapp`), bulletin PDF avec grille de notes groupées, total/coef, cote, rang/min/max par matière, mentions, absences, sanctions du trimestre. Pages : onglet Résultats sur la fiche classe, page Palmarès, bouton bulletin sur les listes élèves/classement.

**Discipline** : `AbsenceTrimestre` (heures justifiées/non justifiées cumulées par élève × trimestre — pas un journal par créneau, fidèle à `_smapp`), `Sanction` (type/durée/motif en colonnes réelles, pas de chaîne packée). Bilan disciplinaire par classe (totaux/moyennes par genre, élève le plus absent). Pages : onglet Absences sur la fiche classe, page Sanctions.

**Settings** (`app/Services/SettingsCatalog.php`) : catalogue complet des préférences repérées dans `_smapp` (`preferences.php`/table `settings`) — nombre de séquences, comportement note vide, seuil de remplissage, seuils palmarès, et surtout les seuils de mentions (félicitations/encouragements/avertissement/blâme travail et conduite) qui existaient côté `_smapp` mais n'étaient branchés nulle part. Page Paramètres (`/parametres`, réservée à `ecoles.manage`) pour les éditer par établissement.

**Exports** — XLSX (`maatwebsite/excel`) : personnel, élèves, classement de classe, palmarès. Word (`phpoffice/phpword`, nouvellement installé) : attestation de scolarité par élève. PDF supplémentaires : palmarès, bilan disciplinaire de classe. Boutons de téléchargement/consultation câblés sur les pages concernées (`shared/lib/download.ts` : `telechargerFichier()` pour Excel/Word, `ouvrirDocument()` pour ouvrir un PDF dans un nouvel onglet).

**Imports en masse** — XLSX/CSV : personnel (déjà backend en Palier A, bouton frontend ajouté), élèves (avec résolution de classe par nom + création de tuteur), notes (par classe/matière/séquence, résolution par matricule, lignes invalides ignorées silencieusement plutôt que de bloquer tout le fichier). Composant frontend générique `shared/ui/ImportModal.tsx` réutilisé sur les trois.

**Sécurité multi-école** : audit systématique de toutes les règles de validation `exists:` référençant une table scopée par école — corrigé via un trait `App\Http\Requests\Api\V1\Concerns\ScopedRules` (`scopedExists()`, `scopedExistsTrimestre()`, `scopedExistsSequence()`) réutilisé dans ~10 `FormRequest`, plus les classes d'import (résolution manuelle scopée par `school_id`). Vérifié avec un vrai scénario cross-tenant (deux écoles, tentative de rattacher un élève/enseignant/classe de l'école B depuis l'école A → rejeté).

**Comptes de test** (mot de passe `password`) : `admin@elites-school.test` (super_admin), `directeur@elites-school.test` (admin_etablissement), `censeur@elites-school.test` (censeur_sg).

### Style documentaire, navigation et session (terminé)
**Documents PDF** — les templates Blade restants (`resources/views/pdf/palmares.blade.php`, `bilan-disciplinaire.blade.php`) ont été repris avec le style visuel réel de `_smapp` (déduit d'une étude directe de son code de génération mPDF/FPDF) : palette ardoise `#292F36` / or `#FFAB02`, en-tête bilingue FR/EN à 3 colonnes (texte + logo si `School.logo_path` existe, sinon monogramme), tableaux à en-tête doré, bloc de signatures bilingue à deux parties, code couleur d'assiduité (vert/orange/rouge selon les seuils `<10h / 10-30h / >30h`, repris du `getAbsenceColorClass()` de `_smapp` qui n'était en réalité jamais stylé côté legacy). Partiels réutilisables : `resources/views/pdf/partials/{styles,header,signatures}.blade.php`. Bulletin et carte scolaire ont depuis quitté Blade pour les générateurs PHP décrits plus bas.

**Sidebar** (`web/src/app/AppLayout.tsx`) — passée d'une liste plate à des groupes explicites façon `_smapp` (`utils/php/sidebar.php`) : Vue d'ensemble, Personnel & structure, Classes & élèves, Pédagogie, Discipline, Résultats, Administration — chaque groupe se filtre selon les permissions comme avant, mais n'apparaît que si au moins un de ses items est visible.

**Page Session** (`/session`, `web/src/features/session/`) — gère les années scolaires (créer, activer) et leurs trimestres (créer, activer), branchée sur `AnneeScolaireController`/`TrimestreController` déjà existants côté API. A nécessité d'exposer `annee_scolaire_id` dans `TrimestreResource` (absent jusqu'ici) pour permettre le regroupement des trimestres par année côté frontend.

**Paramètres > Établissement** (`SchoolController` + `EcoleProfileCard.tsx`) — nouveau contrôleur `GET/PUT /ecole` (permission `ecoles.manage`) pour éditer le profil de l'école (nom, adresse, téléphone, email, en-têtes de documents FR/EN) et surtout **cocher les niveaux qu'elle opère** (Maternelle/Primaire/Collège, pivot `school_niveau`). `ClasseFormModal` filtre désormais son sélecteur de niveau sur ces niveaux activés plutôt que sur la liste globale — vérifié en navigateur (désactivation de la Maternelle → elle disparaît du formulaire de classe, réactivation → elle réapparaît).

### Complexe scolaire, moteur PDF _smapp et vie de classe (terminé)

**Complexe à trois écoles** — ELITES est modélisé comme un `Complexe` regroupant une maternelle, un primaire et un secondaire, chacun portant son `School.type` (le type pilote le mode de fonctionnement : au secondaire un enseignant par matière avec départements ; au primaire et en maternelle un enseignant par classe avec animateurs de niveau, **à construire**). Le super administrateur voit les trois écoles et bascule de l'une à l'autre par un sélecteur en barre supérieure (`web/src/app/SchoolSwitcher.tsx`), qui vide le cache React Query puisque chaque entrée est scopée par établissement. `ScopeEtablissement` accepte toujours `X-School-Id` mais le borne désormais aux écoles du complexe du compte — l'en-tête n'ouvre plus l'accès à un établissement arbitraire. Les comptes rattachés à une école y sont dirigés dès la connexion. **Transfert d'élève** entre écoles (`POST /eleves/{id}/transfert`, super admin seulement) : la classe d'arrivée est obligatoire et doit appartenir à l'établissement de destination, sinon l'élève serait invisible des listes de classe. Seul le flux secondaire est alimenté ; primaire et maternelle n'ont qu'une coquille (école, niveau, année, compte de direction).

**Bulletins sur le moteur _smapp** — la vue Blade a été remplacée par `App\Support\Pdf\BulletinGenerator`, qui assemble le HTML en PHP puis le rend via mPDF, exactement comme `report_cards_single.php`. Le document couvre **toute une classe, un élève par page** (`GET /classes/{id}/bulletins`), le bulletin individuel restant disponible. Structure reprise du legacy : en-tête bilingue à trois colonnes avec logo, bloc élève (photo, état civil, effectif, prof principal, surveillant général), grille de notes par groupe de matières avec une colonne par séquence, synthèse travail/conduite/appréciations/profil de classe, rappel des moyennes de l'année (séquences × trimestres) et cartouche de visas portant cachet et signature. Le conseil de fin de bulletin reprend `getAdvice()` (au-delà de 70 % de matières faibles, constat global plutôt que liste). `BulletinService::donneesClasse()` calcule les statistiques de classe une seule fois pour toutes les pages.

**Cartes scolaires recto-verso** — `CarteScolaireGenerator` (FPDF) produit une planche recto puis sa planche verso par groupe de 10 cartes (5 × 2, A4 paysage), comme `generate_IDcards_for_a_class.php`. Le verso porte les mentions réglementaires, le cachet et la signature ; il est identique pour toutes les cartes, donc insensible au sens de retournement à l'impression.

**Logo, cachet et signature** — `POST/DELETE /ecole/images/{logo|cachet|signature}` et carte d'import dans Paramètres. Le fichier est stocké tel quel plutôt que converti en JPEG : aplatir la transparence rendrait cachet et signature inutilisables en superposition.

**Responsables de classe** — `professeur_principal_id` est rejoint par `surveillant_general_id`, `censeur_id` et `conseiller_orientation_id`, avec un onglet Responsables sur la fiche classe. Le professeur principal et le surveillant général alimentent le bulletin.

**Emploi du temps, séances et appel** — `EmploiDuTemps` (créneaux hebdomadaires classe × jour × heure, chevauchement refusé), `Seance` (séance datée, matérialisée depuis les créneaux sur une période, sans doublon) et `Presence` (pointage élève × séance). Pages : grille jour × heure par classe, liste des séances, feuille d'appel par exception (tout le monde présent par défaut). `EmploiDuTempsService::cumulAbsences()` déduit les heures d'absence d'un trimestre des appels — **non branché** sur `AbsenceTrimestre`, qui reste la saisie manuelle de référence (cf. §3).

**Pages de résultats et d'identification** — Bulletins par classe, État de remplissage des notes, Photos & cartes scolaires (couverture photo de la classe, upload par élève, édition de la planche).

---

## 2. Reste du Palier B (MVP fonctionnel)

### 2.1 Finance scolaire
**Modèles** : `FraisScolaire` (school_id, niveau_id ou classe_id, montant_total, annee_scolaire_id), `Paiement` (eleve_id, montant, date, moyen, recu_numero, enregistre_par personnel_id).
**Permissions** : `finance.view`/`finance.manage` (déjà seedés).
**Endpoints** : `GET/POST /frais-scolaires`, `POST /eleves/{id}/paiements`, `GET /eleves/{id}/paiements` (historique + solde), `GET /paiements/{id}/recu` (PDF — réutiliser le pattern des vues `pdf/*.blade.php` déjà en place).
**Frontend** : onglet Finance sur la fiche élève (solde, historique, bouton "Enregistrer un paiement"), reçu PDF.
**Explicitement hors Palier B** (→ Phase 2) : paie/salaires du personnel, dépenses hors scolarité.

### 2.2 Communication
**Modèles** : `Annonce` (school_id, titre, contenu, publie_par, cible), `NotificationsQueue` (table + job pour découpler l'envoi SMS/WhatsApp, pattern déjà utilisé côté smapp/api).
**Permissions** : `annonces.view`/`annonces.publish` (déjà seedés).
**Endpoints** : `GET/POST /annonces`, notifications internes automatiques (absence saisie → notification parent) via `Events`/`Listeners ShouldQueue`.
**Décision à prendre avant de commencer** : fournisseur SMS/WhatsApp (Twilio, MessageBird, ou solution locale moins chère) — impacte `NotificationService` et le budget d'hébergement récurrent.
**Frontend** : page Annonces, badge de notifications internes dans la topbar.

### 2.3 Portails Parent & Élève (lecture seule)
**Prérequis backend** : middleware `parent.scope` (pas encore créé) — vérifie que le `parent` authentifié est bien rattaché à l'élève demandé via `eleve_tuteur` + `Tuteur.user_id`.
**Endpoints** : réutilisent les endpoints existants (notes/absences/paiements) scopés côté parent, `GET /mes-enfants`.
**Frontend** : layout distinct et simplifié, sélecteur d'enfant, vues en lecture seule.
**Remarque** : `Tuteur.user_id` existe en base mais n'est jamais rempli — il faut un flux "créer un accès parent" symétrique à `PersonnelController::createAccount`.

### 2.4 Interfaces Primaire et Maternelle — livré (branche `claude/primaire-maternelle-archange`)
Le flux pédagogique de ces deux cycles est porté du projet **archange** (`C:/Données/archange`), à côté de celui du secondaire hérité de `_smapp`. Le RBAC est inchangé : mêmes rôles, mêmes permissions dot-notation.

**Ce qui diffère du secondaire**, et pourquoi :
- **Un titulaire par classe** (`classes.titulaire_id`) plutôt qu'un enseignant par affectation classe↔matière — au primaire un seul maître enseigne toutes les matières.
- **Des niveaux d'enseignement** (`niveau_scolaires` : SIL, CP, CE1… / PS, MS, GS) pilotés par un **animateur de niveau**, là où le secondaire s'organise en départements avec un chef. À ne pas confondre avec la table `niveaux`, qui désigne le type d'établissement dans le complexe.
- **Un barème par matière** (`matieres.notation`, 10 à 100) au lieu d'une note sur 20 pondérée par un coefficient : le barème joue le rôle du coefficient.
- **Quatre volets d'évaluation** (`notes.composante` : oral, écrit, savoir-être, et pratique si `matieres.evalue_pratique`), chacun noté à chaque séquence — le secondaire n'a qu'une note par séquence (`composante = 'unique'`).

**Formules** (`MoyennePrimaireService`, portées de `term_reports.php` et `calculate_marks.php`) :
- total d'une séquence = somme de ses volets ;
- note trimestrielle d'une matière = moyenne des totaux de séquence ;
- moyenne générale = `(Σ notes matières × 20) / Σ barèmes` ;
- appréciation par compétence sur le pourcentage du barème : `A+ ≥ 80 %`, `A ≥ 60 %`, `ECA ≥ 50 %`, `NA < 50 %` ;
- moyenne annuelle = moyenne des trimestres renseignés ; passage en classe supérieure au-delà de `passage_moyenne_min` (défaut 10).

**Écart assumé avec archange** : le legacy initialise toutes les notes à 0 en base, ce qui fait entrer chaque matière au dénominateur avant même la saisie et rend les moyennes ininterprétables en cours de trimestre. Ici une matière sans aucune note est exclue du calcul, comme au secondaire.

**Interfaces** : la sidebar remplace « Départements » par « Niveaux » hors secondaire ; l'onglet Notes d'une classe sert la grille par volets ; les écrans Résultats/Remplissage/Bulletins sont partagés, l'aiguillage vers le bon moteur se faisant dans la couche API (`shared/lib/ecole.ts`) et non dans chaque page. Bulletin PDF dédié (`BulletinPrimaireGenerator`, mPDF) reprenant la mise en page d'archange : une ligne par volet, note trimestrielle et appréciation fusionnées par matière, mention « Sur/Over {barème} ».

**Données de démonstration** : `php artisan db:seed --class=PrimaireMaternelleSeeder` crée les niveaux, classes, titulaires, matières et notes du trimestre 1 pour les deux écoles.

**Reste à faire sur ce cycle** : bilan disciplinaire et palmarès n'ont pas d'équivalent primaire dédié (ils utilisent encore le moteur du secondaire) ; l'écran de décisions de passage existe côté API (`GET /classes/{id}/decisions`) mais n'a pas encore de page ; l'import XLSX des notes ne gère pas les volets.

---

## 3. Dette technique et points d'attention

- **Tests automatisés** : `phpunit.xml` configuré mais aucun test réel n'existe. À prioriser : auth/RBAC, `MoyenneService` (moyennes/rangs/mentions — la logique la plus sensible aux régressions), et le scoping multi-école (`ScopedRules` + classes d'import).
- **CORS** : `config/cors.php` autorise `*` en origine — à restreindre avant mise en production.
- **Sanctum** : pas de vrai refresh-token séparé, juste un endpoint `refresh` qui révoque et réémet.
- **Messages d'erreur par défaut** : `ApiResponse::validationError()`/`forbidden()`/etc. ont des messages français codés en dur par défaut — acceptable pour le MVP.
- **`X-School-Id` côté frontend** : déjà envoyé pour les comptes rattachés à un établissement ; à vérifier pour un futur écran super_admin multi-établissements (sélecteur d'établissement actif).
- **Deux sources d'absences** : `AbsenceTrimestre` (heures cumulées saisies à la main, ce que consomme le bulletin) et les pointages d'appel par séance coexistent. `EmploiDuTempsService::cumulAbsences()` sait dériver les heures des appels mais n'écrase pas la saisie manuelle — à arbitrer une fois l'appel réellement utilisé sur le terrain.
- **Bulletin et carte scolaire PDF** : pas de QR/code-barre de vérification d'authenticité (contrairement à `_smapp`) — volontairement différé en Phase 2 (§4, "Vérification de documents").
- **Import notes** : les lignes dont le matricule est introuvable (ou l'élève hors de la classe visée) sont silencieusement ignorées plutôt que remontées une à une à l'utilisateur — le compteur `failed` de la réponse ne couvre que les échecs de validation de ligne (matricule/note absents), pas les matricules inconnus. Acceptable pour le MVP, mais à affiner si l'usage réel montre des fichiers avec beaucoup d'erreurs de saisie.

---

## 4. Backlog Phase 2 (hors périmètre MVP, prévu au cahier des charges)

- **Application mobile Flutter** — consomme la même API `/api/v1`.
- **Présence enseignant par QR code + suivi de progression pédagogique** (cahier des charges §5.1).
- **Transport scolaire (bus)**, **Infirmerie**, **Inventaire matériel**.
- **Paiement Mobile Money** — MTN MoMo / Orange Money.
- **Site vitrine**.
- **Mode offline-first / PWA**.
- **RH avancée** — paie, primes, retenues, avances sur salaire.
- **Vérification de documents par QR signé** — bulletins/cartes/attestations authentifiables.
- **Gestion des revendications (contestations de notes) et PV de conseil de classe** — n'existent pas non plus dans `_smapp` (menus en 404 dans le code legacy) ; terrain vierge, mais les seuils de mentions (§1, Settings) donnent déjà la base de logique métier pour déclencher un conseil de discipline.

---

## 5. Pour reprendre le travail

```bash
# API
cd api
php artisan serve --port=8000

# Web (ou via l'outil de preview du navigateur, config dans .claude/launch.json)
cd web
npm run dev
```

Base de données de test : `php artisan migrate:fresh --seed` depuis `api/` recharge le jeu de données de démonstration (20 élèves, 7 personnels, 4 classes du Collège, 8 matières affectées avec coefficients, notes de démonstration sur le trimestre actif, préférences par défaut).
