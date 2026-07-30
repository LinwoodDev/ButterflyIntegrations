# Butterfly for Nextcloud

This Nextcloud app opens and edits `.bfly` files with Butterfly's supported
embed API. Documents stay in Nextcloud and are loaded into the editor only
while they are open. The app is based on the official
[Nextcloud app template](https://github.com/nextcloud/app_template) at commit
`a75bc6ed28ecff0f043f0b36e1b6f46d2d798976`.

## Requirements

- Nextcloud 31–34
- PHP 8.1 or newer
- Node.js 24.11 or newer in the 24.x release line, and npm 11, for building
  the frontend
- Composer for PHP development and tests

## Build

```bash
nvm install
nvm use
npm ci
npm run build
composer install
```

The generated `js/` and `css/` directories are ignored by Git and must be
present in an installed or packaged app.

## Test with Docker

Build the frontend, then start the included development instance:

```bash
npm ci
npm run build
chmod a+rx .
docker compose up -d
docker compose exec --user www-data nextcloud php occ app:enable butterfly
```

Open <http://localhost:8080> and sign in with `admin` / `admin`. Upload a
`.bfly` document, then click it or choose **Open in Butterfly** from its action
menu. You can also create one from **New → New Butterfly document**.

Stop the instance with:

```bash
docker compose down
```

The Docker volume keeps the test instance between runs. Use
`docker compose down --volumes` only when you intentionally want a clean
instance.

If `occ app:enable butterfly` reports that it cannot write into the apps
directory, make sure this app directory is traversable by the container and
recreate it so Docker applies the SELinux mount label:

```bash
chmod a+rx .
docker compose up -d --force-recreate
docker compose exec --user www-data nextcloud php occ app:enable butterfly
```

The Butterfly bind mount is intentionally read-only. Nextcloud does not need
to write to an app that is already present; that error usually means it could
not discover the mounted app and attempted to install it instead.

## Checks

```bash
npm run check
composer lint
composer cs:check
composer psalm
composer test:unit
```

See [CONTRIBUTING.md](CONTRIBUTING.md) for the complete development workflow.

## How documents are handled

The app adds a default Files action for `.bfly` documents. Its authenticated
controller reads the file from the current user's storage, sends the bytes to
the Butterfly iframe with `postMessage`, and writes bytes back when Butterfly
emits `save` or `exit`. Saves include the loaded ETag, so an external change is
reported instead of overwritten.

The editor iframe currently uses `https://preview.butterfly.linwood.dev/embed`.
The Nextcloud file name is passed through Butterfly's visual-only `fileName`
embed option. Butterfly provides the title and exit controls; exiting saves
the document and returns to its directory in Nextcloud Files. Only messages
from that exact origin and iframe window are accepted.

## Contributing and security

Contributions are welcome. Read [CONTRIBUTING.md](CONTRIBUTING.md) before
opening a pull request. Please report vulnerabilities according to
[SECURITY.md](SECURITY.md), rather than through a public issue.
