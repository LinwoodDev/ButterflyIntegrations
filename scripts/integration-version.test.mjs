import assert from 'node:assert/strict'
import { mkdir, mkdtemp, rm, writeFile } from 'node:fs/promises'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import test from 'node:test'

import { integrationNames, validateIntegrationVersion } from './integration-version.mjs'

test('discovers only integrations with release metadata', async () => {
	assert.deepEqual(await integrationNames(), ['nextcloud'])
})

test('validates every declared version source and builds the release tag', async () => {
	assert.deepEqual(await validateIntegrationVersion('nextcloud'), {
		integration: 'nextcloud',
		releaseTag: 'nextcloud/v0.1.0',
		version: '0.1.0',
	})
})

test('rejects a release version that differs from integration metadata', async () => {
	await assert.rejects(
		validateIntegrationVersion('nextcloud', '9.9.9'),
		/does not match release version/,
	)
})

test('rejects integrations whose declared version sources disagree', async (context) => {
	const integrationsDirectory = await mkdtemp(join(tmpdir(), 'butterfly-integration-versions-'))
	context.after(() => rm(integrationsDirectory, { force: true, recursive: true }))
	const integrationDirectory = join(integrationsDirectory, 'example')
	await mkdir(join(integrationDirectory, 'appinfo'), { recursive: true })
	await writeFile(join(integrationDirectory, 'integration.json'), JSON.stringify({
		changelogPath: 'CHANGELOG.md',
		name: 'example',
		releaseTagPrefix: 'example/v',
		versionSources: [
			{ path: 'package.json', property: 'version', type: 'json' },
			{ element: 'version', path: 'appinfo/info.xml', type: 'xml' },
		],
	}))
	await writeFile(join(integrationDirectory, 'package.json'), '{"version":"1.0.0"}')
	await writeFile(join(integrationDirectory, 'appinfo/info.xml'), '<info><version>2.0.0</version></info>')
	await writeFile(join(integrationDirectory, 'CHANGELOG.md'), '## [1.0.0]\n\n- Initial release.\n')

	await assert.rejects(
		validateIntegrationVersion('example', undefined, integrationsDirectory),
		/does not match/,
	)
})
