<script setup lang="ts">
import axios, { isAxiosError } from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'

interface ButterflyConfig {
	filePath: string | null
	embedUrl: string
	create: boolean
}

interface EmbedMessage {
	type?: string
	message?: unknown
}

const config = loadState<ButterflyConfig>('butterfly', 'config')
const frame = ref<HTMLIFrameElement>()
const loading = ref(Boolean(config.filePath))
const saving = ref(false)
const creating = ref(config.create)
const error = ref('')
const etag = ref('')
let pendingDocument: Uint8Array | null = null
let readinessAttempts = 0
let readinessTimer: ReturnType<typeof window.setTimeout> | undefined

const fileName = computed(() => config.filePath?.split('/').pop() ?? '')
const embedUrl = computed(() => {
	const url = new URL(config.embedUrl, window.location.href)
	url.searchParams.set('save', 'true')
	url.searchParams.set('editable', 'true')
	url.searchParams.set('language', 'user')
	if (fileName.value) {
		url.searchParams.set('fileName', fileName.value)
	}
	return url.toString()
})
const embedOrigin = computed(() => new URL(embedUrl.value).origin)

function sendToButterfly(type: string, message?: unknown) {
	frame.value?.contentWindow?.postMessage({ type, message }, embedOrigin.value)
}

function stopReadinessHandshake() {
	if (readinessTimer !== undefined) {
		window.clearTimeout(readinessTimer)
		readinessTimer = undefined
	}
}

function requestEmbedReadiness() {
	stopReadinessHandshake()
	if (!loading.value || (!creating.value && !pendingDocument)) {
		return
	}
	if (readinessAttempts >= 60) {
		pendingDocument = null
		loading.value = false
		error.value = t('butterfly', 'The embedded editor did not become ready.')
		return
	}

	readinessAttempts += 1
	sendToButterfly('getData')
	readinessTimer = window.setTimeout(requestEmbedReadiness, 500)
}

