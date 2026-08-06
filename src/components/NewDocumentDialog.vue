<script setup lang="ts">
import type { ComponentPublicInstance } from 'vue'

import {
	getUniqueName,
	InvalidFilenameError,
	InvalidFilenameErrorReason,
	validateFilename,
} from '@nextcloud/files'
import { t } from '@nextcloud/l10n'
import { computed, nextTick, onMounted, ref, watchEffect } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcTextField from '@nextcloud/vue/components/NcTextField'

const props = defineProps<{
	defaultName: string
	otherNames?: string[]
}>()

const emit = defineEmits<{
	close: [name: string | null]
}>()

const name = ref(getUniqueName(props.defaultName, props.otherNames ?? []))
const nameInput = ref<ComponentPublicInstance>()
const form = ref<HTMLFormElement>()

const fileName = computed(() => {
	const requestedName = name.value.trim()
	return requestedName.toLowerCase().endsWith('.bfly')
		? requestedName
		: `${requestedName}.bfly`
})
const validity = computed(() => getFilenameValidity(fileName.value))

function getFilenameValidity(value: string): string {
	if (name.value.trim() === '') {
		return t('butterfly', 'Filename must not be empty.')
	}
	if ((props.otherNames ?? []).includes(value)) {
		return t('butterfly', 'This name is already in use.')
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

function focusName() {
	nextTick(() => {
		const input = nameInput.value?.$el.querySelector('input') as HTMLInputElement | undefined
		if (!input) {
			return
		}

		input.focus()
		const extensionLength = name.value.toLowerCase().endsWith('.bfly') ? 5 : 0
		input.setSelectionRange(0, name.value.length - extensionLength)
	})
}

function submit() {
	form.value?.requestSubmit()
}

watchEffect(() => {
	const input = nameInput.value?.$el.querySelector('input') as HTMLInputElement | undefined
	input?.setCustomValidity(validity.value)
})

onMounted(focusName)
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
			@submit.prevent="emit('close', fileName)">
			<NcTextField
				ref="nameInput"
				v-model="name"
				:error="validity !== ''"
				:helperText="validity"
				:label="t('butterfly', 'Document name')" />
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
