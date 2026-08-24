# Contributing to Butterfly Integrations

Thank you for helping improve Butterfly's integrations.

## Before you start

- Search the [issue tracker](https://github.com/LinwoodDev/ButterflyIntegrations/issues).
- Use the main [Butterfly repository](https://github.com/LinwoodDev/Butterfly)
  for editor or `.bfly` format issues that are not integration-specific.
- Follow the [Code of Conduct](CODE_OF_CONDUCT.md).
- Report vulnerabilities privately as described in [SECURITY.md](SECURITY.md).

## Development setup

The implemented Nextcloud integration needs Nextcloud 31–34, PHP 8.1 or newer,
Composer, Node.js 24.11 or newer in the 24.x release line, and pnpm 11.

```bash
git clone https://github.com/LinwoodDev/ButterflyIntegrations.git
cd ButterflyIntegrations
corepack enable
pnpm install --frozen-lockfile
pnpm build
composer --working-dir=integrations/nextcloud install
chmod a+rx integrations/nextcloud
docker compose -f integrations/nextcloud/docker-compose.yml up -d
docker compose -f integrations/nextcloud/docker-compose.yml exec --user www-data nextcloud php occ app:enable butterfly
```

Open <http://localhost:8080> and sign in with `admin` / `admin`.

## Making changes

- Put integration-specific code and tooling in `integrations/<name>`.
- Put framework-neutral code used by multiple integrations in `packages/`.
- Do not add placeholder integrations to workspace builds until they are
  implemented and have their own checks.
- Keep each integration's version and changelog within its directory.
- Add or update tests for behavior changes.

Run all checks before opening a pull request:

```bash
pnpm check
composer --working-dir=integrations/nextcloud lint
composer --working-dir=integrations/nextcloud cs:check
composer --working-dir=integrations/nextcloud psalm
composer --working-dir=integrations/nextcloud test:unit
```

## Releases

Integrations version independently. For Nextcloud, update its `package.json`,
`appinfo/info.xml`, and `CHANGELOG.md`, then create a tag named
`nextcloud/v<version>`. The release workflow verifies the versions, builds the
app, publishes the immutable version release, and updates the `nextcloud/stable`
tag and release assets when the version is not a prerelease.
Version sources and tag prefixes belong in each implemented integration's
`integration.json`; `pnpm check:versions` discovers and validates those files.
The same metadata declares the changelog path, and a release fails if the
current version has no matching `## [<version>]` section.

## Dependency updates

Use `corepack pnpm update` from the repository root and commit `pnpm-lock.yaml`.
Use `composer --working-dir=integrations/nextcloud update` for the Nextcloud PHP
tooling and commit the affected Composer lockfiles.
