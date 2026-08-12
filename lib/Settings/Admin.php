<?php

declare(strict_types=1);

namespace OCA\Butterfly\Settings;

use OCA\Butterfly\AppInfo\Application;
use OCA\Butterfly\Service\EditorHostingService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;

/** @psalm-suppress UnusedClass */
final class Admin implements ISettings {
	public function __construct(
		private EditorHostingService $hostingService,
	) {
	}

	public function getForm(): TemplateResponse {
		return new TemplateResponse(Application::APP_ID, 'settings/admin', [
			'customDomain' => $this->hostingService->getCustomDomain(),
			'embedUrl' => $this->hostingService->getEmbedUrl(),
			'deploymentVersion' => $this->hostingService->getDeploymentVersion(),
		]);
	}

	public function getSection(): string {
		return 'additional';
	}

	public function getPriority(): int {
		return 50;
	}
}
