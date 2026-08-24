import { rm } from 'node:fs/promises'

await Promise.all([
	rm(new URL('../css', import.meta.url), { force: true, recursive: true }),
	rm(new URL('../js', import.meta.url), { force: true, recursive: true }),
])
