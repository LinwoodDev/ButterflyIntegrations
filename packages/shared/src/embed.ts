export const BUTTERFLY_EDITOR_MESSAGE_TYPES = [
	'error',
	'exit',
	'getData',
	'save',
] as const

export const BUTTERFLY_HOST_MESSAGE_TYPES = [
	'getData',
	'setData',
] as const

export type ButterflyEditorMessageType = typeof BUTTERFLY_EDITOR_MESSAGE_TYPES[number]
export type ButterflyHostMessageType = typeof BUTTERFLY_HOST_MESSAGE_TYPES[number]

export interface ButterflyEditorMessage {
	type: ButterflyEditorMessageType
	message?: unknown
}

export interface ButterflyHostMessage {
	type: ButterflyHostMessageType
	message?: unknown
}

export interface ButterflyEmbedOptions {
	editable?: boolean
	fileName?: string
	language?: string
	save?: boolean
}

export interface ButterflyEditorMessageEvent {
	data: unknown
	origin: string
	source: MessageEventSource | null
}

export function createButterflyEmbedUrl(
	baseUrl: string,
	currentUrl: string,
	options: ButterflyEmbedOptions = {},
): string {
	const url = new URL(baseUrl, currentUrl)
	if (options.save !== undefined) {
		url.searchParams.set('save', String(options.save))
	}
	if (options.editable !== undefined) {
		url.searchParams.set('editable', String(options.editable))
	}
	if (options.language) {
		url.searchParams.set('language', options.language)
	}
	if (options.fileName) {
		url.searchParams.set('fileName', options.fileName)
	}
	return url.toString()
}

export function parseButterflyEditorMessageEvent(
	event: ButterflyEditorMessageEvent,
	expectedOrigin: string,
	expectedSource: MessageEventSource | null,
): ButterflyEditorMessage | null {
	if (event.origin !== expectedOrigin || event.source !== expectedSource) {
		return null
	}
	if (event.data === null || typeof event.data !== 'object') {
		return null
	}

	const { type, message } = event.data as { type?: unknown, message?: unknown }
	if (
		typeof type !== 'string'
		|| !BUTTERFLY_EDITOR_MESSAGE_TYPES.some((allowedType) => allowedType === type)
	) {
		return null
	}

	return { type: type as ButterflyEditorMessageType, message }
}

export function postButterflyHostMessage(
	target: WindowProxy | null,
	targetOrigin: string,
	type: ButterflyHostMessageType,
	message?: unknown,
): void {
	target?.postMessage({ type, message } satisfies ButterflyHostMessage, targetOrigin)
}
