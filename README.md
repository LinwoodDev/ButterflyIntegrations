<div align="center">

<img src="https://raw.githubusercontent.com/LinwoodDev/butterfly/develop/app/images/logo.png" width="350px" alt="Butterfly logo" />

# Butterfly Integrations

> 🔌 Bring Butterfly documents into the tools you already use 🔌

[![GitHub License](https://img.shields.io/github/license/LinwoodDev/ButterflyIntegrations?color=EBB733&style=for-the-badge)](LICENSE)
[![GitHub Repo stars](https://img.shields.io/github/stars/LinwoodDev/ButterflyIntegrations?color=EBB733&style=for-the-badge&logo=github&logoColor=EBB733)](https://github.com/LinwoodDev/ButterflyIntegrations)

</div>

<p align="center">
    <a href="https://butterfly.linwood.dev"><b>Butterfly</b></a> •
    <a href="https://github.com/LinwoodDev/ButterflyIntegrations/releases"><b>Downloads</b></a> •
    <a href="https://go.linwood.dev/matrix"><b>Matrix</b></a> •
    <a href="https://go.linwood.dev/discord"><b>Discord</b></a> •
    <a href="https://linwood.dev"><b>Linwood</b></a> •
    <a href="CONTRIBUTING.md"><b>Contribute</b></a>
</p>

---

Butterfly Integrations connects [Butterfly](https://butterfly.linwood.dev) to
other applications while keeping your `.bfly` documents in the host you
already use. Integrations share a small, typed embed protocol package but are
built, versioned, and released independently.

## Integrations

### ☁️ Nextcloud

Open, create, edit, and save Butterfly documents directly from Nextcloud Files.
The integration protects saves with ETags, supports custom or self-hosted
Butterfly web builds, and validates every iframe message against its expected
window and origin.

[**Learn more**](integrations/nextcloud/README.md) ·
[**Download stable `.tar.gz`**](https://github.com/LinwoodDev/ButterflyIntegrations/releases/download/nextcloud%2Fstable/butterfly-nextcloud.tar.gz) ·
[**Download stable `.zip`**](https://github.com/LinwoodDev/ButterflyIntegrations/releases/download/nextcloud%2Fstable/butterfly-nextcloud.zip)

### 📝 Joplin

Planned. The directory is reserved, but the integration is not implemented or
built yet.

### 💎 Obsidian

Planned. The directory is reserved, but the integration is not implemented or
built yet.

---

## Repository

```text
integrations/
  nextcloud/   # implemented and built in CI
  joplin/      # reserved; not built
  obsidian/    # reserved; not built
packages/
  shared/      # framework-neutral TypeScript helpers
```

The shared package contains the Butterfly embed message types, trusted event
validation, document conversion helpers, and `version.json` validation used by
browser-facing integrations.

## Development

Install Node.js 24 and pnpm 11, then run:

```bash
corepack enable
pnpm install --frozen-lockfile
pnpm check
```

See the [Nextcloud integration guide](integrations/nextcloud/README.md) for PHP,
Composer, and Docker setup. Joplin and Obsidian are intentionally excluded from
the workspace until their implementations exist.

## Releases

Each integration has its own version namespace. For Nextcloud:

- `nextcloud/v1.0.0` is an immutable version release.
- `nextcloud/stable` moves to the newest stable version and keeps persistent
  asset URLs.
- Prereleases create a versioned release without changing `nextcloud/stable`.
- The matching integration changelog entry becomes the GitHub release body.

Versions, tag prefixes, and changelog locations are declared in each
implemented integration's `integration.json`.

---

## Contributing

We are happy to see that you are interested in improving Butterfly's
integrations. To get started, visit [the contributing guide](CONTRIBUTING.md).

Please report security issues privately according to the
[security policy](SECURITY.md).

## License

The code is open source and licensed under the
[Apache-2.0](LICENSE) license.
