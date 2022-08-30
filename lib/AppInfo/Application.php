<?php
/**
 * Nextcloud - Spacedeck
 *
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2020
 */

namespace OCA\Spacedeck\AppInfo;

use OCA\Spacedeck\Listener\AddContentSecurityPolicyListener;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\Security\CSP\AddContentSecurityPolicyEvent;
use OCP\Util;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;

use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\Files_Sharing\Event\BeforeTemplateRenderedEvent;
use OCA\Viewer\Event\LoadViewer;

/**
 * Class Application
 *
 * @package OCA\Spacedeck\AppInfo
 */
class Application extends App implements IBootstrap {

	public const APP_ID = 'integration_whiteboard';
	public const DEFAULT_SPACEDECK_URL =  'http://localhost:9666';
	public const DEFAULT_SPACEDECK_API_KEY = 'super_secret_token';

	public const PERMISSIONS = [
		'none' => 0,
		'view' => 1,
		'edit' => 2,
	];

	public const TOKEN_TYPES = [
		'user' => 0,
		'share' => 1,
	];

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

		$config = $container->get(IConfig::class);

		$initialState = $container->get(IInitialState::class);
		$initialState->provideLazyInitialState('use_local_spacedeck', function () use ($config) {
			return $config->getAppValue(self::APP_ID, 'use_local_spacedeck', '1');
		});
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

	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(AddContentSecurityPolicyEvent::class, AddContentSecurityPolicyListener::class);
	}

	public function boot(IBootContext $context): void {
	}
}

