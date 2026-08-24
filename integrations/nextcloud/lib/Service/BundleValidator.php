<?php

declare(strict_types=1);

namespace OCA\Butterfly\Service;

final class BundleValidator {
	// Keep aligned with MINIMUM_BUTTERFLY_BUILD_NUMBER in packages/shared.
	public const MINIMUM_BUILD_NUMBER = 193;

	private const MAX_ENTRIES = 10000;
	private const MAX_UNCOMPRESSED_SIZE = 536870912;

	/**
	 * @return array{root: string, packageName: string, buildNumber: string}
	 */
	public function validate(\ZipArchive $archive): array {
		if ($archive->numFiles < 1 || $archive->numFiles > self::MAX_ENTRIES) {
			throw new BundleValidationException('The ZIP contains an unsupported number of files.');
		}

		$versionFiles = [];
		$seenNames = [];
		$totalSize = 0;
		for ($index = 0; $index < $archive->numFiles; $index++) {
			$stat = $archive->statIndex($index);
			if (!is_array($stat) || !isset($stat['name'], $stat['size'])) {
				throw new BundleValidationException('The ZIP contains an unreadable entry.');
			}

			$name = $this->normalizeEntryName((string)$stat['name']);
			if (isset($seenNames[$name])) {
				throw new BundleValidationException('The ZIP contains duplicate paths.');
			}
			$seenNames[$name] = true;
			$totalSize += (int)$stat['size'];
			if ($totalSize > self::MAX_UNCOMPRESSED_SIZE) {
				throw new BundleValidationException('The uncompressed ZIP is larger than 512 MiB.');
			}
			if ($this->isSymbolicLink($archive, $index)) {
				throw new BundleValidationException('Symbolic links are not allowed in the ZIP.');
			}
			if (basename($name) === 'version.json') {
				$versionFiles[] = ['index' => $index, 'path' => $name];
			}
		}

		if (count($versionFiles) !== 1) {
			throw new BundleValidationException('The ZIP must contain exactly one version.json.');
		}

		$versionFile = $versionFiles[0];
		$content = $archive->getFromIndex($versionFile['index']);
		if (!is_string($content)) {
			throw new BundleValidationException('version.json could not be read.');
		}

		try {
			/** @var mixed $decoded */
			$decoded = json_decode($content, true, 16, JSON_THROW_ON_ERROR);
		} catch (\JsonException $exception) {
			throw new BundleValidationException('version.json is not valid JSON.', 0, $exception);
		}

		if (!is_array($decoded) || ($decoded['package_name'] ?? null) !== 'butterfly') {
			throw new BundleValidationException('version.json must have package_name set to "butterfly".');
		}

		$buildNumber = $decoded['build_number'] ?? null;
		if (
			!is_string($buildNumber)
			|| !ctype_digit($buildNumber)
			|| (int)$buildNumber < self::MINIMUM_BUILD_NUMBER
		) {
			throw new BundleValidationException('version.json must have a string build_number of "193" or higher.');
		}

		$root = dirname($versionFile['path']);
		$root = $root === '.' ? '' : $root . '/';
		if ($archive->locateName($root . 'index.html') === false) {
			throw new BundleValidationException('The ZIP must contain index.html next to version.json.');
		}

		return [
			'root' => $root,
			'packageName' => 'butterfly',
			'buildNumber' => $buildNumber,
		];
	}

	public function normalizeEntryName(string $name): string {
		if ($name === '' || preg_match('/[\x00-\x1f\x7f]/', $name) === 1 || str_contains($name, '\\')) {
			throw new BundleValidationException('The ZIP contains an invalid path.');
		}

		if (str_starts_with($name, '/')) {
			throw new BundleValidationException('The ZIP contains an unsafe path.');
		}
		$parts = explode('/', rtrim($name, '/'));
		if (in_array('', $parts, true) || in_array('.', $parts, true) || in_array('..', $parts, true)) {
			throw new BundleValidationException('The ZIP contains an unsafe path.');
		}

		return implode('/', $parts) . (str_ends_with($name, '/') ? '/' : '');
	}

	private function isSymbolicLink(\ZipArchive $archive, int $index): bool {
		$attributes = 0;
		$operationsSystem = 0;
		if (!$archive->getExternalAttributesIndex($index, $operationsSystem, $attributes)) {
			return false;
		}

		return (($attributes >> 16) & 0170000) === 0120000;
	}
}
