# Annulation et retablissement des actions

L'historique prend en charge les nouvelles sauvegardes de notes (secondaire,
primaire et maternelle), leurs observations et les editions simples des fiches
eleves et personnel. Les actions anterieures a l'installation ne sont pas
restaurables. Les imports, suppressions de fiches, paiements, fichiers,
changements de relations et modifications de tuteurs ne sont pas couverts.
Une edition de fiche non couverte forme une limite dans la pile d'annulation.

## API

- `GET /api/v1/historique-actions` : prochaine action a annuler/retablir.
- `POST /api/v1/historique-actions/{uuid}/annuler` : corps `{ "revision": 0 }`.
- `POST /api/v1/historique-actions/{uuid}/retablir` : revision retournee par l'API.

Les endpoints exigent une session authentifiee et un contexte d'etablissement.
L'auteur, les privileges actuels, le perimetre des eleves et l'ouverture des
sequences sont reverifies avant chaque annulation. Un conflit de donnees ou de
revision renvoie 409 et annule toute la transaction. Une nouvelle ecriture
metier reussie abandonne la branche de retablissement du contexte courant.

Les valeurs completes sont conservees dans `actions_annulables`, independamment
du journal d'audit. Cette table contient des donnees personnelles et doit avoir
la meme protection que les fiches sources. Elle n'est jamais exposee via le
catalogue de synchronisation generale ou l'API de consultation de l'historique.

## Desktop et web

Les boutons executent une annulation en base. Hors des champs editables,
Ctrl+Z annule, Ctrl+Y ou Ctrl+Shift+Z retablit. Dans les champs, l'historique de
saisie reste prioritaire. Les grilles de notes sont rechargees apres reussite.

Le desktop conserve sa pile dans sa base locale. `X-Action-Id` et la metadata
`__historique_action` de l'outbox associent l'action locale a son rejeu serveur.
Une annulation synchronisee vise cet UUID et sa revision, et non la derniere
action sur le serveur. Les notes et observations sont retrouvees par leurs
cles metier, car leurs IDs peuvent differer entre replicas. Le retablissement
d'une nouvelle note lui attribue un nouvel ID ; la suppression precedente reste
dans les tombstones pour les clients ayant deja recu l'ancien ID.

Les piles d'autres postes et les anciennes actions web ne sont pas telechargees
vers le desktop. Un conflit de synchronisation reste dans l'outbox et apparait
dans le statut existant ; le pull reconcilie les donnees avec le serveur.
Une migration et une version d'API compatibles doivent etre installees sur
le serveur avant de synchroniser les nouveaux clients desktop.

## Installation et verification

Depuis `api`, executer `php artisan migrate` sur chaque serveur web. Le desktop
execute deja ses migrations au demarrage ; reconstruire/reinstaller le client
pour lui distribuer ces nouveaux fichiers.

Tests : `php vendor/phpunit/phpunit/phpunit tests/Feature/HistoriqueActionsTest.php`.
Build web : `npm run build` depuis `web`.

Pour etendre le perimetre, ajouter explicitement la route et ses verifications
metier dans `HistoriqueActionsService`, puis couvrir les changements de relations
et les effets annexes par des tests. Ne pas utiliser les valeurs masquees ou
tronquees du journal d'audit pour restaurer les enregistrements.
