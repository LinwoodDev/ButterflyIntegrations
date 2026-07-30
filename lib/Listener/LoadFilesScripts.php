<?php

declare(strict_types=1);

namespace OCA\Butterfly\Listener;

use OCA\Butterfly\AppInfo\Application;
use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Util;

/**
 * @template-implements IEventListener<LoadAdditionalScriptsEvent>
 */
final class LoadFilesScripts implements IEventListener {
	public function handle(Event $event): void {
		if (!$event instanceof LoadAdditionalScriptsEvent) {
			return;
		}

		Util::addInitScript(
			Application::APP_ID,
			Application::APP_ID . '-files',
		);
	}
}
