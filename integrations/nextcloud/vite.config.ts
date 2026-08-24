import { createAppConfig } from '@nextcloud/vite-config'
import { join, resolve } from 'path'

export default createAppConfig(
	{
		admin: resolve(join('src', 'admin.ts')),
		main: resolve(join('src', 'main.ts')),
		files: resolve(join('src', 'files.ts')),
	},
	{
		createEmptyCSSEntryPoints: true,
		extractLicenseInformation: true,
		thirdPartyLicense: false,
	},
)
