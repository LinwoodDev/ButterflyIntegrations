import { recommended } from '@nextcloud/eslint-config'

export default [
	...recommended,
	{
		rules: {
			'jsdoc/require-jsdoc': 'off',
			'vue/first-attribute-linebreak': 'off',
		},
	},
]