function documentBytes(message: unknown): Uint8Array | null {
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

async function loadDocument() {
	if (!config.filePath) {
		return
	}

	loading.value = true
	error.value = ''
	readinessAttempts = 0
	if (creating.value) {
		requestEmbedReadiness()
		return
	}

	try {
		const response = await axios.get<ArrayBuffer>(
			generateUrl('/apps/butterfly/api/document'),
			{
				params: { path: config.filePath },
				responseType: 'arraybuffer',
			},
		)
		etag.value = response.headers.etag ?? ''
		pendingDocument = new Uint8Array(response.data)
		// Use getData as a readiness handshake. The Flutter message listeners
		// are guaranteed to be registered before it can reply.
		requestEmbedReadiness()
	} catch (reason) {
		stopReadinessHandshake()
		pendingDocument = null
		loading.value = false
		error.value = requestError(reason, t('butterfly', 'Could not load the document.'))
	}
}

async function saveDocument(message: unknown): Promise<boolean> {
	if (!config.filePath || saving.value) {
		return false
	}

	const bytes = documentBytes(message)
	if (!bytes) {
		error.value = t('butterfly', 'Butterfly returned invalid document data.')
		return false
	}

	saving.value = true
	error.value = ''
	try {
		const uploadBytes = new Uint8Array(bytes.byteLength)
		uploadBytes.set(bytes)
		const response = await axios.put<{ etag: string }>(
			generateUrl(creating.value
				? '/apps/butterfly/api/document/create'
				: '/apps/butterfly/api/document'),
			uploadBytes,
			{
				params: { path: config.filePath },
				headers: {
					'Content-Type': 'application/x-butterfly',
					...(etag.value ? { 'If-Match': etag.value } : {}),
				},
			},
		)
		etag.value = response.data.etag
		creating.value = false
		loading.value = false
		saving.value = false
		return true
	} catch (reason) {
		loading.value = false
		saving.value = false
		error.value = requestError(reason, t('butterfly', 'Could not save the document.'))
		return false
	}
}

async function handleEmbedMessage(event: MessageEvent<EmbedMessage>) {
	if (
		event.origin !== embedOrigin.value
		|| event.source !== frame.value?.contentWindow
		|| typeof event.data?.type !== 'string'
	) {
		return
	}

	switch (event.data.type) {
		case 'getData':
			stopReadinessHandshake()
			if (creating.value) {
				await saveDocument(event.data.message)
			} else if (pendingDocument) {
				const document = pendingDocument
				pendingDocument = null
				// A plain number array is intentionally used here. Butterfly's
				// Dart bridge accepts Lists reliably across web renderers, while a
				// structured-cloned JavaScript Uint8Array can be ignored silently.
				sendToButterfly('setData', Array.from(document))
				loading.value = false
			}
			break
		case 'save':
			await saveDocument(event.data.message)
			break
		case 'exit':
			if (await saveDocument(event.data.message)) {
				openFiles()
			}
			break
		case 'error':
			stopReadinessHandshake()
			pendingDocument = null
			loading.value = false
			error.value = t('butterfly', 'The embedded editor reported an error.')
			break
	}
}

function requestError(reason: unknown, fallback: string): string {
	if (isAxiosError<{ message?: string }>(reason)) {
		return reason.response?.data?.message ?? fallback
	}
	return fallback
}

function openFiles() {
	const directory = config.filePath?.includes('/')
		? config.filePath.slice(0, config.filePath.lastIndexOf('/')) || '/'
		: '/'
	const filesUrl = new URL(generateUrl('/apps/files/files'), window.location.origin)
	filesUrl.searchParams.set('dir', directory)
	window.location.assign(filesUrl)
}

onMounted(() => window.addEventListener('message', handleEmbedMessage))
onBeforeUnmount(() => {
	stopReadinessHandshake()
	window.removeEventListener('message', handleEmbedMessage)
})
</script>

<template>
	<NcContent appName="butterfly">
		<NcAppContent :class="$style.content">
			<NcEmptyContent
				v-if="!config.filePath"
				:name="t('butterfly', 'Open a Butterfly document from Files')"
				:description="t('butterfly', 'Choose a .bfly file in Nextcloud Files and use Open in Butterfly.')">
				<template #action>
					<NcButton variant="primary" @click="openFiles">
						{{ t('butterfly', 'Open Files') }}
					</NcButton>
				</template>
			</NcEmptyContent>

			<template v-else>
				<NcNoteCard v-if="error" type="error" :class="$style.error">
					{{ error }}
				</NcNoteCard>

				<div :class="$style.editor">
					<NcLoadingIcon
						v-if="loading"
						:class="$style.loading"
						:size="48"
						:name="t('butterfly', 'Loading document')" />
					<iframe
						ref="frame"
						:class="$style.frame"
						:src="embedUrl"
						:title="t('butterfly', 'Butterfly editor for {file}', { file: fileName })"
						allow="clipboard-read; clipboard-write"
						@load="loadDocument" />
				</div>
			</template>
		</NcAppContent>
	</NcContent>
</template>

<style module>
:global(#butterfly) {
	width: 100%;
	height: var(--body-height, calc(100dvh - 50px));
	min-height: 0;
}

.content {
	display: flex;
	flex-direction: column;
	height: 100%;
	min-height: 0;
	overflow: hidden !important;
}

.error {
	flex: 0 0 auto;
	margin: 8px 12px;
}

.editor {
	position: relative;
	display: grid;
	flex: 1 1 auto;
	width: 100%;
	height: 100%;
	min-height: 0;
	overflow: hidden;
	place-items: center;
}

.loading {
	z-index: 1;
	grid-area: 1 / 1;
}

.frame {
	display: block;
	width: 100%;
	height: 100%;
	min-height: 0;
	border: 0;
	grid-area: 1 / 1;
}
</style>
