# ShinedeWake API

Backend PHP Wake-on-LAN pour ShinedeWake.

## Role

Cette API est proprietaire des commandes Wake-on-LAN de l'ecosysteme Shinede. Elle valide la session commune, applique les permissions centralisees, lit/ecrit les tables `wake_*`, envoie les Magic Packets sur le LAN et publie des evenements Mercure best-effort apres les demandes de reveil.

## Repo et deploiement

- Source DEV: `P:\DEV\GitHub\App-ShinedeWake-API`
- Runtime PROD: `P:\PROD\API\wake`
- Endpoint public: `https://api.shinederu.ch/wake/`
- Code projet: `wake`
- Branche normale: `main`

`P:\PROD\API\wake` ne doit contenir que le runtime PHP necessaire. Les migrations et la documentation restent en DEV, sauf intervention DB explicite.

## Endpoints

Toutes les actions utilisent le parametre `action` en camelCase.

- `GET ?action=status`: etat de session Wake.
- `GET ?action=listDevices`: liste des machines autorisees.
- `POST ?action=wakeDevice`: envoi d'un Magic Packet.
- `POST ?action=createDevice`: creation d'une machine.
- `PUT ?action=updateDevice`: modification d'une machine.
- `DELETE ?action=deleteDevice`: suppression d'une machine.
- `GET ?action=listUsers`: liste des comptes pour la gestion Wake.
- `PUT ?action=updateUserPermissions`: attribution/retrait des roles Wake.

Les reponses JSON suivent l'enveloppe commune:

```json
{ "success": true, "data": {} }
```

```json
{ "success": false, "error": "Message lisible" }
```

## Authentification et permissions

- Auth commune via cookie `sid`, tables `auth_sessions` et `users`.
- Les comptes bloques via `users.is_banned` sont refuses si la colonne existe; leur session courante est supprimee.
- Permissions via `Module-ShinedeCore-PHP`, deploye sous `P:\PROD\API\core`.
- Le backend utilise `ProjectAccessService::hasPermission($userId, 'wake', '<permission>')`.
- Role global `core.super_admin`: acces complet implicite.

Permissions stables:

- `wake.devices.wake`: acces au panel et envoi WOL.
- `wake.devices.manage`: creation, edition et suppression des machines.
- `wake.users.manage`: gestion des acces utilisateurs Wake.

Roles projet dans `core_project_roles`:

- `wake`: porte `devices.wake`.
- `manage`: porte `devices.wake`, `devices.manage` et `users.manage`.

La table `wake_user_permissions` est legacy et conservee pour historique/rollback. La source de verite courante est `core_*`.

## Base de donnees

Schema partage: `ShinedeCore`.

Tables projet:

- `wake_devices`
- `wake_device_components`
- `wake_user_permissions` legacy

Tables partagees lues:

- `users`
- `auth_sessions`
- `core_*`

Migrations source:

- `sql/001_wake_core.sql`
- `sql/002_align_user_foreign_keys.sql`
- `sql/003_wake_device_components.sql`
- `Module-ShinedeCore-PHP/sql/001_core_project_access.sql` pour les roles et permissions centralises.

Les changements DB doivent rester non destructifs et versionnes dans `sql/` si significatifs.

## Format MAC et composants materiel

Les adresses MAC peuvent etre saisies avec `-`, `:` ou sans separateur. L'API retire les separateurs, valide 12 caracteres hexadecimaux, puis stocke le format normalise `AA-BB-CC-DD-EE-FF`.

`listDevices`, `createDevice` et `updateDevice` transportent aussi `components`. Chaque composant contient:

- `component_type`: `processor`, `motherboard`, `memory`, `graphics_card`, `storage`, `network_card`, `sound_card`, `capture_card`, `extension_card`, `power_supply`, `cooling`, `case` ou `other`
- `label`: modele ou reference lisible
- `details`: precision optionnelle
- `sort_order`: ordre d'affichage

## Dossiers runtime et fichiers partages

- Logs par defaut: `P:\PROD\API\wake\logs\wake.log`.
- `logs/` est runtime et preserve au deploiement.
- Aucun stockage persistant public supplementaire n'est requis.
- Wake ne partage pas de dossier avec Arcadia ou un autre projet; toute commande externe passe par l'API Wake.

## Logs et observabilite

Chaque tentative de reveil porte un `request_id`/`trace_id`. Evenements principaux:

