<script setup lang="ts">
/**
 * Mirrors Nextcloud Files' NewNodeDialog behavior. The upstream component is
 * private to the Files app, so it cannot be imported by installed apps.
 *
 * @see https://github.com/nextcloud/server/blob/stable34/apps/files/src/components/NewNodeDialog.vue
 */
import type { ComponentPublicInstance } from 'vue'

import {
	getUniqueName,
	InvalidFilenameError,
	InvalidFilenameErrorReason,
	validateFilename,
} from '@nextcloud/files'
import { t } from '@nextcloud/l10n'
import { computed, nextTick, onMounted, ref, watch, watchEffect } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextField from '@nextcloud/vue/components/NcTextField'

const props = defineProps<{
	defaultName: string
	otherNames?: string[]
}>()

const emit = defineEmits<{
	close: [name: string | null]
}>()

const localDefaultName = ref(props.defaultName)
const nameInput = ref<ComponentPublicInstance>()
const form = ref<HTMLFormElement>()
const validity = ref('')

const isHiddenFileName = computed(() => localDefaultName.value.trim().startsWith('.'))

function getFilenameValidity(value: string): string {
	if (value.trim() === '') {
		return t('butterfly', 'Filename must not be empty.')
	}

	try {
		validateFilename(value)
		return ''
	} catch (error) {
		if (!(error instanceof InvalidFilenameError)) {
			throw error
		}

		switch (error.reason) {
			case InvalidFilenameErrorReason.Character:
				return t('butterfly', '"{char}" is not allowed inside a filename.', {
					char: error.segment,
				})
			case InvalidFilenameErrorReason.ReservedName:
				return t('butterfly', '"{segment}" is a reserved name and not allowed for filenames.', {
					segment: error.segment,
				})
			case InvalidFilenameErrorReason.Extension:
				return t('butterfly', 'Filenames must not end with "{extension}".', {
					extension: error.segment,
				})
			default:
				return t('butterfly', 'Invalid filename.')
		}
	}
}

function focusInput() {
	nextTick(() => {
		const input = nameInput.value?.$el.querySelector('input') as HTMLInputElement | undefined
		if (!input) {
			return
		}

		input.focus()
		const extensionIndex = localDefaultName.value.lastIndexOf('.')
		const basenameLength = extensionIndex > 0
			? extensionIndex
			: localDefaultName.value.length
		input.setSelectionRange(0, basenameLength)
	})
}

function submit() {
	form.value?.requestSubmit()
}

watch(() => [props.defaultName, props.otherNames], () => {
	localDefaultName.value = getUniqueName(props.defaultName, props.otherNames ?? []).trim()
})

watchEffect(() => {
	const trimmedName = localDefaultName.value.trim()
	validity.value = (props.otherNames ?? []).includes(trimmedName)
		? t('butterfly', 'This name is already in use.')
		: getFilenameValidity(trimmedName)
	const input = nameInput.value?.$el.querySelector('input') as HTMLInputElement | undefined
	if (input) {
		input.setCustomValidity(validity.value)
		input.reportValidity()
	}
})

onMounted(() => {
	localDefaultName.value = getUniqueName(
		localDefaultName.value,
		props.otherNames ?? [],
	).trim()
	focusInput()
})
</script>

<template>
	<NcDialog
		:name="t('butterfly', 'Create a new Butterfly document')"
		closeOnClickOutside
		outTransition
		@update:open="emit('close', null)">
		<form
			ref="form"
			:class="$style.form"
			@submit.prevent="emit('close', localDefaultName)">
			<NcTextField
				ref="nameInput"
				v-model="localDefaultName"
				:error="validity !== ''"
				:helperText="validity"
				:label="t('butterfly', 'Document name')" />

			<NcNoteCard
				v-if="isHiddenFileName"
				type="warning"
				:text="t('butterfly', 'Files starting with a dot are hidden by default')" />
		</form>

		<template #actions>
			<NcButton
				variant="primary"
				:disabled="validity !== ''"
				@click="submit">
				{{ t('butterfly', 'Create') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<style module>
.form {
	min-height: calc(2 * var(--default-clickable-area));
}
</style>
