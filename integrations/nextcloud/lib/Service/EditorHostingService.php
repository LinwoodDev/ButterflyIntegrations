<?php

declare(strict_types=1);

namespace OCA\Butterfly\Service;

use OCA\Butterfly\AppInfo\Application;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\IURLGenerator;

class EditorHostingService {
	public const DEFAULT_ORIGIN = 'https://preview.butterfly.linwood.dev';

	private const CONFIG_CUSTOM_DOMAIN = 'editor_custom_domain';
	private const CONFIG_DEPLOYMENT = 'editor_deployment';
	private const CONFIG_PACKAGE_NAME = 'editor_package_name';
	private const CONFIG_BUILD_NUMBER = 'editor_build_number';

	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(
		private IAppConfig $appConfig,
		private IAppDataFactory $appDataFactory,
		private IURLGenerator $urlGenerator,
		private BundleValidator $bundleValidator,
	) {
	}

	public function getCustomDomain(): string {
		return $this->appConfig->getAppValueString(self::CONFIG_CUSTOM_DOMAIN);
	}

	public function setCustomDomain(string $domain): string {
		$domain = trim($domain);
		if ($domain === '') {
			$this->appConfig->deleteAppValue(self::CONFIG_CUSTOM_DOMAIN);
			return '';
		}

		$parts = parse_url($domain);
		$scheme = is_array($parts) && isset($parts['scheme'])
			? strtolower($parts['scheme'])
			: null;
		if (
			!is_array($parts)
			|| !in_array($scheme, ['http', 'https'], true)
			|| !isset($parts['host'])
			|| array_key_exists('user', $parts)
			|| array_key_exists('pass', $parts)
			|| array_key_exists('query', $parts)
			|| array_key_exists('fragment', $parts)
			|| !in_array($parts['path'] ?? '', ['', '/'], true)
		) {
			throw new \InvalidArgumentException('Enter an HTTP(S) origin without a path, query, or credentials.');
		}

		$host = strtolower($parts['host']);
		if (str_contains($host, ':')) {
			$host = '[' . trim($host, '[]') . ']';
		}
		$normalized = $scheme . '://' . $host;
		if (isset($parts['port'])) {
			$normalized .= ':' . $parts['port'];
		}

		$this->appConfig->setAppValueString(self::CONFIG_CUSTOM_DOMAIN, $normalized);
		return $normalized;
	}

	public function getEmbedUrl(): string {
		$customDomain = $this->getCustomDomain();
		if ($customDomain !== '') {
			return $customDomain . '/embed';
		}
		if ($this->hasDeployment()) {
			return $this->urlGenerator->linkToRouteAbsolute('butterfly.editor.asset', ['path' => 'embed']);
		}

		return self::DEFAULT_ORIGIN . '/embed';
	}

	public function getFrameDomain(): string {
		$url = $this->getEmbedUrl();
		$parts = parse_url($url);
		if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
			return "'self'";
		}

