<?php
namespace OCA\Spacedeck\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\Settings\ISettings;

use OCA\Spacedeck\AppInfo\Application;

class Admin implements ISettings {

	/**
	 * @var IConfig
	 */
	private $config;
	/**
	 * @var IInitialState
	 */
	private $initialStateService;

	public function __construct(
						string $appName,
						IConfig $config,
						IInitialState $initialStateService,
						?string $userId) {
		$this->appName = $appName;
		$this->config = $config;
		$this->initialStateService = $initialStateService;
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		$useLocalSpacedeck = $this->config->getAppValue(Application::APP_ID, 'use_local_spacedeck', '1') === '1';
		$baseUrl = $this->config->getAppValue(Application::APP_ID, 'base_url', '');
		$apiToken = $this->config->getAppValue(Application::APP_ID, 'api_token', Application::DEFAULT_SPACEDECK_API_KEY);
		$dataCopied = $this->config->getAppValue(Application::APP_ID, 'spacedeck_data_copied', '0') === '1';

		$adminConfig = [
			'use_local_spacedeck' => $useLocalSpacedeck,
			'base_url' => $baseUrl,
			'api_token' => $apiToken,
			'spacedeck_data_copied' => $dataCopied,
		];
		$this->initialStateService->provideInitialState('admin-config', $adminConfig);
		return new TemplateResponse(Application::APP_ID, 'adminSettings');
	}

	public function getSection(): string {
		return 'connected-accounts';
	}

	public function getPriority(): int {
		return 10;
	}
}
