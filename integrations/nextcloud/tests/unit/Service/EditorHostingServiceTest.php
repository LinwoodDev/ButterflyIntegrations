<?php

declare(strict_types=1);

namespace Service;

use OCA\Butterfly\Service\BundleValidator;
use OCA\Butterfly\Service\EditorHostingService;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Files\AppData\IAppDataFactory;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;

final class EditorHostingServiceTest extends TestCase {
	public function testCustomDomainIsNormalized(): void {
		$appConfig = $this->createMock(IAppConfig::class);
		$appConfig->expects($this->once())
			->method('setAppValueString')
			->with('editor_custom_domain', 'https://editor.example.com:8443');

		$service = $this->createService($appConfig);
		$this->assertSame(
			'https://editor.example.com:8443',
			$service->setCustomDomain(' HTTPS://Editor.Example.COM:8443/ '),
		);
	}

	public function testCustomDomainRejectsPaths(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->createService($this->createMock(IAppConfig::class))
			->setCustomDomain('https://editor.example.com/untrusted/path');
	}

	public function testUploadedDeploymentProvidesLocalEmbedUrl(): void {
		$appConfig = $this->createMock(IAppConfig::class);
		$appConfig->method('getAppValueString')->willReturnCallback(
			static fn (string $key): string => $key === 'editor_deployment' ? 'editor-test' : '',
		);
		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->expects($this->once())
			->method('linkToRouteAbsolute')
			->with('butterfly.editor.asset', ['path' => 'embed'])
			->willReturn('https://cloud.example.com/apps/butterfly/editor/embed');

		$service = new EditorHostingService(
			$appConfig,
			$this->createMock(IAppDataFactory::class),
			$urlGenerator,
			new BundleValidator(),
		);
		$this->assertSame(
			'https://cloud.example.com/apps/butterfly/editor/embed',
			$service->getEmbedUrl(),
		);
	}

	private function createService(IAppConfig $appConfig): EditorHostingService {
		return new EditorHostingService(
			$appConfig,
			$this->createMock(IAppDataFactory::class),
			$this->createMock(IURLGenerator::class),
			new BundleValidator(),
		);
	}
}
