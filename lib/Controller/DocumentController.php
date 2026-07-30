<?php

declare(strict_types=1);

namespace OCA\Butterfly\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Response;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Reads and writes Butterfly documents in the current user's Nextcloud files.
 *
 * @psalm-suppress UnusedClass
 */
final class DocumentController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private IRootFolder $rootFolder,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	#[OpenAPI(OpenAPI::SCOPE_IGNORE)]
	#[FrontpageRoute(verb: 'GET', url: '/api/document')]
	public function show(string $path): Response {
		try {
			$file = $this->getDocument($path);
			return new DataDisplayResponse(
				$file->getContent(),
				Http::STATUS_OK,
				[
					'Content-Type' => 'application/x-butterfly',
					'ETag' => $this->quotedEtag($file),
					'Cache-Control' => 'no-store',
				],
			);
		} catch (NotFoundException) {
			return $this->error('The Butterfly document was not found.', Http::STATUS_NOT_FOUND);
		} catch (NotPermittedException) {
			return $this->error('You do not have permission to read this document.', Http::STATUS_FORBIDDEN);
		}
	}

	#[NoAdminRequired]
	#[OpenAPI(OpenAPI::SCOPE_IGNORE)]
	#[FrontpageRoute(verb: 'PUT', url: '/api/document/create')]
	public function create(string $path): Response {
		try {
			$normalizedPath = $this->normalizeDocumentPath($path);
			$separator = strrpos($normalizedPath, '/');
			$directory = $separator === false ? '' : substr($normalizedPath, 0, $separator);
			$fileName = $separator === false
				? $normalizedPath
				: substr($normalizedPath, $separator + 1);

			$userFolder = $this->getUserFolder();
			$parent = $directory === '' ? $userFolder : $userFolder->get($directory);
			if (!$parent instanceof Folder) {
				throw new NotFoundException('The parent folder was not found');
			}
			if (!$parent->isCreatable()) {
				return $this->error(
					'You do not have permission to create a document in this folder.',
					Http::STATUS_FORBIDDEN,
				);
			}
			if ($parent->nodeExists($fileName)) {
				return $this->error(
					'A file with this name already exists.',
					Http::STATUS_CONFLICT,
				);
			}

			$content = $this->requestDocument();
			if ($content === null) {
				return $this->error(
					'The document body is missing, empty, or invalid.',
					Http::STATUS_BAD_REQUEST,
				);
			}

			$file = $parent->newFile($fileName, $content);
			return new DataResponse(
				['etag' => $this->quotedEtag($file)],
				Http::STATUS_CREATED,
			);
		} catch (NotFoundException) {
			return $this->error('The parent folder was not found.', Http::STATUS_NOT_FOUND);
		} catch (NotPermittedException) {
			return $this->error(
				'You do not have permission to create this document.',
				Http::STATUS_FORBIDDEN,
			);
		}
	}

	#[NoAdminRequired]
	#[OpenAPI(OpenAPI::SCOPE_IGNORE)]
	#[FrontpageRoute(verb: 'PUT', url: '/api/document')]
	public function update(string $path): Response {
		try {
			$file = $this->getDocument($path);
			if (!$file->isUpdateable()) {
				return $this->error(
					'You do not have permission to update this document.',
					Http::STATUS_FORBIDDEN,
				);
			}

			$ifMatch = trim($this->request->getHeader('If-Match'));
			if (
				$ifMatch !== ''
				&& $ifMatch !== '*'
				&& trim($ifMatch, '"') !== $file->getEtag()
			) {
				return $this->error(
					'The document changed on the server. Reload it before saving again.',
					Http::STATUS_PRECONDITION_FAILED,
				);
			}

			$content = $this->requestDocument();
			if ($content === null) {
				return $this->error(
					'The document body is missing, empty, or invalid.',
					Http::STATUS_BAD_REQUEST,
				);
			}

			$file->putContent($content);

			return new DataResponse([
				'etag' => $this->quotedEtag($file),
			]);
		} catch (NotFoundException) {
			return $this->error('The Butterfly document was not found.', Http::STATUS_NOT_FOUND);
		} catch (NotPermittedException) {
			return $this->error('You do not have permission to update this document.', Http::STATUS_FORBIDDEN);
		}
	}

	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	private function getDocument(string $path): File {
		$node = $this->getUserFolder()->get($this->normalizeDocumentPath($path));
		if (!$node instanceof File) {
			throw new NotFoundException('Not a file');
		}

		return $node;
	}

	/**
	 * @throws NotFoundException
	 */
	private function normalizeDocumentPath(string $path): string {
		$normalizedPath = ltrim(trim($path), '/');
		if (
			$normalizedPath === ''
			|| !str_ends_with(strtolower($normalizedPath), '.bfly')
		) {
			throw new NotFoundException('Not a Butterfly document');
		}

		return $normalizedPath;
	}

	/**
	 * @throws NotPermittedException
	 */
	private function getUserFolder(): Folder {
		$user = $this->userSession->getUser();
		if ($user === null) {
			throw new NotPermittedException('No authenticated user');
		}

		return $this->rootFolder->getUserFolder($user->getUID());
	}

	private function requestDocument(): ?string {
		if ($this->request->getMethod() === 'PUT') {
			/**
			 * Nextcloud documents IRequest::put as a raw stream even though the
			 * magic property cannot be expressed by the public interface.
			 *
			 * @var resource|false $stream
			 * @psalm-suppress MixedAssignment
			 * @psalm-suppress NoInterfaceProperties
			 */
			$stream = $this->request->put;
			if (is_resource($stream)) {
				$content = stream_get_contents($stream);
				return $content === false || $content === '' ? null : $content;
			}
		}

		// Keep multipart support for controller tests and older callers.
		$upload = $this->request->getUploadedFile('document');
		$uploadError = $upload['error'] ?? UPLOAD_ERR_NO_FILE;
		$tmpName = $upload['tmp_name'] ?? null;
		if (
			$uploadError !== UPLOAD_ERR_OK
			|| !is_string($tmpName)
			|| !is_file($tmpName)
		) {
			return null;
		}

		$content = file_get_contents($tmpName);
		return $content === false || $content === '' ? null : $content;
	}

	private function quotedEtag(File $file): string {
		return '"' . trim($file->getEtag(), '"') . '"';
	}

	private function error(string $message, int $status): DataResponse {
		/** @psalm-suppress ArgumentTypeCoercion */
		return new DataResponse(['message' => $message], $status);
	}
}