		$domain = $parts['scheme'] . '://' . $parts['host'];
		return isset($parts['port']) ? $domain . ':' . $parts['port'] : $domain;
	}

	/**
	 * @return array{packageName: string, buildNumber: string}|null
	 */
	public function getDeploymentVersion(): ?array {
		if (!$this->hasDeployment()) {
			return null;
		}

		return [
			'packageName' => $this->appConfig->getAppValueString(self::CONFIG_PACKAGE_NAME),
			'buildNumber' => $this->appConfig->getAppValueString(self::CONFIG_BUILD_NUMBER),
		];
	}

	/**
	 * @return array{packageName: string, buildNumber: string}
	 */
	public function deploy(string $zipPath): array {
		$archive = new \ZipArchive();
		if ($archive->open($zipPath, \ZipArchive::RDONLY) !== true) {
			throw new BundleValidationException('The uploaded file is not a readable ZIP archive.');
		}

		$deploymentName = 'editor-' . bin2hex(random_bytes(12));
		$folder = null;
		try {
			$metadata = $this->bundleValidator->validate($archive);
			$folder = $this->appDataFactory->get(Application::APP_ID)->newFolder($deploymentName);
			$this->copyArchive($archive, $metadata['root'], $folder);
		} catch (\Throwable $exception) {
			$folder?->delete();
			throw $exception;
		} finally {
			$archive->close();
		}

		$previousDeployment = $this->appConfig->getAppValueString(self::CONFIG_DEPLOYMENT);
		$this->appConfig->setAppValueString(self::CONFIG_PACKAGE_NAME, $metadata['packageName']);
		$this->appConfig->setAppValueString(self::CONFIG_BUILD_NUMBER, $metadata['buildNumber']);
		$this->appConfig->setAppValueString(self::CONFIG_DEPLOYMENT, $deploymentName);
		$this->appConfig->deleteAppValue(self::CONFIG_CUSTOM_DOMAIN);

		if ($previousDeployment !== '' && $previousDeployment !== $deploymentName) {
			try {
				$this->appDataFactory->get(Application::APP_ID)->getFolder($previousDeployment)->delete();
			} catch (\Throwable) {
			}
		}

		return [
			'packageName' => $metadata['packageName'],
			'buildNumber' => $metadata['buildNumber'],
		];
	}

	public function getAsset(string $path): ?ISimpleFile {
		$deployment = $this->appConfig->getAppValueString(self::CONFIG_DEPLOYMENT);
		if ($deployment === '') {
			return null;
		}

		try {
			$root = $this->appDataFactory->get(Application::APP_ID)->getFolder($deployment);
			$normalized = $this->normalizeAssetPath($path);
			$file = $this->findFile($root, $normalized);
			if ($file !== null) {
				return $file;
			}

			return str_contains(basename($normalized), '.') ? null : $root->getFile('index.html');
		} catch (NotFoundException|BundleValidationException) {
			return null;
		}
	}

	private function hasDeployment(): bool {
		return $this->appConfig->getAppValueString(self::CONFIG_DEPLOYMENT) !== '';
	}

	private function normalizeAssetPath(string $path): string {
		$path = rawurldecode($path);
		if ($path === '' || $path === 'embed') {
			return 'index.html';
		}

		return $this->bundleValidator->normalizeEntryName($path);
	}

	private function findFile(ISimpleFolder $root, string $path): ?ISimpleFile {
		try {
			$parts = explode('/', $path);
			$fileName = array_pop($parts);
			$folder = $root;
			foreach ($parts as $part) {
				$folder = $folder->getFolder($part);
			}

			return $folder->getFile($fileName);
		} catch (NotFoundException) {
			return null;
		}
	}

	private function copyArchive(\ZipArchive $archive, string $rootPrefix, ISimpleFolder $destination): void {
		for ($index = 0; $index < $archive->numFiles; $index++) {
			$stat = $archive->statIndex($index);
			if (!is_array($stat) || !isset($stat['name'])) {
				continue;
			}

			$name = $this->bundleValidator->normalizeEntryName((string)$stat['name']);
			if (!str_starts_with($name, $rootPrefix)) {
				continue;
			}
			$relative = substr($name, strlen($rootPrefix));
			if ($relative === '' || str_ends_with($relative, '/')) {
				continue;
			}

			$content = $archive->getFromIndex($index);
			if (!is_string($content)) {
				throw new BundleValidationException('A file in the ZIP could not be read.');
			}
			if ($relative === 'index.html') {
				$content = preg_replace(
					'/<base\s+href=([' . "'\"" . ']).*?\1\s*\/?>/i',
					'<base href="./">',
					$content,
					1,
				) ?? $content;
			}

			$this->writeFile($destination, $relative, $content);
		}
	}

	private function writeFile(ISimpleFolder $root, string $path, string $content): void {
		$parts = explode('/', $path);
		$fileName = array_pop($parts);
		$folder = $root;
		foreach ($parts as $part) {
			try {
				$folder = $folder->getFolder($part);
			} catch (NotFoundException) {
				$folder = $folder->newFolder($part);
			}
		}

		$folder->newFile($fileName, $content);
	}
}
