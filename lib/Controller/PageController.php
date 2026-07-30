<?php

declare(strict_types=1);

namespace OCA\Butterfly\Controller;

use OCA\Butterfly\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;

/**
 * @psalm-suppress UnusedClass
 */
class PageController extends Controller {
	private const EMBED_URL = 'https://preview.butterfly.linwood.dev/embed';

	public function __construct(
		string $appName,
		\OCP\IRequest $request,
		private IInitialState $initialState,
	) {
		parent::__construct($appName, $request);
	}

	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[OpenAPI(OpenAPI::SCOPE_IGNORE)]
	#[FrontpageRoute(verb: 'GET', url: '/')]
	public function index(?string $file = null, ?string $create = null): TemplateResponse {
		$this->initialState->provideInitialState('config', [
			'filePath' => $file,
			'embedUrl' => self::EMBED_URL,
			'create' => $create === '1',
		]);

		$response = new TemplateResponse(
			Application::APP_ID,
			'index',
		);
		$policy = new ContentSecurityPolicy();
		$policy->addAllowedFrameDomain('https://preview.butterfly.linwood.dev');
		$response->setContentSecurityPolicy($policy);

		return $response;
	}
}
