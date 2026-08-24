<?php

declare(strict_types=1);

namespace Controller;

use OCA\Butterfly\AppInfo\Application;
use OCA\Butterfly\Controller\PageController;
use OCA\Butterfly\Service\EditorHostingService;
use OCP\AppFramework\Services\IInitialState;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

final class PageControllerTest extends TestCase {
	public function testIndexProvidesEditorConfiguration(): void {
		$request = $this->createMock(IRequest::class);
		$initialState = $this->createMock(IInitialState::class);
		$rootFolder = $this->createMock(IRootFolder::class);
		$userSession = $this->createMock(IUserSession::class);
		$hostingService = $this->createMock(EditorHostingService::class);
		$hostingService->method('getEmbedUrl')->willReturn('https://preview.butterfly.linwood.dev/embed');
		$hostingService->method('getFrameDomain')->willReturn('https://preview.butterfly.linwood.dev');
		$initialState
			->expects($this->once())
			->method('provideInitialState')
			->with(
				'config',
				[
					'filePath' => '/Notes/example.bfly',
					'embedUrl' => 'https://preview.butterfly.linwood.dev/embed',
					'create' => true,
					'existingRootNames' => [],
				],
			);

		$controller = new PageController(
			Application::APP_ID,
			$request,
			$initialState,
			$rootFolder,
			$userSession,
			$hostingService,
		);
		$response = $controller->index('/Notes/example.bfly', '1');

		$this->assertSame('index', $response->getTemplateName());
	}

	public function testIndexProvidesExistingRootButterflyNames(): void {
		$request = $this->createMock(IRequest::class);
		$initialState = $this->createMock(IInitialState::class);
		$rootFolder = $this->createMock(IRootFolder::class);
		$userSession = $this->createMock(IUserSession::class);
		$hostingService = $this->createMock(EditorHostingService::class);
		$hostingService->method('getEmbedUrl')->willReturn('https://preview.butterfly.linwood.dev/embed');
		$hostingService->method('getFrameDomain')->willReturn('https://preview.butterfly.linwood.dev');
		$userFolder = $this->createMock(Folder::class);
		$user = $this->createMock(IUser::class);
		$document = $this->createMock(File::class);
		$otherFile = $this->createMock(File::class);

		$user->method('getUID')->willReturn('ada');
		$userSession->method('getUser')->willReturn($user);
		$rootFolder->method('getUserFolder')->with('ada')->willReturn($userFolder);
		$document->method('getName')->willReturn('Existing.bfly');
		$otherFile->method('getName')->willReturn('Notes.txt');
		$userFolder->method('getDirectoryListing')->willReturn([$document, $otherFile]);
		$initialState
			->expects($this->once())
			->method('provideInitialState')
			->with(
				'config',
				[
					'filePath' => null,
					'embedUrl' => 'https://preview.butterfly.linwood.dev/embed',
					'create' => false,
					'existingRootNames' => ['Existing.bfly'],
				],
			);

		$controller = new PageController(
			Application::APP_ID,
			$request,
			$initialState,
			$rootFolder,
			$userSession,
			$hostingService,
		);
		$response = $controller->index();

		$this->assertSame('index', $response->getTemplateName());
	}
}
