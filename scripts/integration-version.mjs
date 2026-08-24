import { readdir, readFile } from 'node:fs/promises'
import { resolve, sep } from 'node:path'
import { pathToFileURL } from 'node:url'

import { readIntegrationChangelogEntry } from './changelog-entry.mjs'

const INTEGRATION_NAME = /^[a-z][a-z0-9-]*$/
const SEMANTIC_VERSION = /^\d+\.\d+\.\d+(?:-[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?$/

function escapeRegularExpression(value) {
	return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

function readJsonProperty(value, property) {
	return property.split('.').reduce((current, key) => current?.[key], value)
}

async function readVersion(source, integrationDirectory) {
	const sourcePath = resolve(integrationDirectory, source.path)
	if (!sourcePath.startsWith(`${integrationDirectory}${sep}`)) {
		throw new Error(`Version source ${source.path} escapes ${integrationDirectory}`)
	}
	const contents = await readFile(sourcePath, 'utf8')

	if (source.type === 'json') {
		const version = readJsonProperty(JSON.parse(contents), source.property)
		return typeof version === 'string' ? version : null
	}
	if (source.type === 'xml') {
		const element = escapeRegularExpression(source.element)
		return contents.match(new RegExp(`<${element}>([^<]+)</${element}>`))?.[1] ?? null
	}
	throw new Error(`Unsupported version source type ${source.type} in ${sourcePath}`)
}

export async function validateIntegrationVersion(
	integration,
	expectedVersion,
	integrationsDirectory = resolve('integrations'),
) {
	if (!INTEGRATION_NAME.test(integration)) {
		throw new Error(`Invalid integration name ${integration}`)
	}
	const integrationDirectory = resolve(integrationsDirectory, integration)
	const metadataPath = resolve(integrationDirectory, 'integration.json')
	const metadata = JSON.parse(await readFile(metadataPath, 'utf8'))

	if (metadata.name !== integration) {
		throw new Error(`${metadataPath} declares integration ${metadata.name}, expected ${integration}`)
	}
	if (!Array.isArray(metadata.versionSources) || metadata.versionSources.length === 0) {
		throw new Error(`${metadataPath} must declare at least one version source`)
	}
	if (typeof metadata.releaseTagPrefix !== 'string' || !metadata.releaseTagPrefix) {
		throw new Error(`${metadataPath} must declare releaseTagPrefix`)
	}
	if (metadata.releaseTagPrefix !== `${integration}/v`) {
		throw new Error(`${metadataPath} releaseTagPrefix must be ${integration}/v`)
	}

	const versions = await Promise.all(metadata.versionSources.map(async (source) => ({
		path: source.path,
		version: await readVersion(source, integrationDirectory),
	})))
	const missing = versions.find(({ version }) => version === null)
	if (missing) {
		throw new Error(`Could not read the ${integration} version from ${missing.path}`)
	}

	const version = versions[0].version
	if (!SEMANTIC_VERSION.test(version)) {
		throw new Error(`${integration} has invalid semantic version ${version}`)
	}
	const mismatch = versions.find((candidate) => candidate.version !== version)
	if (mismatch) {
		throw new Error(`${integration} version ${mismatch.version} in ${mismatch.path} does not match ${version}`)
	}
	if (expectedVersion && version !== expectedVersion) {
		throw new Error(`${integration} version ${version} does not match release version ${expectedVersion}`)
	}
	await readIntegrationChangelogEntry(integration, version, integrationsDirectory)

	return { integration, releaseTag: `${metadata.releaseTagPrefix}${version}`, version }
}

export async function integrationNames(integrationsDirectory = resolve('integrations')) {
	const entries = await readdir(integrationsDirectory, { withFileTypes: true })
	const names = []
	for (const entry of entries) {
		if (!entry.isDirectory()) {
			continue
		}
		try {
			await readFile(resolve(integrationsDirectory, entry.name, 'integration.json'))
			names.push(entry.name)
		} catch (error) {
			if (error.code !== 'ENOENT') {
				throw error
			}
		}
	}
	return names.sort()
}

async function main() {
	const [integration, expectedVersion] = process.argv.slice(2)
	const names = integration ? [integration] : await integrationNames()
	if (names.length === 0) {
		throw new Error('No integration metadata files found')
	}
	for (const name of names) {
		const result = await validateIntegrationVersion(name, expectedVersion)
		console.log(`${result.integration} version ${result.version} is consistent (${result.releaseTag})`)
	}
}

if (import.meta.url === pathToFileURL(process.argv[1]).href) {
	await main()
}
