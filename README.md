# Butterfly Integrations

Official integrations that open and edit Butterfly documents in other apps.
This repository is a pnpm monorepo so browser-facing integrations can share
the embed protocol and document helpers without coupling their release cycles.

## Repository layout

```text
integrations/
  nextcloud/   # implemented and built in CI
  joplin/      # reserved; not built
  obsidian/    # reserved; not built
packages/
  shared/      # framework-neutral TypeScript helpers
```

See [the Nextcloud README](integrations/nextcloud/README.md) for its setup,
development, and Docker instructions.

## Development

```bash
corepack enable
pnpm install --frozen-lockfile
pnpm check
```

The root commands intentionally build only implemented integrations. Joplin
and Obsidian are placeholders and are not pnpm workspace packages yet.

## Releases and downloads

Each integration versions independently. A Nextcloud release is created from a
tag such as `nextcloud/v0.1.0`; its version must match both
`integrations/nextcloud/package.json` and
`integrations/nextcloud/appinfo/info.xml`. The generic version checker reads
those sources and the tag prefix from `integrations/nextcloud/integration.json`.
The matching version section from the integration's changelog becomes the body
of both its versioned release and, for stable versions, its stable release.

The release workflow maintains immutable version tags and one moving stable
tag:

```text
nextcloud/stable
nextcloud/v0.1.0
nextcloud/v0.2.0
nextcloud/v1.0.0
```

For example, publishing `nextcloud/v1.0.0` creates its immutable GitHub release,
moves `nextcloud/stable` to the same commit, and replaces the assets on the
stable GitHub release. Prereleases create only their versioned release.

Consumers can use this persistent download URL:

`https://github.com/LinwoodDev/ButterflyIntegrations/releases/download/nextcloud%2Fstable/butterfly-nextcloud.tar.gz`
