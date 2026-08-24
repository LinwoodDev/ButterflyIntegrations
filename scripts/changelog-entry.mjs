import { mkdir, readFile, writeFile } from 'node:fs/promises'
import { dirname, resolve, sep } from 'node:path'
import { pathToFileURL } from 'node:url'

function escapeRegularExpression(value) {
	return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

export async function readIntegrationChangelogEntry(
	integration,
	version,
	integrationsDirectory = resolve('integrations'),
) {
	const integrationDirectory = resolve(integrationsDirectory, integration)
	const metadataPath = resolve(integrationDirectory, 'integration.json')
	const metadata = JSON.parse(await readFile(metadataPath, 'utf8'))
	if (typeof metadata.changelogPath !== 'string' || !metadata.changelogPath) {
		throw new Error(`${metadataPath} must declare changelogPath`)
	}

	const changelogPath = resolve(integrationDirectory, metadata.changelogPath)
	if (!changelogPath.startsWith(`${integrationDirectory}${sep}`)) {
		throw new Error(`Changelog path ${metadata.changelogPath} escapes ${integrationDirectory}`)
	}
	const changelog = await readFile(changelogPath, 'utf8')
	const escapedVersion = escapeRegularExpression(version)
	const heading = new RegExp(
		`^##\\s+(?:\\[${escapedVersion}\\]|v?${escapedVersion})(?:\\s+-\\s+.+)?\\s*$`,
		'm',
	)
	const match = heading.exec(changelog)
	if (!match) {
		throw new Error(`No changelog entry found for ${integration} v${version} in ${metadata.changelogPath}`)
	}

	const followingText = changelog.slice(match.index + match[0].length)
	const nextHeadingIndex = followingText.search(/^##\s+/m)
	const entryEnd = nextHeadingIndex === -1
		? changelog.length
		: match.index + match[0].length + nextHeadingIndex
	return `${changelog.slice(match.index, entryEnd).trim()}\n`
}

async function main() {
	const [integration, version, outputPath] = process.argv.slice(2)
	if (!integration || !version) {
		throw new Error('Usage: changelog-entry.mjs <integration> <version> [output-path]')
	}
	const entry = await readIntegrationChangelogEntry(integration, version)
	if (!outputPath) {
		process.stdout.write(entry)
		return
	}
	const resolvedOutputPath = resolve(outputPath)
	await mkdir(dirname(resolvedOutputPath), { recursive: true })
	await writeFile(resolvedOutputPath, entry)
	console.log(`Release notes written to ${resolvedOutputPath}`)
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
	await main()
}
