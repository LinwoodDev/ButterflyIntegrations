# Butterfly for Nextcloud

This Nextcloud app opens and edits `.bfly` files with Butterfly's supported
embed API. Documents stay in Nextcloud and are loaded into the editor only
while they are open. The app is based on the official
[Nextcloud app template](https://github.com/nextcloud/app_template) at commit
`a75bc6ed28ecff0f043f0b36e1b6f46d2d798976`.

## Requirements

- Nextcloud 31–34
- PHP 8.1 or newer
- Node.js 24.11 or newer in the 24.x release line, with Corepack for pnpm 11,
  for building
  the frontend
- Composer for PHP development and tests

## Build

```bash
nvm install
nvm use
corepack enable
pnpm install --frozen-lockfile
pnpm --filter @linwood/butterfly-nextcloud build
composer --working-dir=integrations/nextcloud install
```

The generated `integrations/nextcloud/js/` and `integrations/nextcloud/css/`
directories are ignored by Git and must be
present in an installed or packaged app.

## Test with Docker

Build the frontend, then start the included development instance:

```bash
corepack enable
pnpm install --frozen-lockfile
pnpm --filter @linwood/butterfly-nextcloud build
chmod a+rx integrations/nextcloud
docker compose -f integrations/nextcloud/docker-compose.yml up -d
docker compose -f integrations/nextcloud/docker-compose.yml exec --user www-data nextcloud php occ app:enable butterfly
```

Open <http://localhost:8080> and sign in with `admin` / `admin`. Upload a
`.bfly` document, then click it or choose **Open in Butterfly** from its action
menu. You can also create one from **New → New Butterfly document**.

Stop the instance with:

```bash
docker compose -f integrations/nextcloud/docker-compose.yml down
```

The Docker volume keeps the test instance between runs. Use
`docker compose -f integrations/nextcloud/docker-compose.yml down --volumes`
only when you intentionally want a clean
instance.

If `occ app:enable butterfly` reports that it cannot write into the apps
directory, make sure this app directory is traversable by the container and
recreate it so Docker applies the SELinux mount label:

```bash
chmod a+rx integrations/nextcloud
docker compose -f integrations/nextcloud/docker-compose.yml up -d --force-recreate
docker compose -f integrations/nextcloud/docker-compose.yml exec --user www-data nextcloud php occ app:enable butterfly
```

The Butterfly bind mount is intentionally read-only. Nextcloud does not need
to write to an app that is already present; that error usually means it could
not discover the mounted app and attempted to install it instead.

## Checks

```bash
pnpm check
composer --working-dir=integrations/nextcloud lint
composer --working-dir=integrations/nextcloud cs:check
composer --working-dir=integrations/nextcloud psalm
composer --working-dir=integrations/nextcloud test:unit
```

See [the repository contribution guide](../../CONTRIBUTING.md) for the complete
development workflow.

## How documents are handled

The app adds a default Files action for `.bfly` documents. Its authenticated
controller reads the file from the current user's storage, sends the bytes to
the Butterfly iframe with `postMessage`, and writes bytes back when Butterfly
emits `save` or `exit`. Saves include the loaded ETag, so an external change is
reported instead of overwritten.

By default, the editor iframe uses `https://preview.butterfly.linwood.dev/embed`.
An administrator can configure another Butterfly origin or upload a Butterfly
web-build ZIP under **Administration settings → Additional settings**. Uploaded
builds are stored in Nextcloud app data and served by this app. The archive is
accepted only when it has an `index.html` next to exactly one `version.json`,
with `package_name` set to `"butterfly"` and a string `build_number` of `"193"`
or higher. Uploading a valid build activates it and clears the custom-domain
override.

The Nextcloud file name is passed through Butterfly's visual-only `fileName`
embed option. Butterfly provides the title and exit controls; exiting saves
the document and returns to its directory in Nextcloud Files. Only messages
from that exact origin and iframe window are accepted.

## Contributing and security

Contributions are welcome. Read [CONTRIBUTING.md](../../CONTRIBUTING.md) before
opening a pull request. Please report vulnerabilities according to
[SECURITY.md](../../SECURITY.md), rather than through a public issue.
