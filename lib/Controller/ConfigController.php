<?php
/**
 * Nextcloud - spacedeck
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2020
 */

namespace OCA\Spacedeck\Controller;

use OCP\IConfig;
use Psr\Log\LoggerInterface;

use OCP\IRequest;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Controller;

use OCA\Spacedeck\Service\SpacedeckAPIService;
use OCA\Spacedeck\AppInfo\Application;

class ConfigController extends Controller {


	private $userId;
	private $config;
	private $dbtype;

	public function __construct($AppName,
								IRequest $request,
								IConfig $config,
								LoggerInterface $logger,
								SpacedeckAPIService $spacedeckAPIService,
								?string $userId) {
		parent::__construct($AppName, $request);
		$this->appName = $AppName;
		$this->userId = $userId;
		$this->config = $config;
		$this->logger = $logger;
		$this->spacedeckAPIService = $spacedeckAPIService;
	}

	/**
	 * set admin config values
	 *
	 * @param array $values
	 * @return DataResponse
	 */
	public function setAdminConfig(array $values): DataResponse {
		foreach ($values as $key => $value) {
			$this->config->setAppValue(Application::APP_ID, $key, $value);
		}
		$response = new DataResponse(1);
		return $response;
	}
}
