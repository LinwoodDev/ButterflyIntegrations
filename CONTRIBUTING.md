# Contributing to Butterfly for Nextcloud

Thank you for helping improve Butterfly's Nextcloud integration.

## Before you start

- Search the [issue tracker](https://github.com/LinwoodDev/ButterflyNextcloud/issues)
  before opening a new issue.
- Use the main [Butterfly repository](https://github.com/LinwoodDev/Butterfly)
  for editor or `.bfly` format issues that are not specific to Nextcloud.
- Follow the [Code of Conduct](CODE_OF_CONDUCT.md).
- Report vulnerabilities privately as described in [SECURITY.md](SECURITY.md).

## Development setup

You need Nextcloud 31–34, PHP 8.1 or newer, Composer, Node.js 24.11 or newer
in the 24.x release line, and npm 11. The included Docker Compose setup
provides a local Nextcloud 34 instance.

```bash
git clone https://github.com/LinwoodDev/ButterflyNextcloud.git
cd ButterflyNextcloud
nvm install
nvm use
npm ci
npm run build
composer install
chmod a+rx .
docker compose up -d
docker compose exec --user www-data nextcloud php occ app:enable butterfly
```

Open <http://localhost:8080> and sign in with `admin` / `admin`. Upload a
`.bfly` file or create one from the Files **New** menu.

## Making changes

- Keep the app compatible with every Nextcloud version declared in
  `appinfo/info.xml`.
- Keep document contents in Nextcloud; the iframe should receive and return
  bytes through Butterfly's supported embed protocol.
- Validate `postMessage` origins and iframe sources when changing embed
  messaging.
- Add or update tests for backend behavior changes.
- Document user-visible changes in `CHANGELOG.md`.

Run all checks before opening a pull request:

```bash
npm run check
composer lint
composer cs:check
composer psalm
composer test:unit
```

Use focused commits and explain the user-visible behavior and test coverage in
the pull request. Do not commit generated `node_modules`, `vendor`, `js`, or
`css` directories.

## Dependency updates

Use `npm install` when intentionally changing JavaScript constraints, and
commit both `package.json` and `package-lock.json`. Use `composer update` for
PHP tooling and commit the affected Composer lockfiles. Avoid forced upgrades
that violate the supported Node.js, PHP, or Nextcloud ranges.
