import assert from 'node:assert/strict'
import test from 'node:test'

import { readIntegrationChangelogEntry } from './changelog-entry.mjs'

test('extracts only the matching integration changelog section', async () => {
	const entry = await readIntegrationChangelogEntry('nextcloud', '0.1.0')

	assert.match(entry, /^## \[0\.1\.0\] - 2026-08-24/m)
	assert.match(entry, /Open `\.bfly` documents directly from Nextcloud Files\./)
	assert.doesNotMatch(entry, /Unreleased/)
})

test('rejects releases without a matching changelog section', async () => {
	await assert.rejects(
		readIntegrationChangelogEntry('nextcloud', '9.9.9'),
		/No changelog entry found/,
	)
})
