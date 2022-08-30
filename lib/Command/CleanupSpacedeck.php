<?php

/**
 * Nextcloud - spacedeck
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2021
 */

namespace OCA\Spacedeck\Command;

use OCA\Spacedeck\Service\SessionService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use OCP\IConfig;

use OCA\Spacedeck\Service\SpacedeckAPIService;
use OCA\Spacedeck\AppInfo\Application;

class CleanupSpacedeck extends Command {

	/**
	 * @var SessionService
	 */
	private $sessionService;
	/**
	 * @var SpacedeckAPIService
	 */
	private $apiService;
	/**
	 * @var IConfig
	 */
	private $config;

	public function __construct(SpacedeckAPIService $apiService,
								SessionService $sessionService,
								IConfig $config) {
		parent::__construct();
		$this->sessionService = $sessionService;
		$this->apiService = $apiService;
		$this->config = $config;
	}

	protected function configure() {
		$this->setName('integration_whiteboard:cleanup-spacedeck')
			->setDescription('Cleanup Spacedeck storage and database');
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$this->sessionService->cleanupSessions();

		// TODO find a way to setup the filesystem without any user ID
		// for the moment, in cleanup command context, root FS is not available
		// and we have to iterate on all users to get all application/spacedeck files
		// \OC_Util::setupFS('');
		$useLocalSpacedeck = $this->config->getAppValue(Application::APP_ID, 'use_local_spacedeck', '1') === '1';
		if ($useLocalSpacedeck) {
			$apiToken = Application::DEFAULT_SPACEDECK_API_KEY;
			$baseUrl = $this->config->getAppValue(Application::APP_ID, 'base_url', Application::DEFAULT_SPACEDECK_URL);
			$baseUrl = $baseUrl ?: Application::DEFAULT_SPACEDECK_URL;

			$result = $this->apiService->cleanupSpacedeckStorage($baseUrl, $apiToken);
			if (isset($result['error'])) {
				$output->writeln('[ERROR] ' . $result['error']);
				return 1;
			} elseif (isset($result['actions'])) {
				foreach ($result['actions'] as $action) {
					$output->writeln($action);
				}
			}
		}
		return 0;
	}
}
