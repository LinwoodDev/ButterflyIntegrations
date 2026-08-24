export const BUTTERFLY_PACKAGE_NAME = 'butterfly'
export const MINIMUM_BUTTERFLY_BUILD_NUMBER = 193

export interface ButterflyVersionMetadata {
	build_number: string
	package_name: typeof BUTTERFLY_PACKAGE_NAME
}

export class ButterflyVersionValidationError extends Error {
	constructor(message: string, options?: ErrorOptions) {
		super(message, options)
		this.name = 'ButterflyVersionValidationError'
	}
}

export function validateButterflyVersionMetadata(value: unknown): ButterflyVersionMetadata {
	if (
		value === null
		|| typeof value !== 'object'
		|| !('package_name' in value)
		|| value.package_name !== BUTTERFLY_PACKAGE_NAME
	) {
		throw new ButterflyVersionValidationError(
			`version.json must have package_name set to "${BUTTERFLY_PACKAGE_NAME}".`,
		)
	}

	if (
		!('build_number' in value)
		|| typeof value.build_number !== 'string'
		|| !/^\d+$/.test(value.build_number)
		|| BigInt(value.build_number) < BigInt(MINIMUM_BUTTERFLY_BUILD_NUMBER)
	) {
		throw new ButterflyVersionValidationError(
			`version.json must have a string build_number of "${MINIMUM_BUTTERFLY_BUILD_NUMBER}" or higher.`,
		)
	}

	return {
		build_number: value.build_number,
		package_name: BUTTERFLY_PACKAGE_NAME,
	}
}

export function parseButterflyVersionJson(contents: string): ButterflyVersionMetadata {
	let value: unknown
	try {
		value = JSON.parse(contents)
	} catch (error) {
		throw new ButterflyVersionValidationError('version.json is not valid JSON.', { cause: error })
	}
	return validateButterflyVersionMetadata(value)
}
