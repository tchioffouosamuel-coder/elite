# Elites Mobile

Application mobile de la Fondation ELITES. Consomme l'API Laravel de `../api`,
sans la remplacer ni la dupliquer.

## Principe : la base locale est la vérité

Aucun écran n'appelle l'API. Chaque écran observe un `Stream` Drift (SQLite
local) ; chaque action écrit d'abord en local, puis dépose une opération dans
une file persistante (`outbox_operations`). Le moteur de synchronisation la
vide quand il y a du réseau.

Conséquence : **le mode hors-ligne n'est pas un cas particulier**, c'est le
fonctionnement normal. Le mode connecté n'est qu'une variante plus rapide à se
réconcilier.

```
Geste  →  Drift (UI redessinée)  →  Outbox  →  POST /sync  →  GET /sync?depuis=  →  réconciliation
 0 ms          ~15 ms              persistée    au réseau       delta serveur        badges ✓
```

## Démarrer

```bash
flutter pub get
dart run build_runner build       # génère database.g.dart (Drift)
flutter run
```

L'app vise par défaut l'API de production
(`https://elite-g0k9.onrender.com/api/v1`). Pour travailler contre l'API
locale :

```bash
flutter run --dart-define=API_URL=http://10.0.2.2:8000/api/v1
```

`10.0.2.2` est l'alias de la machine hôte vu depuis l'émulateur Android :
`localhost` y désignerait le téléphone lui-même. Sur un appareil physique,
utiliser l'IP de la machine sur le réseau local.

### Mise en veille de l'hébergement

Render endort le service après inactivité. Mesuré : **38 s pour la première
requête**, 2 s ensuite. D'où deux délais volontairement dissymétriques dans
`api_client.dart` :

| Délai | Valeur | Raison |
|---|---|---|
| Réception | 90 s | Doit couvrir le réveil, sinon la première requête de la journée échoue toujours |
| Connexion | 15 s | Le lien TCP aboutit vite même pendant le démarrage — c'est lui qui détecte un vrai « hors réseau », et il ne doit pas faire patienter un téléphone en mode avion |

L'écran de connexion annonce « Réveil du serveur… » après 5 s d'attente : 40 s
de spinner muet passeraient pour un plantage.

> **À arbitrer** : la tâche de fond se réveille tous les quarts d'heure et
> paiera ce réveil de 40 s chaque fois que le service s'est rendormi — batterie
> et données mobiles pour rien. Soit un plan sans mise en veille, soit espacer
> la tâche à une heure.

### JDK

Le build Android est épinglé sur le JDK 21 via `android/gradle.properties`.
La machine ayant `JAVA_HOME` sur un JDK 25 que Gradle 8.14 ne sait pas piloter,
l'échec se manifesterait sinon par un laconique `25.0.3`.

## Organisation

```
lib/
├─ core/
│  ├─ db/          schéma Drift (miroir de RegistreSync côté API) + application des deltas
│  ├─ network/     client Dio, en-têtes, traduction des erreurs
│  ├─ session/     jeton Sanctum (stockage sécurisé), école active, privilèges
│  ├─ sync/        outbox, moteur, curseur, back-off
│  └─ ui/          thème, points de rupture, états (vide / erreur / périmé)
└─ features/       un dossier par domaine, calqué sur web/src/features/
```

Le `core/db/database.dart` porte `tablePourEntite()` : **c'est le seul endroit à
compléter** quand une entité est ajoutée au `RegistreSync` côté API.

## Ce qui est disponible hors connexion

| Palier | Contenu | Mode |
|---|---|---|
| 1 | Appel, notes, ma journée, sanctions, consultation élèves/classes | Lecture **et** écriture |
| 2 | Bulletins PDF, statistiques, situation financière | Lecture seule, datée |
| 3 | Encaissements, paie, génération PDF, imports, privilèges | En ligne uniquement |

Le palier 3 n'est pas une limite technique mais une décision : un reçu porte un
numéro de série alloué par le serveur. Un téléphone hors-ligne ne peut pas se
l'attribuer sans risquer un doublon comptable, et un encaissement affiché comme
réussi puis rejeté à la synchro serait pire que pas d'encaissement — le parent
est reparti avec un reçu.

## Contrat de synchronisation

- `GET /api/v1/sync?depuis={curseur}` → `{curseur, complet, donnees, suppressions}`
  Tant que `complet` est faux, rappeler immédiatement avec le curseur rendu.
- `POST /api/v1/sync` → `{resultats: [{id, statut, reponse}]}`
  Chaque opération réussit ou échoue indépendamment.
- Toute écriture porte un en-tête `Idempotency-Key` (l'id d'opération de
  l'outbox, généré au moment du geste) : un rejeu ne crée jamais de doublon.

## Écrans livrés

| Écran | Rôle | Hors-ligne |
|---|---|---|
| Ma journée | Séances du jour, point d'entrée enseignant | Lecture |
| Appel | Tap = présent/absent, appui long = motif | **Écriture** |
| Clôture de séance | Contenu traité, leçons, observations de fin de cours | **Écriture** |
| Saisie des notes | Grille par séquence, sauvegarde à la frappe | **Écriture** |
| Scan QR | Résolution locale du jeton de salle → appel | **Écriture** |
| Fiche élève | Identité, notes, discipline (feuille à onglets) | Lecture |
| Sanction | Saisie par le surveillant général | **Écriture** |
| Centre de synchro | File d'attente, échecs et motifs | — |

### Conventions d'écriture hors-ligne

Les lignes créées localement portent un **identifiant négatif et
déterministe**, dérivé de leur clé métier — `-(seance × 100000 + eleve)` pour
une présence, `-(matiere × 10⁶ + sequence × 10⁴ + eleve)` pour une note.
Refaire le même appel hors connexion écrase donc la même ligne au lieu d'en
empiler une seconde, et le signe négatif exclut toute collision avec les
identifiants attribués par le serveur.

Deux écarts assumés par rapport au serveur, tous deux au bénéfice du terrain :

- **Le scan QR ne passe pas par `/ma-journee/qr/{token}`.** Le jeton est résolu
  dans la base locale : le geste a lieu en entrant en classe, exactement là où
  le réseau manque.
- **Aucun filtre de créneau horaire** au scan, là où `creneauActuel()` refuse
  hors fenêtre ± 10 min. Un enseignant en retard doit pouvoir faire son appel.

## Reste à faire

- Notifications FCM côté app (le serveur est prêt, `PUSH_DRIVER=fcm`)
- Tâche de fond `WorkManager` : filet si l'app est tuée avec une outbox pleine
- Traductions FR/EN (les clés existent côté web, à reprendre)
- Paliers 2 et 3 : bulletins en cache, annonces, caisse (en ligne uniquement)

> **Donnée manquante côté serveur** : la table `emplois_du_temps` est vide.
> Tant qu'aucun emploi du temps n'est saisi depuis le web, « Ma journée »
> restera vide et le scan QR ne trouvera aucune séance — ce n'est pas un défaut
> de l'app, mais un préalable à tout test terrain réaliste.
