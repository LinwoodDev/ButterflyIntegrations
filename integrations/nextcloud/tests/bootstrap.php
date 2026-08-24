<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$nextcloudBootstrap = __DIR__ . '/../../../tests/bootstrap.php';
if (is_file($nextcloudBootstrap)) {
	require_once $nextcloudBootstrap;
	\OC_App::loadApp(OCA\Butterfly\AppInfo\Application::APP_ID);
	OC_Hook::clear();
} else {
	require_once __DIR__ . '/../stubs/Emitter.php';

	spl_autoload_register(static function (string $class): void {
		if (!str_starts_with($class, 'OCP\\') && !str_starts_with($class, 'NCU\\')) {
			return;
		}

		$path = __DIR__ . '/../vendor/nextcloud/ocp/' . str_replace('\\', '/', $class) . '.php';
		if (is_file($path)) {
			require_once $path;
		}
	});
}
