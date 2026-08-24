import assert from 'node:assert/strict'
import test from 'node:test'

import {
	parseButterflyEditorMessageEvent,
	postButterflyHostMessage,
} from '../src/embed.ts'

test('parses messages only from the expected editor window and origin', () => {
	const source = {}
	const validEvent = {
		data: { type: 'save', message: [1, 2, 3] },
		origin: 'https://butterfly.example.com',
		source,
	}

	assert.deepEqual(
		parseButterflyEditorMessageEvent(validEvent, validEvent.origin, source),
		validEvent.data,
	)
	assert.equal(
		parseButterflyEditorMessageEvent(validEvent, 'https://other.example.com', source),
		null,
	)
	assert.equal(
		parseButterflyEditorMessageEvent(validEvent, validEvent.origin, {}),
		null,
	)
})

test('rejects malformed and unknown editor messages', () => {
	const source = {}
	const expectedOrigin = 'https://butterfly.example.com'

	for (const data of [null, 'save', {}, { type: 'setData' }, { type: 'unknown' }]) {
		assert.equal(
			parseButterflyEditorMessageEvent({ data, origin: expectedOrigin, source }, expectedOrigin, source),
			null,
		)
	}
})

test('posts typed host messages to the configured origin', () => {
	const calls = []
	const target = {
		postMessage(message, origin) {
			calls.push({ message, origin })
		},
	}

	postButterflyHostMessage(target, 'https://butterfly.example.com', 'setData', [1, 2, 3])
	assert.deepEqual(calls, [{
		message: { type: 'setData', message: [1, 2, 3] },
		origin: 'https://butterfly.example.com',
	}])
})
