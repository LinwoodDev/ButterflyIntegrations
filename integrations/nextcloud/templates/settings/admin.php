<?php

declare(strict_types=1);

use OCA\Butterfly\AppInfo\Application;
use OCP\Util;

Util::addScript(Application::APP_ID, Application::APP_ID . '-admin');
Util::addStyle(Application::APP_ID, Application::APP_ID . '-admin');

/** @var array{packageName: string, buildNumber: string}|null $deploymentVersion */
$deploymentVersion = $_['deploymentVersion'];
?>

<div id="butterfly-admin-settings" class="section">
	<h2><?php p($l->t('Butterfly editor hosting')); ?></h2>
	<p class="settings-hint">
		<?php p($l->t('Use Butterfly Preview, an editor on your own domain, or upload a Butterfly web build to Nextcloud.')); ?>
	</p>

	<p>
		<strong><?php p($l->t('Active editor:')); ?></strong>
		<code id="butterfly-active-editor"><?php p($_['embedUrl']); ?></code>
	</p>

	<form id="butterfly-domain-form">
		<label for="butterfly-custom-domain"><?php p($l->t('Custom domain')); ?></label>
		<input
			id="butterfly-custom-domain"
			name="domain"
			type="url"
			placeholder="https://butterfly.example.com"
			value="<?php p($_['customDomain']); ?>">
		<button type="submit" class="primary"><?php p($l->t('Save domain')); ?></button>
		<p class="settings-hint">
			<?php p($l->t('Leave empty to use the uploaded build, or Butterfly Preview when no build has been uploaded.')); ?>
		</p>
	</form>

	<form id="butterfly-bundle-form">
		<label for="butterfly-bundle"><?php p($l->t('Self-hosted editor ZIP')); ?></label>
		<input id="butterfly-bundle" name="bundle" type="file" accept=".zip,application/zip" required>
		<button type="submit" class="primary"><?php p($l->t('Upload and activate')); ?></button>
		<p class="settings-hint">
			<?php p($l->t('The ZIP must contain index.html and version.json. package_name must be "butterfly", and build_number must be the string "193" or higher.')); ?>
		</p>
		<p id="butterfly-bundle-version">
			<?php if ($deploymentVersion !== null) { ?>
				<?php p($l->t('Installed package: %1$s, build %2$s', [$deploymentVersion['packageName'], $deploymentVersion['buildNumber']])); ?>
			<?php } ?>
		</p>
	</form>

	<p id="butterfly-settings-message" role="status" aria-live="polite"></p>
</div>
