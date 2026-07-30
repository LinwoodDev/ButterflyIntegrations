<?php

declare(strict_types=1);

namespace Controller;

use OCA\Butterfly\AppInfo\Application;
use OCA\Butterfly\Controller\PageController;
use OCP\AppFramework\Services\IInitialState;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

final class PageControllerTest extends TestCase {
	public function testIndexProvidesEditorConfiguration(): void {
		$request = $this->createMock(IRequest::class);
		$initialState = $this->createMock(IInitialState::class);
		$initialState
			->expects($this->once())
			->method('provideInitialState')
			->with(
				'config',
				[
					'filePath' => '/Notes/example.bfly',
					'embedUrl' => 'https://preview.butterfly.linwood.dev/embed',
					'create' => true,
				],
			);

		$controller = new PageController(Application::APP_ID, $request, $initialState);
		$response = $controller->index('/Notes/example.bfly', '1');

		$this->assertSame('index', $response->getTemplateName());
	}
}
