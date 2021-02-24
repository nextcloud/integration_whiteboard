<?php
/**
 * Nextcloud - Spacedeck
 *
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2020
 */

namespace OCA\Spacedeck\AppInfo;

use OCP\IContainer;
use OCP\Util;
use OCP\AppFramework\App;
use OCP\AppFramework\IAppContainer;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;

use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\Files_Sharing\Event\BeforeTemplateRenderedEvent;
use OCA\Viewer\Event\LoadViewer;

use OC\Security\CSP\ContentSecurityPolicy;

require_once __DIR__ . '/../constants.php';

/**
 * Class Application
 *
 * @package OCA\Spacedeck\AppInfo
 */
class Application extends App implements IBootstrap {

	public const APP_ID = 'integration_whiteboard';

	/**
	 * Constructor
	 *
	 * @param array $urlParams
	 */
	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);

		$container = $this->getContainer();
		$server = $container->getServer();
		$eventDispatcher = $server->getEventDispatcher();
		$this->addPrivateListeners($eventDispatcher);

		$spacedeckUrl = $container->getServer()->getConfig()->getAppValue(self::APP_ID, 'base_url', DEFAULT_SPACEDECK_URL);
		if ($spacedeckUrl !== DEFAULT_SPACEDECK_URL) {
			$this->updateCSP($spacedeckUrl);
		} else {
			$this->updateCSP();
		}
	}

	protected function addPrivateListeners($eventDispatcher) {
		$eventDispatcher->addListener(LoadAdditionalScriptsEvent::class, function () {
			$this->loadFilesScripts();
		});
		$eventDispatcher->addListener(BeforeTemplateRenderedEvent::class, function () {
			$this->loadFilesScripts();
		});
		$eventDispatcher->addListener(LoadViewer::class, function () {
			Util::addscript(self::APP_ID, self::APP_ID . '-viewer');
		});
	}

	private function loadFilesScripts() {
		Util::addscript(self::APP_ID, self::APP_ID . '-filetypes');
		Util::addStyle(self::APP_ID, 'style');
	}

	/**
	 * this might have been necessary in the past
	 */
	public function updateCSP(string $url = '') {
		$container = $this->getContainer();

		$cspManager = $container->getServer()->getContentSecurityPolicyManager();
		$policy = new ContentSecurityPolicy();
		$policy->addAllowedFrameDomain('\'self\'');
		$policy->addAllowedFrameAncestorDomain('\'self\'');
		if ($url) {
			$policy->addAllowedFrameDomain($url);
		}

		$cspManager->addDefaultPolicy($policy);
	}

	public function register(IRegistrationContext $context): void {
	}

	public function boot(IBootContext $context): void {
	}
}

