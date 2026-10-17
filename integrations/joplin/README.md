# Butterfly for Joplin

This Joplin plugin opens and edits `.bfly` files with Butterfly's supported
embed API. Documents are loaded into the editor only while they are open.
The plugin is based on the official [Joplin plugin template](https://github.com/laurent22/joplin/commits/dev/packages/generator-joplin/generators/app/templates) at commit
`e58fcf9402973d8b9f5ae7b340b9b426cf07988e`.

## Requirements

- Joplin 3.6 or newer

## Build

```bash
corepack enable
pnpm install --frozen-lockfile
cd integrations/joplin
pnpm run dist
```

This creates the plugin in `publish/dev.linwood.butterfly-joplin.jpl`

## Checks

```bash
pnpm --filter @linwood/butterfly-integration-shared check
pnpm check:joplin
```

See [the repository contribution guide](../../CONTRIBUTING.md) for the complete
development workflow.

## How documents are handled

The plugin adds an action for clicking Markdown image-links in the form of 
!\[bfly@<documentId>](previewId). Doing so will open a joplin panel. 
It reads the file from the joplin resource directory, sends the bytes to the
Butterfly iframe with `postMessage`, and writes bytes back when Butterfly emits `save`
by storing them in the temporary directory  of the os under `joplin-butterfly-plugin/`
and then uploading them to the joplin resource directory.

By default, the editor iframe uses `https://preview.butterfly.linwood.dev/embed`.
The user may configure another Butterfly origin under **Tools → Options → Butterfly**. 

Exiting does not save the document but closes the joplin panel containing the iframe.
Only messages from that exact origin and iframe window are accepted. 

## Contributing and security

Contributions are welcome. Read [CONTRIBUTING.md](../../CONTRIBUTING.md) before
opening a pull request. Please report vulnerabilities according to
[SECURITY.md](../../SECURITY.md), rather than through a public issue.
