<?php

declare(strict_types=1);

use OCP\Util;

Util::addScript(OCA\Butterfly\AppInfo\Application::APP_ID, OCA\Butterfly\AppInfo\Application::APP_ID . '-main');
Util::addStyle(OCA\Butterfly\AppInfo\Application::APP_ID, OCA\Butterfly\AppInfo\Application::APP_ID . '-main');

?>

<div id="butterfly"></div>
