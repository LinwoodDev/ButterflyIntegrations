<?php

declare(strict_types=1);

namespace OCA\Butterfly\Controller;

use OCA\Butterfly\AppInfo\Application;
use OCA\Butterfly\Service\EditorHostingService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\Files\IRootFolder;
use OCP\IUserSession;

/**
 * @psalm-suppress UnusedClass
 */
class PageController extends Controller {
	public function __construct(
		string $appName,
		\OCP\IRequest $request,
		private IInitialState $initialState,
		private IRootFolder $rootFolder,
		private IUserSession $userSession,
		private EditorHostingService $hostingService,
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
			'embedUrl' => $this->hostingService->getEmbedUrl(),
			'create' => $create === '1',
			'existingRootNames' => $file === null
				? $this->getExistingRootDocumentNames()
				: [],
		]);

		$response = new TemplateResponse(
			Application::APP_ID,
			'index',
		);
		$policy = new ContentSecurityPolicy();
		$policy->addAllowedFrameDomain($this->hostingService->getFrameDomain());
		$response->setContentSecurityPolicy($policy);

		return $response;
	}

	/**
	 * @return list<string>
	 */
	private function getExistingRootDocumentNames(): array {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return [];
		}

		$names = [];
		foreach ($this->rootFolder->getUserFolder($user->getUID())->getDirectoryListing() as $node) {
			$name = $node->getName();
			if (str_ends_with(strtolower($name), '.bfly')) {
				$names[] = $name;
			}
		}

		return $names;
	}
}
