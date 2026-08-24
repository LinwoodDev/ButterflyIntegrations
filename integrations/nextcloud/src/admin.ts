import axios, { isAxiosError } from '@nextcloud/axios'
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'

import './admin.scss'

interface HostingResponse {
	embedUrl: string
	version?: {
		packageName: string
		buildNumber: string
	}
}

const domainForm = document.querySelector<HTMLFormElement>('#butterfly-domain-form')
const bundleForm = document.querySelector<HTMLFormElement>('#butterfly-bundle-form')
const activeEditor = document.querySelector<HTMLElement>('#butterfly-active-editor')
const bundleVersion = document.querySelector<HTMLElement>('#butterfly-bundle-version')
const message = document.querySelector<HTMLElement>('#butterfly-settings-message')

function setMessage(text: string, error = false) {
	if (!message) {
		return
	}
	message.textContent = text
	message.classList.toggle('butterfly-settings-error', error)
}

function requestError(reason: unknown): string {
	if (isAxiosError<{ message?: string }>(reason)) {
		return reason.response?.data.message ?? t('butterfly', 'The settings could not be saved.')
	}
	return t('butterfly', 'The settings could not be saved.')
}

domainForm?.addEventListener('submit', async (event) => {
	event.preventDefault()
	setMessage(t('butterfly', 'Saving…'))
	const input = domainForm.elements.namedItem('domain') as HTMLInputElement
	try {
		const response = await axios.post<HostingResponse>(
			generateUrl('/apps/butterfly/admin/domain'),
			{ domain: input.value },
		)
		if (activeEditor) {
			activeEditor.textContent = response.data.embedUrl
		}
		setMessage(t('butterfly', 'Custom domain saved.'))
	} catch (reason) {
		setMessage(requestError(reason), true)
	}
})

bundleForm?.addEventListener('submit', async (event) => {
	event.preventDefault()
	setMessage(t('butterfly', 'Uploading and validating the editor…'))
	const formData = new FormData(bundleForm)
	try {
		const response = await axios.post<HostingResponse>(
			generateUrl('/apps/butterfly/admin/bundle'),
			formData,
		)
		if (activeEditor) {
			activeEditor.textContent = response.data.embedUrl
		}
		if (bundleVersion && response.data.version) {
			bundleVersion.textContent = t(
				'butterfly',
				'Installed package: %1$s, build %2$s',
				[response.data.version.packageName, response.data.version.buildNumber],
			)
		}
		const domainInput = domainForm?.elements.namedItem('domain') as HTMLInputElement | null
		if (domainInput) {
			domainInput.value = ''
		}
		setMessage(t('butterfly', 'The self-hosted editor is active.'))
	} catch (reason) {
		setMessage(requestError(reason), true)
	}
})
