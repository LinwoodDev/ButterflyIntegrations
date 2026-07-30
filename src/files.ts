import type { IFileAction, NewMenuEntry } from '@nextcloud/files'

import {
	addNewFileMenuEntry,
	DefaultType,
	FileType,
	getUniqueName,
	NewMenuEntryCategory,
	Permission,
	registerFileAction,
	validateFilename,
} from '@nextcloud/files'
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import AppIcon from '../img/app.svg?raw'

const butterflyAction = {
	id: 'butterfly-open',
	displayName: () => t('butterfly', 'Open in Butterfly'),
	iconSvgInline: () => AppIcon,
	enabled: ({ nodes }) => {
		if (nodes.length !== 1) {
			return false
		}

		const node = nodes[0]
		return node.type === FileType.File
			&& (node.permissions & Permission.READ) !== 0
			&& (
				node.mime === 'application/x-butterfly'
				|| node.basename.toLowerCase().endsWith('.bfly')
			)
	},
	async exec({ nodes }) {
		const node = nodes[0]
		if (!node) {
			return false
		}

		const url = new URL(generateUrl('/apps/butterfly/'), window.location.origin)
		url.searchParams.set('file', node.path)
		window.location.assign(url)
		return true
	},
	default: DefaultType.DEFAULT,
	order: -10,
} satisfies IFileAction

registerFileAction(butterflyAction)

const newButterflyDocument = {
	id: 'butterfly-new',
	category: NewMenuEntryCategory.CreateNew,
	displayName: t('butterfly', 'New Butterfly document'),
	iconSvgInline: AppIcon,
	order: 20,
	enabled: (context) => (context.permissions & Permission.CREATE) !== 0,
	handler(context, content) {
		const existingNames = content.map((node) => node.basename)
		const suggestedName = getUniqueName(
			t('butterfly', 'New Butterfly document.bfly'),
			existingNames,
		)
		const requestedName = window.prompt(
			t('butterfly', 'Name the new Butterfly document'),
			suggestedName,
		)?.trim()
		if (!requestedName) {
			return
		}

		const nameWithExtension = requestedName.toLowerCase().endsWith('.bfly')
			? requestedName
			: `${requestedName}.bfly`
		try {
			validateFilename(nameWithExtension)
		} catch {
			window.alert(t('butterfly', 'The document name is not valid.'))
			return
		}

		const fileName = getUniqueName(nameWithExtension, existingNames)
		const directory = context.path === '/'
			? ''
			: context.path.replace(/\/$/, '')
		const url = new URL(generateUrl('/apps/butterfly/'), window.location.origin)
		url.searchParams.set('file', `${directory}/${fileName}`)
		url.searchParams.set('create', '1')
		window.location.assign(url)
	},
} satisfies NewMenuEntry

addNewFileMenuEntry(newButterflyDocument)
