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

use OC\Security\CSP\ContentSecurityPolicy;

use OCA\Spacedeck\Notification\Notifier;

/**
 * Class Application
 *
 * @package OCA\Spacedeck\AppInfo
 */
class Application extends App implements IBootstrap {

	public const APP_ID = 'integration_spacedeck';

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
		$this->updateCSP();
	}

	protected function addPrivateListeners($eventDispatcher) {
		$eventDispatcher->addListener(LoadAdditionalScriptsEvent::class, function () {
			$this->loadScripts();
		});
		$eventDispatcher->addListener(BeforeTemplateRenderedEvent::class, function () {
			$this->loadScripts();
		});
	}

	private function loadScripts() {
		Util::addscript(self::APP_ID, self::APP_ID . '-filetypes');
		Util::addStyle(self::APP_ID, 'style');
	}

	public function updateCSP() {
		$container = $this->getContainer();

		$spacedeckUrl = $container->getServer()->getConfig()->getAppValue(self::APP_ID, 'base_url', '');
		if ($spacedeckUrl === '') {
			return;
		}
		$cspManager = $container->getServer()->getContentSecurityPolicyManager();
		$policy = new ContentSecurityPolicy();
		// $policy->addAllowedFrameDomain('\'self\'');
		$policy->addAllowedFrameDomain($spacedeckUrl);

		/**
		 * Dynamically add CSP for federated editing
		 */
		// $path = '';
		// try {
		// 	$path = $container->getServer()->getRequest()->getPathInfo();
		// } catch (\Exception $e) {}
		// if (strpos($path, '/apps/files') === 0 && $container->getServer()->getAppManager()->isEnabledForUser('federation')) {
		// 		/** @var FederationService $federationService */
		// 		$federationService = \OC::$server->query(FederationService::class);

		// 		// Always add trusted servers on global scale
		// 		/** @var IConfig $globalScale */
		// 		$globalScale = $container->query(IConfig::class);
		// 		if ($globalScale->isGlobalScaleEnabled()) {
		// 				$trustedList = \OC::$server->getConfig()->getSystemValue('gs.trustedHosts', []);
		// 				foreach ($trustedList as $server) {
		// 						$this->addTrustedRemote($policy, $server);
		// 				}
		// 		}
		// 		$remoteAccess = $container->getServer()->getRequest()->getParam('richdocuments_remote_access');

		// 		if ($remoteAccess && $federationService->isTrustedRemote($remoteAccess)) {
		// 				$this->addTrustedRemote($policy, $remoteAccess);
		// 		}
		// }

		$cspManager->addDefaultPolicy($policy);
	}

	public function register(IRegistrationContext $context): void {
	}

	public function boot(IBootContext $context): void {
	}
}

