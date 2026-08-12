<?php

declare(strict_types=1);

namespace OCA\Butterfly\Controller;

use OCA\Butterfly\Service\BundleValidationException;
use OCA\Butterfly\Service\EditorHostingService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

/** @psalm-suppress UnusedClass */
final class AdminController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private EditorHostingService $hostingService,
	) {
		parent::__construct($appName, $request);
	}

	#[OpenAPI(OpenAPI::SCOPE_IGNORE)]
	#[FrontpageRoute(verb: 'POST', url: '/admin/domain')]
	public function saveDomain(string $domain = ''): DataResponse {
		try {
			$domain = $this->hostingService->setCustomDomain($domain);
		} catch (\InvalidArgumentException $exception) {
			return new DataResponse(['message' => $exception->getMessage()], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse([
			'domain' => $domain,
			'embedUrl' => $this->hostingService->getEmbedUrl(),
		]);
	}

	#[OpenAPI(OpenAPI::SCOPE_IGNORE)]
	#[FrontpageRoute(verb: 'POST', url: '/admin/bundle')]
	public function uploadBundle(): DataResponse {
		$upload = $this->request->getUploadedFile('bundle');
		$error = $upload['error'] ?? UPLOAD_ERR_NO_FILE;
		$tmpName = $upload['tmp_name'] ?? null;
		if ($error !== UPLOAD_ERR_OK || !is_string($tmpName) || !is_file($tmpName)) {
			return new DataResponse(['message' => 'Select a ZIP file to upload.'], Http::STATUS_BAD_REQUEST);
		}

		try {
			$version = $this->hostingService->deploy($tmpName);
		} catch (BundleValidationException $exception) {
			return new DataResponse(['message' => $exception->getMessage()], Http::STATUS_BAD_REQUEST);
		} catch (\Throwable) {
			return new DataResponse(['message' => 'The editor bundle could not be installed.'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new DataResponse([
			'version' => $version,
			'embedUrl' => $this->hostingService->getEmbedUrl(),
		]);
	}
}
