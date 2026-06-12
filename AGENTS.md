# Guide Agents - ShinedeWake API

Ce depot contient le backend PHP Wake-on-LAN. Il doit rester deployable dans le runtime stable `P:\PROD\API\wake` sans copier de fichiers de developpement en production.

## Lecture de demarrage

1. Lire `P:\AGENTS.md`.
2. Lire `P:\ECOSYSTEM.md`.
3. Lire `P:\DEV\GitHub\README.md`.
4. Lire `P:\DEV\GitHub\AGENTS.md`.
5. Lire ce fichier.
6. Lire `README.md`.
7. Lire les migrations `sql\` si le changement touche la DB.

## Source de verite

- Backend DEV: `P:\DEV\GitHub\App-ShinedeWake-API`
- Backend PROD: `P:\PROD\API\wake`
- Frontend DEV: `P:\DEV\GitHub\App-ShinedeWake`
- Endpoint API: `https://api.shinederu.ch/wake/`
- Code projet: `wake`
- Tables DB: `wake_*` dans `ShinedeCore`
- Permissions: `wake.devices.wake`, `wake.devices.manage`, `wake.users.manage`

## Structure

- `index.php`: routeur API par `action`.
- `config\`: constantes runtime non secretes et lecture env.
- `controllers\`: validation payload, orchestration et reponses.
- `middlewares\`: auth session et permissions.
- `services\`: logique metier, DB, WOL, ping, Mercure.
- `utils\`: helpers request/response/log.
- `sql\`: migrations source, a ne pas deployer en runtime public.
- `logs\`: runtime local ignore par Git sauf `.gitignore`.

## Auth, permissions et DB

- Auth via session `sid`, tables `auth_sessions` et `users`.
- Refuser les comptes bannis si `users.is_banned` existe.
- Permissions via `Module-ShinedeCore-PHP`, deploye sous `P:\PROD\API\core`.
- Verifier les droits avec `hasPermission($userId, 'wake', '<permission>')`.
- Ne pas ecrire dans les tables d'un autre projet hors contrat documente.
- Les migrations Wake doivent rester non destructives.

## Temps reel

- Transport d'evenements: Mercure, optionnel et best-effort.
- Topics:
  - `https://api.shinederu.ch/wake/topics/devices`
  - `https://api.shinederu.ch/wake/topics/devices/{DEVICE_ID}`
- Evenements:
  - `wake.device.wake_requested`
  - `wake.device.wake_succeeded`
  - `wake.device.wake_failed`
- Les commandes critiques passent par HTTP, jamais par Mercure.
- L'etat doit pouvoir etre relu via `status` et `listDevices`.

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

Ne pas deployer:

- `.git`, `.github`
- `README.md`, `AGENTS.md`
- `.env.example`
- `sql\`
- tests, caches, brouillons, exports temporaires

Preserver les fichiers runtime existants si presents (`.env`, `logs\`). Ne jamais copier de secret depuis DEV vers PROD.
