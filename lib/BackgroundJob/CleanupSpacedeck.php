<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2021 Julien Veyssier <eneiluj@posteo.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Spacedeck\BackgroundJob;

use OCA\Spacedeck\Service\SessionService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

use OCA\Spacedeck\Service\SpacedeckAPIService;
use OCA\Spacedeck\AppInfo\Application;

require_once __DIR__ . '/../constants.php';

/**
 * Class CleanupSpacedeck
 *
 * @package OCA\Spacedeck\BackgroundJob
 */
class CleanupSpacedeck extends TimedJob {

	/** @var IConfig */
	protected $config;

	/** @var SpacedeckAPIService */
	protected $apiService;

	/** @var LoggerInterface */
	protected $logger;
	/**
	 * @var SessionService
	 */
	private $sessionService;

	public function __construct(ITimeFactory $time,
								IConfig $config,
								SpacedeckAPIService $apiService,
								SessionService $sessionService,
								LoggerInterface $logger) {
		parent::__construct($time);

		// Every 24 hours
		$this->setInterval(60 * 60 * 24);

		$this->config = $config;
		$this->apiService = $apiService;
		$this->logger = $logger;
		$this->sessionService = $sessionService;
	}

	protected function run($argument): void {
		$useLocalSpacedeck = $this->config->getAppValue(Application::APP_ID, 'use_local_spacedeck', '1') === '1';
		if ($useLocalSpacedeck) {
			$apiToken = $this->config->getAppValue(Application::APP_ID, 'api_token', DEFAULT_SPACEDECK_API_KEY);
			$apiToken = $apiToken ?: DEFAULT_SPACEDECK_API_KEY;
			$baseUrl = $this->config->getAppValue(Application::APP_ID, 'base_url', DEFAULT_SPACEDECK_URL);
			$baseUrl = $baseUrl ?: DEFAULT_SPACEDECK_URL;

			$result = $this->apiService->cleanupSpacedeckStorage($baseUrl, $apiToken);
			if (isset($result['error'])) {
				$this->logger->error('[ERROR] ' . $result['error']);
			} elseif (isset($result['actions'])) {
				foreach ($result['actions'] as $action) {
					$this->logger->info($action);
				}
			}
		}
		$this->sessionService->cleanupSessions();
	}
}