- `wake_attempt_started`
- `wake_packet_sent`
- `wake_attempt_succeeded`
- `wake_attempt_failed`
- `wake_mercure_publish_failed`

Si `WAKE_LOG_FILE` est vide ou non writable, les traces restent dans `error_log` PHP/FPM.

## Temps reel et evenements

Mercure est optionnel mais supporte cote API pour les evenements de reveil.

- Hub public: `https://mercure.shinederu.ch/.well-known/mercure`
- Publish interne recommande: `http://mercure/.well-known/mercure`
- Topic global appareils: `https://api.shinederu.ch/wake/topics/devices`
- Topic appareil: `https://api.shinederu.ch/wake/topics/devices/{DEVICE_ID}`
- Evenements: `wake.device.wake_requested`, `wake.device.wake_succeeded`, `wake.device.wake_failed`
- Les evenements sont prives par defaut via `WAKE_MERCURE_EVENTS_PRIVATE=1`.

Mercure ne declenche jamais une commande critique. La commande passe par `POST ?action=wakeDevice`, puis l'evenement annonce la demande ou le resultat. Les clients doivent pouvoir se resynchroniser par `status` et `listDevices`.

## Dependances inter-projets

- `Module-Auth-API`: sessions `sid`, table `auth_sessions`, endpoint `https://api.shinederu.ch/auth/`.
- `Module-ShinedeCore-PHP`: permissions centralisees.
- `App-ShinedeWake`: frontend React/Vite.
- `App-Arcadia` ou un autre projet peut demander un reveil uniquement via l'API Wake, avec session utilisateur et permission `wake.devices.wake`. Aucun autre projet ne doit ecrire dans `wake_*` ni envoyer directement le Magic Packet.

## Configuration

Ordre de chargement:

1. `.env` local a l'API Wake.
2. `.env` runtime de `P:\PROD\API\auth` quand disponible.
3. `.env.example` comme fallback non secret.

Variables sans valeur secrete dans la documentation:

- `BASE_URL`
- `AUTH_API_BASE`
- `AUTH_PORTAL_URL`
- `CORS_ALLOWED_ORIGINS`
- `WAKE_DEFAULT_BROADCAST`
- `WAKE_DEFAULT_PORT`
- `WAKE_LOG_ENABLED`
- `WAKE_LOG_FILE`
- `WAKE_PING_ENABLED`
- `WAKE_PING_TIMEOUT_SECONDS`
- `WAKE_PING_COMMAND`
- `WAKE_DB_TYPE`, `WAKE_DB_HOST`, `WAKE_DB_PORT`, `WAKE_DB_NAME`, `WAKE_DB_USER`, `WAKE_DB_PASS`
- fallback partage: `DB_TYPE`, `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`
- `WAKE_MERCURE_HUB_URL`
- `WAKE_MERCURE_PUBLISH_URL`
- `WAKE_MERCURE_PUBLISHER_JWT_KEY`
- `WAKE_MERCURE_TOPIC_BASE`
- `WAKE_MERCURE_EVENTS_PRIVATE`
- `WAKE_MERCURE_PUBLISH_TIMEOUT_SECONDS`

Ne jamais versionner de `.env` contenant des secrets.

## Verifications

```powershell
Get-ChildItem P:\DEV\GitHub\App-ShinedeWake-API -Recurse -Filter *.php | % { php -l $_.FullName }
git -c safe.directory=* -C P:\DEV\GitHub\App-ShinedeWake-API diff --check
rg -n "password|passwd|secret|BEGIN (RSA|OPENSSH|PRIVATE)|api_key|token" P:\DEV\GitHub\App-ShinedeWake-API
```

## Deploiement

Copier uniquement le runtime necessaire vers `P:\PROD\API\wake`:

- `index.php`
- `config\`
- `controllers\`
- `middlewares\`
- `services\`
- `utils\`

Preserver si presents:

- `.env`
- `logs\`
- fichiers runtime generes

Ne pas deployer:

- `.git`, `.github`
- `README.md`, `AGENTS.md`
- `.env.example`
- `sql\`
- tests, caches, brouillons, exports temporaires ou scripts de developpement

## Notes de reprise

Le backend DEV est plus recent que la PROD observee le 2026-06-12 avant resynchronisation. Toujours comparer `DEV` et `PROD` si une correction a pu etre faite directement en production.
