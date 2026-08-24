<?php

declare(strict_types=1);

namespace Controller;

use OCA\Butterfly\AppInfo\Application;
use OCA\Butterfly\Controller\DocumentController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

final class DocumentControllerTest extends TestCase {
	private IRequest $request;
	private IRootFolder $rootFolder;
	private IUserSession $userSession;
	private Folder $userFolder;
	private IUser $user;

	protected function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->userFolder = $this->createMock(Folder::class);
		$this->user = $this->createMock(IUser::class);

		$this->user->method('getUID')->willReturn('ada');
		$this->userSession->method('getUser')->willReturn($this->user);
		$this->rootFolder->method('getUserFolder')->with('ada')->willReturn($this->userFolder);
	}

	public function testShowReturnsDocumentAndEtag(): void {
		$file = $this->createMock(File::class);
		$file->method('getContent')->willReturn('document-bytes');
		$file->method('getEtag')->willReturn('etag-1');
		$this->userFolder->method('get')->with('Notes/example.bfly')->willReturn($file);

		$response = $this->controller()->show('/Notes/example.bfly');

		$this->assertInstanceOf(DataDisplayResponse::class, $response);
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame('document-bytes', $response->getData());
	}

	public function testUpdateUsesIfMatchAndReturnsNewEtag(): void {
		$file = $this->createMock(File::class);
		$file->method('isUpdateable')->willReturn(true);
		$file->method('getEtag')->willReturnOnConsecutiveCalls('etag-1', 'etag-2');
		$file->expects($this->once())->method('putContent')->with("updated-bytes\n");
		$this->userFolder->method('get')->with('Notes/example.bfly')->willReturn($file);
		$this->request->method('getHeader')->with('If-Match')->willReturn('"etag-1"');
		$this->request->method('getUploadedFile')->with('document')->willReturn([
			'error' => UPLOAD_ERR_OK,
			'tmp_name' => __DIR__ . '/../../fixtures/document.bfly',
		]);

		$response = $this->controller()->update('/Notes/example.bfly');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame('"etag-2"', $response->getData()['etag']);
	}

	public function testCreateStoresNewDocument(): void {
		$file = $this->createMock(File::class);
		$file->method('getEtag')->willReturn('etag-new');
		$this->userFolder->method('isCreatable')->willReturn(true);
		$this->userFolder->method('nodeExists')->with('New document.bfly')->willReturn(false);
		$this->userFolder
			->expects($this->once())
			->method('newFile')
			->with('New document.bfly', "updated-bytes\n")
			->willReturn($file);
		$this->request->method('getUploadedFile')->with('document')->willReturn([
			'error' => UPLOAD_ERR_OK,
			'tmp_name' => __DIR__ . '/../../fixtures/document.bfly',
		]);

		$response = $this->controller()->create('/New document.bfly');

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$this->assertSame('"etag-new"', $response->getData()['etag']);
	}

	public function testCreateRejectsExistingFile(): void {
		$this->userFolder->method('isCreatable')->willReturn(true);
		$this->userFolder->method('nodeExists')->with('Existing.bfly')->willReturn(true);
		$this->userFolder->expects($this->never())->method('newFile');

		$response = $this->controller()->create('/Existing.bfly');

		$this->assertSame(Http::STATUS_CONFLICT, $response->getStatus());
	}

	public function testUpdateRejectsConflictingVersion(): void {
		$file = $this->createMock(File::class);
		$file->method('isUpdateable')->willReturn(true);
		$file->method('getEtag')->willReturn('etag-current');
		$file->expects($this->never())->method('putContent');
		$this->userFolder->method('get')->willReturn($file);
		$this->request->method('getHeader')->willReturn('"etag-stale"');

		$response = $this->controller()->update('/Notes/example.bfly');

		$this->assertSame(Http::STATUS_PRECONDITION_FAILED, $response->getStatus());
	}

	public function testRejectsNonButterflyFiles(): void {
		$response = $this->controller()->show('/Notes/example.txt');

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	private function controller(): DocumentController {
		return new DocumentController(
			Application::APP_ID,
			$this->request,
			$this->rootFolder,
			$this->userSession,
		);
	}
}
