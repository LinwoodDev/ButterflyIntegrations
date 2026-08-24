import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import test from 'node:test'

import { MINIMUM_BUTTERFLY_BUILD_NUMBER } from '../packages/shared/src/version.ts'

test('shared and Nextcloud validators require the same minimum build number', async () => {
	const validator = await readFile(
		'integrations/nextcloud/lib/Service/BundleValidator.php',
		'utf8',
	)
	const phpMinimum = validator.match(/public const MINIMUM_BUILD_NUMBER = (\d+);/)?.[1]

	assert.equal(Number(phpMinimum), MINIMUM_BUTTERFLY_BUILD_NUMBER)
})
