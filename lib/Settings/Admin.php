<?php
namespace OCA\Spacedeck\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\IL10N;
use OCP\IConfig;
use OCP\Settings\ISettings;
use OCP\Util;
use OCP\IURLGenerator;
use OCP\IInitialStateService;

use OCA\Spacedeck\AppInfo\Application;

class Admin implements ISettings {

	private $request;
	private $config;
	private $dataDirPath;
	private $urlGenerator;
	private $l;

	public function __construct(
						string $appName,
						IL10N $l,
						IRequest $request,
						IConfig $config,
						IURLGenerator $urlGenerator,
						IInitialStateService $initialStateService,
						$userId) {
		$this->appName = $appName;
		$this->urlGenerator = $urlGenerator;
		$this->request = $request;
		$this->l = $l;
		$this->config = $config;
		$this->initialStateService = $initialStateService;
		$this->userId = $userId;
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		$apiToken = $this->config->getAppValue(Application::APP_ID, 'api_token', '');
		$baseUrl = $this->config->getAppValue(Application::APP_ID, 'base_url', '');

		$adminConfig = [
			'api_token' => $apiToken,
			'base_url' => $baseUrl,
		];
		$this->initialStateService->provideInitialState($this->appName, 'admin-config', $adminConfig);
		return new TemplateResponse(Application::APP_ID, 'adminSettings');
	}

	public function getSection(): string {
		return 'connected-accounts';
	}

	public function getPriority(): int {
		return 10;
	}
}
