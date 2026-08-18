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
flutter run --dart-define=API_URL=http://10.0.2.2:8000/api/v1
```

`10.0.2.2` est l'alias de la machine hôte vu depuis l'émulateur Android :
`localhost` y désignerait le téléphone lui-même. Sur un appareil physique,
utiliser l'IP de la machine sur le réseau local.

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

## Reste à faire

- Écrans métier au-delà du socle : appel, saisie des notes, scan QR, fiche élève
- Notifications FCM côté app (le serveur est prêt, `PUSH_DRIVER=fcm`)
- Tâche de fond `WorkManager` : filet si l'app est tuée avec une outbox pleine
- Traductions FR/EN (les clés existent côté web, à reprendre)
