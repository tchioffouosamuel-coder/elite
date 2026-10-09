# Annulation et retablissement des actions

L'historique prend en charge les nouvelles sauvegardes de notes (secondaire,
primaire et maternelle), leurs observations et les editions simples des fiches
eleves et personnel. Les actions anterieures a l'installation ne sont pas
restaurables. Les suppressions de fiches, paiements, fichiers,
changements de relations et modifications de tuteurs hors import ne sont pas couverts.
Une edition de fiche non couverte forme une limite dans la pile d'annulation.

## Imports

Les nouveaux imports de notes, eleves et personnel sont annulables en bloc.
L'import eleves inclut les tuteurs, leurs liens et les dettes anterieures reprises
(avec leur imputation sur les dossiers deja ouverts). L'import personnel inclut
les nouveaux comptes d'acces, referentiels et changements de titulaires de classe.
Les contacts et comptes preexistants non modifies sont conserves.

Les imports de classes, niveaux, matieres et competences (avec leurs affectations),
departements, fonctions, banques, sous-systemes, appreciations, inventaire,
infrastructures, equipements, vehicules et baremes de frais sont aussi couverts.
La liste explicite est dans `ImportsAnnulables::ROUTES`.

Les lots consecutifs d'un import eleves decoupe partagent le meme UUID et une
revision incrementee a chaque lot. Une action intercalee separe les groupes :
l'annulation ne franchit pas cette action. Les lots reussis d'un import interrompu
restent annulables. La preparation du fichier n'est pas une action en base.
Chaque lot est verifie avant d'etre ajoute au groupe ; une modification concurrente
sur un lot precedent interrompt la suite plutot que de l'integrer silencieusement.
Les lots sans modification conservent seulement un marqueur invisible pour
stabiliser l'UUID de synchronisation, sans proposer une annulation vide.

L'annulation restaure les valeurs precedentes et retire les creations dans l'ordre
de leurs dependances. Le retablissement recree avec de nouveaux IDs et reajuste
les liens du lot, en conservant les tombstones precedents. Les changements des
donnees et de leurs dependances sont verifies et verrouilles avant toute mutation :
un nouveau paiement, une note, un rattachement ou des droits ajoutes a un compte
peuvent bloquer le lot entier (409). Les permissions et toutes les ecoles concernees
doivent toujours etre accessibles. L'historique n'utilise pas le plafond de 200
changements du journal d'audit.

Les autres imports, notamment preinscriptions avec versements, paie, depenses,
documents et imports specialises de presence/progression/transport, restent exclus
et constituent une limite non annulable. Ils demandent une annulation metier de
leurs effets, pas une suppression generique de lignes financieres ou de fichiers.

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
du journal d'audit. Cette table contient des donnees personnelles, y compris les
empreintes de mots de passe des comptes ouverts par import personnel, et doit avoir
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

Les imports eleves decoupes emportent chaque fichier de lot dans l'outbox,
avec l'UUID commun et l'indicateur de dernier lot. Le serveur les importe sans
dependre du dossier temporaire du poste. Les fichiers locaux ne sont nettoyes
qu'apres validation de la transaction (donnees, historique et outbox).
Les imports multi-ecoles emportent aussi leur perimetre original ; le serveur
reverifie l'acces a chaque ecole avant le rejeu ou l'annulation.

Les piles d'autres postes et les anciennes actions web ne sont pas telechargees
vers le desktop. Un conflit de synchronisation reste dans l'outbox et apparait
dans le statut existant ; le pull reconcilie les donnees avec le serveur.
Une migration et une version d'API compatibles doivent etre installees sur
le serveur avant de synchroniser les nouveaux clients desktop.

## Installation et verification

Depuis `api`, executer `php artisan migrate` sur chaque serveur web. Le desktop
execute deja ses migrations au demarrage ; reconstruire/reinstaller le client
pour lui distribuer ces nouveaux fichiers.

Tests : `php vendor/phpunit/phpunit/phpunit --filter="HistoriqueActionsTest|HistoriqueImportsTest|EleveImportDecoupeTest"`.
Build web : `npm run build` depuis `web`.

Pour etendre le perimetre, ajouter explicitement la route et ses verifications
metier dans `HistoriqueActionsService` / `ImportsAnnulables`, puis couvrir les changements de relations
et les effets annexes par des tests. Ne pas utiliser les valeurs masquees ou
tronquees du journal d'audit pour restaurer les enregistrements.
