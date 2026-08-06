import type { IFileAction, NewMenuEntry } from '@nextcloud/files'

import {
	addNewFileMenuEntry,
	DefaultType,
	FileType,
	NewMenuEntryCategory,
	Permission,
	registerFileAction,
} from '@nextcloud/files'
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import AppIcon from '../img/app.svg?raw'
import { startNewDocument } from './newDocument.ts'

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
	async handler(context, content) {
		const existingNames = content.map((node) => node.basename)
		await startNewDocument(context.path, existingNames)
	},
} satisfies NewMenuEntry

addNewFileMenuEntry(newButterflyDocument)
