# Reprise du travail — application mobile Elite School

Tu reprends le développement de l'application Flutter `mobile/`, miroir
fonctionnel du portail web `web/`, adossée à l'API Laravel `api/`.

## Contexte du projet

- **API Laravel** : `C:\laragon\www\elites_school\api` — production sur
  `https://elite-g0k9.onrender.com/api/v1`, base MySQL Aiven.
- **Portail web React** : `C:\laragon\www\elites_school\web` — c'est **la
  référence fonctionnelle**. Le mobile doit offrir les mêmes actions ; seules
  l'UI et l'UX changent.
- **Mobile Flutter** : `C:\laragon\www\elites_school\mobile` — nom de paquet
  Dart : `elites_mobile` (attention : pas `mobile`, les imports de test
  s'écrivent `package:elites_mobile/...`).

Établissement client : Fondation ELITES, Bertoua, Cameroun. Trois écoles
(maternelle, primaire, collège/secondaire) dans une même base multi-tenant.

## Architecture, et pourquoi

**Deux régimes assumés, ne pas les uniformiser :**

1. **Hors ligne** (base Drift locale + outbox) — la boucle quotidienne de
   l'enseignant : appel, notes, sanctions, séances, élèves, classes, annonces.
   Répliquée par `GET /sync` (delta à curseur) et poussée par l'outbox.
2. **En ligne** (appels API directs) — tout le reste : finances, transport,
   inventaire, statistiques, administration. Répliquer un rapport de paie sur
   le téléphone de chaque enseignant n'aurait aucun sens.

**Fichiers structurants :**

| Fichier | Rôle |
|---|---|
| `lib/core/nav/destinations.dart` | Les 12 groupes et ~45 destinations, calqués sur `web/src/app/AppLayout.tsx`. C'est **la table de vérité de la parité**. |
| `lib/core/nav/routeur.dart` | Associe chaque chemin web à son écran mobile. Explicite, pour se relire en regard des destinations. |
| `lib/core/nav/tiroir.dart` | Filtre les destinations selon les privilèges (mêmes règles que le web). |
| `lib/core/nav/barre_app.dart` | Barre commune : bouton menu + état de synchro. **Tout écran doit l'utiliser**, sinon le tiroir devient inatteignable. |
| `lib/core/ui/ecran_liste.dart` | Socle des listes en ligne : chargement, erreur, vide, recherche, tiré-pour-rafraîchir, et les gestes. |
| `lib/core/ui/gestes_modules.dart` | Actions déclarées par module (champs, validation, actions métier). **À enrichir pour chaque nouveau module.** |
| `lib/core/ui/formulaire.dart` | Formulaire générique piloté par déclaration. |
| `lib/core/ui/actions_ressource.dart` | Créer / modifier / supprimer / actions métier. |
| `lib/core/network/documents.dart` | Téléchargement authentifié + ouverture des PDF. |
| `api/app/Support/Sync/RegistreSync.php` | Les 17 entités répliquées côté serveur. |

## Conventions à respecter

- **Tout en français** : noms de classes, variables, commentaires, libellés.
- **Les commentaires expliquent le POURQUOI**, jamais le quoi. Un commentaire
  qui paraphrase le code est à supprimer.
- Le **serveur détient les règles de validation**. Les rejouer côté mobile
  évite des allers-retours inutiles, mais ses erreurs par champ doivent
  remonter dans le formulaire — ne jamais diverger volontairement.
- Les **boutons d'écriture n'apparaissent que si le privilège existe**
  (`peutEcrire(context, 'xxx.manage')`), pour ne pas faire remplir un
  formulaire refusé ensuite en 403.

## Protocole de vérification — non négociable

`flutter analyze` et un build qui passe **ne prouvent que la compilation**.
Toute cette session, les vrais défauts ont été trouvés en interrogeant l'API
réelle, jamais par le compilateur.

**Pour chaque écran d'écriture :**

1. Lire le contrat exact : `app/Http/Requests/...` ou le `validate()` du
   contrôleur, et la Resource de réponse.
2. Interroger l'API locale avec `curl` et **le corps exact** qu'enverra
   l'écran, jeton Sanctum temporaire + en-tête `X-School-Id`.
3. Vérifier la réponse, puis **nettoyer les données de test**.
4. `flutter analyze` et `flutter test`.

**Ne pas lancer `flutter build`** — le client s'en charge lui-même.

### Avertissement sur le nettoyage des données

J'ai supprimé 4 bulletins de paie alors que mon test n'en avait créé que 2 :
ma requête visait la période entière au lieu des identifiants créés. Deux
bulletins réels ont été perdus (base locale, école 3).

**Toujours** : relever les identifiants exacts créés, vérifier qu'ils
correspondent bien à la donnée de test, puis supprimer ceux-là seulement.

## Pièges déjà rencontrés — ne pas les refaire

| Piège | Réalité |
|---|---|
| Dossiers de scolarité | 202 sur 203 n'existent pas : appeler `GET eleves/{id}/scolarite` pour l'ouvrir avant d'encaisser. |
| `DossierScolariteResource` | `eleve.classe` est une **chaîne**, pas un objet imbriqué comme ailleurs. |
| Annulation de versement | Exige un `motif` (`required, min:3`). |
| QR de salle | Encode `{origine}/qr/{jeton}`, pas le jeton brut (cf. `lib/features/qr/jeton_qr.dart`). |
| Jetons Sanctum | Contiennent un `|` — ne jamais s'en servir comme séparateur en shell. |
| Drift `Variable` | Typé `<T extends Object>` : un `Variable(valeurDynamique)` compile mais lève à l'exécution. |
| Jointure d'URL | La base finit par `/api/v1` sans barre : `_absolu()` dans `api_client.dart` s'en charge, ne pas le contourner. |
| Réveil du serveur | Render endort le service : ~38 s au premier appel, d'où un délai de réception à 90 s. |

## Fait

- Socle de synchronisation complet (delta, tombstones, idempotence, push par
  lots, jetons d'appareil, service push FCM côté serveur).
- 24 fichiers d'écrans, **40 destinations sur 45** câblées.
- Modules avec actions complètes : véhicules, trajets, inventaire, infirmerie,
  dépenses, avances, sous-systèmes, réclamations, départements, années
  scolaires.
- Écrans dédiés vérifiés contre l'API : **encaissement**, **paie** (cycle
  préparer/arrêter/payer), **emploi du temps** (créneaux + génération des
  séances), **remplissage des notes**, **codes QR**, **bulletins et documents**.
- 12 tests : synchronisation, jointure d'URL, extraction de jeton QR.

## À faire, par ordre de valeur

1. **Câbler deux écrans orphelins** au tiroir : `/sanctions` et `/seances`
   existent déjà mais ne sont atteignables que par « Ma journée » et la fiche
   élève.
2. **Transferts en masse** — `POST eleves/batch-transfert-classe` et
   `batch-transfert-ecole`. Sélection multiple d'élèves puis classe cible.
3. **Photos & cartes** et **photos d'examen** — accès appareil photo et
   galerie (`image_picker`), envoi via `POST eleves/{id}/photo`.
4. **Imports Excel** — `POST eleves/import`, `personnels/import`,
   `matieres/import`, `classes/import`. Nécessite `file_picker`.

## Deux blocages hors de l'app

1. **Migrations de production non appliquées.** Sans elles, inventaire,
   réclamations, avances et annonces répondent « table introuvable ». À lancer
   depuis le Shell Render : `php artisan migrate --force` (le
   `docker/entrypoint.sh` ne les joue pas automatiquement, volontairement).
2. **Emploi du temps vide en base.** « Ma journée » et le scan QR resteront
   vides tant qu'aucun créneau n'est saisi et qu'aucune séance n'est générée.
   L'écran mobile permet désormais les deux.

## Environnement Windows

- Python : `"C:\Program Files\Python311\python.exe"` (le `python` nu ouvre le
  Microsoft Store).
- Flutter : `"C:\flutter\bin\flutter.bat"`, Dart : `"C:\flutter\bin\dart.bat"`.
- MySQL local : démarrer `mysqld` de Laragon s'il ne répond pas.
- Ne jamais tuer un build Gradle de force : cela laisse des verrous dans
  `.dart_tool/hooks_runner/` qui bloquent les builds suivants pendant 5 min.
