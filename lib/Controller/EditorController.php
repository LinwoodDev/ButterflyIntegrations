<?php

declare(strict_types=1);

namespace OCA\Butterfly\Controller;

use OCA\Butterfly\Service\EditorHostingService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\IRequest;

/** @psalm-suppress UnusedClass */
final class EditorController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private EditorHostingService $hostingService,
	) {
		parent::__construct($appName, $request);
	}

	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[OpenAPI(OpenAPI::SCOPE_IGNORE)]
	#[FrontpageRoute(verb: 'GET', url: '/editor/{path}', requirements: ['path' => '.*'])]
	public function asset(string $path): DataDisplayResponse {
		$file = $this->hostingService->getAsset($path);
		if ($file === null) {
			return new DataDisplayResponse('Not found', Http::STATUS_NOT_FOUND, [
				'Content-Type' => 'text/plain; charset=utf-8',
			]);
		}

		$response = new DataDisplayResponse($file->getContent(), Http::STATUS_OK, [
			'Content-Type' => $this->contentType($file->getName()),
			'X-Content-Type-Options' => 'nosniff',
		]);
		$response->setETag($file->getETag());
		$response->cacheFor(3600, false);

		$policy = new ContentSecurityPolicy();
		$policy->allowEvalWasm();
		$policy->addAllowedWorkerSrcDomain('blob:');
		$policy->addAllowedConnectDomain('blob:');
		$response->setContentSecurityPolicy($policy);

		return $response;
	}

	private function contentType(string $fileName): string {
		return match (strtolower(pathinfo($fileName, PATHINFO_EXTENSION))) {
			'css' => 'text/css; charset=utf-8',
			'html' => 'text/html; charset=utf-8',
			'js', 'mjs' => 'text/javascript; charset=utf-8',
			'json', 'map' => 'application/json; charset=utf-8',
			'wasm' => 'application/wasm',
			'svg' => 'image/svg+xml',
			'png' => 'image/png',
			'jpg', 'jpeg' => 'image/jpeg',
			'gif' => 'image/gif',
			'webp' => 'image/webp',
			'ico' => 'image/x-icon',
			'woff' => 'font/woff',
			'woff2' => 'font/woff2',
			'ttf' => 'font/ttf',
			'otf' => 'font/otf',
			default => 'application/octet-stream',
		};
	}
}
