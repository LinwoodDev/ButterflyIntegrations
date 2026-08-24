import assert from 'node:assert/strict'
import test from 'node:test'

import {
	ButterflyVersionValidationError,
	MINIMUM_BUTTERFLY_BUILD_NUMBER,
	parseButterflyVersionJson,
	validateButterflyVersionMetadata,
} from '../src/version.ts'

test('accepts the minimum and newer string build numbers', () => {
	assert.deepEqual(validateButterflyVersionMetadata({
		build_number: String(MINIMUM_BUTTERFLY_BUILD_NUMBER),
		package_name: 'butterfly',
	}), {
		build_number: '193',
		package_name: 'butterfly',
	})
	assert.equal(
		validateButterflyVersionMetadata({
			build_number: '999999999999999999999999',
			package_name: 'butterfly',
		}).build_number,
		'999999999999999999999999',
	)
})

test('rejects wrong packages, numeric build numbers, and old builds', () => {
	for (const metadata of [
		{ build_number: '193', package_name: 'other' },
		{ build_number: 193, package_name: 'butterfly' },
		{ build_number: '192', package_name: 'butterfly' },
		{ build_number: 'not-a-number', package_name: 'butterfly' },
	]) {
		assert.throws(
			() => validateButterflyVersionMetadata(metadata),
			ButterflyVersionValidationError,
		)
	}
})

test('parses and validates version.json content', () => {
	assert.deepEqual(
		parseButterflyVersionJson('{"package_name":"butterfly","build_number":"193"}'),
		{ build_number: '193', package_name: 'butterfly' },
	)
	assert.throws(
		() => parseButterflyVersionJson('{invalid'),
		/version\.json is not valid JSON\./,
	)
})
