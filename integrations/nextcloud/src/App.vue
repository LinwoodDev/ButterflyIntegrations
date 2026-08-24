<script setup lang="ts">
import type { ButterflyHostMessageType } from '@linwood/butterfly-integration-shared'

import {
	createButterflyEmbedUrl,
	parseButterflyEditorMessageEvent,
	postButterflyHostMessage,
	toDocumentBytes,
	toPostMessageBytes,
} from '@linwood/butterfly-integration-shared'
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
import AppIcon from '../img/app.svg?url'
import { startNewDocument } from './newDocument.ts'

interface ButterflyConfig {
	filePath: string | null
	embedUrl: string
	create: boolean
	existingRootNames: string[]
}

const config = loadState<ButterflyConfig>('butterfly', 'config')
const frame = ref<HTMLIFrameElement>()
const loading = ref(Boolean(config.filePath))
const saving = ref(false)
const creating = ref(config.create)
const openingCreateDialog = ref(false)
const error = ref('')
const etag = ref('')
let pendingDocument: Uint8Array | null = null
let readinessAttempts = 0
let readinessTimer: ReturnType<typeof window.setTimeout> | undefined

const fileName = computed(() => config.filePath?.split('/').pop() ?? '')
const embedUrl = computed(() => createButterflyEmbedUrl(
	config.embedUrl,
	window.location.href,
	{
		editable: true,
		fileName: fileName.value,
		language: 'user',
		save: true,
	},
))
const embedOrigin = computed(() => new URL(embedUrl.value).origin)

function sendToButterfly(type: ButterflyHostMessageType, message?: unknown) {
	postButterflyHostMessage(frame.value?.contentWindow ?? null, embedOrigin.value, type, message)
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

	const bytes = toDocumentBytes(message)
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

async function handleEmbedMessage(event: MessageEvent<unknown>) {
	const message = parseButterflyEditorMessageEvent(
		event,
		embedOrigin.value,
		frame.value?.contentWindow ?? null,
	)
	if (!message) {
		return
	}

	switch (message.type) {
		case 'getData':
			stopReadinessHandshake()
			if (creating.value) {
				await saveDocument(message.message)
			} else if (pendingDocument) {
				const document = pendingDocument
				pendingDocument = null
				// A plain number array is intentionally used here. Butterfly's
				// Dart bridge accepts Lists reliably across web renderers, while a
				// structured-cloned JavaScript Uint8Array can be ignored silently.
				sendToButterfly('setData', toPostMessageBytes(document))
				loading.value = false
			}
			break
		case 'save':
			await saveDocument(message.message)
			break
		case 'exit':
			if (await saveDocument(message.message)) {
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

async function createDocument() {
	openingCreateDialog.value = true
	try {
		await startNewDocument('/', config.existingRootNames)
	} finally {
		openingCreateDialog.value = false
	}
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
			<div v-if="!config.filePath" :class="$style.landing">
				<NcEmptyContent
					:name="t('butterfly', 'Create, draw, and organize with Butterfly')"
					:description="t('butterfly', 'Edit Butterfly documents in your browser while keeping them in Nextcloud.')">
					<template #icon>
						<img
							:class="$style.appIcon"
							:src="AppIcon"
							alt="">
					</template>
					<template #action>
						<div :class="$style.actions">
							<NcButton
								variant="primary"
								:disabled="openingCreateDialog"
								@click="createDocument">
								{{ t('butterfly', 'New document') }}
							</NcButton>
							<NcButton variant="secondary" @click="openFiles">
								{{ t('butterfly', 'Open from Files') }}
							</NcButton>
						</div>
					</template>
				</NcEmptyContent>

				<section :class="$style.features" :aria-label="t('butterfly', 'How Butterfly works with Nextcloud')">
					<article :class="$style.feature">
						<span :class="$style.step">1</span>
						<h2>{{ t('butterfly', 'Create or open') }}</h2>
						<p>{{ t('butterfly', 'Start a document here, or open any .bfly file from Nextcloud Files.') }}</p>
					</article>
					<article :class="$style.feature">
						<span :class="$style.step">2</span>
						<h2>{{ t('butterfly', 'Edit in your browser') }}</h2>
						<p>{{ t('butterfly', 'Use the full Butterfly editor without downloading and re-uploading your work.') }}</p>
					</article>
					<article :class="$style.feature">
						<span :class="$style.step">3</span>
						<h2>{{ t('butterfly', 'Keep it in Nextcloud') }}</h2>
						<p>{{ t('butterfly', 'Changes save back to Files with protection against overwriting a newer version.') }}</p>
					</article>
				</section>
			</div>

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

.landing {
	display: flex;
	flex: 1 1 auto;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	gap: 40px;
	width: 100%;
	padding: 48px 24px;
	overflow: auto;
}

.appIcon {
	display: block;
	width: 96px;
	height: 96px;
}

.actions {
	display: flex;
	flex-wrap: wrap;
	justify-content: center;
	gap: 8px;
}

.features {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 16px;
	width: min(900px, 100%);
}

.feature {
	padding: 20px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
}

.feature h2 {
	margin: 12px 0 6px;
	font-size: 1.15rem;
}

.feature p {
	margin: 0;
	color: var(--color-text-maxcontrast);
	line-height: 1.5;
}

.step {
	display: inline-grid;
	width: 32px;
	height: 32px;
	border-radius: 50%;
	background: var(--color-primary-element-light);
	color: var(--color-primary-element-light-text);
	font-weight: 700;
	place-items: center;
}

@media (max-width: 700px) {
	.landing {
		justify-content: flex-start;
		padding: 32px 16px;
	}

	.features {
		grid-template-columns: 1fr;
	}
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
