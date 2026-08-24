export const BUTTERFLY_DOCUMENT_EXTENSION = '.bfly'
export const BUTTERFLY_DOCUMENT_MIME_TYPE = 'application/x-butterfly'

export function toDocumentBytes(message: unknown): Uint8Array | null {
	if (message instanceof Uint8Array) {
		return message
	}
	if (message instanceof ArrayBuffer) {
		return new Uint8Array(message)
	}
	if (ArrayBuffer.isView(message)) {
		return new Uint8Array(message.buffer, message.byteOffset, message.byteLength)
	}
	if (Array.isArray(message)) {
		return Uint8Array.from(message)
	}
	if (message !== null && typeof message === 'object') {
		const values = Object.values(message)
		if (values.every((value) => Number.isInteger(value))) {
			return Uint8Array.from(values as number[])
		}
	}
	return null
}

export function toPostMessageBytes(bytes: Uint8Array): number[] {
	return Array.from(bytes)
}
