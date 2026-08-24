import { BUTTERFLY_DOCUMENT_EXTENSION } from '@linwood/butterfly-integration-shared'
import { getUniqueName } from '@nextcloud/files'
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { spawnDialog } from '@nextcloud/vue/functions/dialog'
import NewDocumentDialog from './components/NewDocumentDialog.vue'

export async function startNewDocument(
	directory = '/',
	existingNames: string[] = [],
): Promise<void> {
	const dialogNames = existingNames.flatMap((name) => name.toLowerCase().endsWith(BUTTERFLY_DOCUMENT_EXTENSION)
		? [name, name.slice(0, -BUTTERFLY_DOCUMENT_EXTENSION.length)]
		: [name])
	const defaultName = getUniqueName(
		t('butterfly', 'New Butterfly document.bfly'),
		existingNames,
	)
	const requestedName = await spawnDialog(NewDocumentDialog, {
		defaultName,
		otherNames: dialogNames,
	})
	if (!requestedName) {
		return
	}
	const trimmedName = requestedName.trim()
	const fileName = trimmedName.toLowerCase().endsWith(BUTTERFLY_DOCUMENT_EXTENSION)
		? trimmedName
		: `${trimmedName}${BUTTERFLY_DOCUMENT_EXTENSION}`

	const normalizedDirectory = directory === '/'
		? ''
		: directory.replace(/\/$/, '')
	const url = new URL(generateUrl('/apps/butterfly/'), window.location.origin)
	url.searchParams.set('file', `${normalizedDirectory}/${fileName}`)
	url.searchParams.set('create', '1')
	window.location.assign(url)
}
