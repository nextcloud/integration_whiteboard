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

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use OCP\IConfig;

use OCA\Spacedeck\Service\SpacedeckAPIService;
use OCA\Spacedeck\AppInfo\Application;

require_once __DIR__ . '/../constants.php';

class CleanupSpacedeck extends Command {

    protected $output;

    public function __construct(SpacedeckAPIService $apiService,
                                IConfig $config) {
        parent::__construct();
        $this->config = $config;
        $this->apiService = $apiService;
    }

    protected function configure() {
        $this->setName('integration_whiteboard:cleanup-spacedeck')
            ->setDescription('Cleanup Spacedeck storage and database');
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        // TODO find a way to setup the filesystem without any user ID
        // for the moment, cleanup will fail
        // \OC_Util::setupFS('');
		$apiToken = $this->config->getAppValue(Application::APP_ID, 'api_token', DEFAULT_SPACEDECK_API_KEY);
		$apiToken = $apiToken ?: DEFAULT_SPACEDECK_API_KEY;
		$baseUrl = $this->config->getAppValue(Application::APP_ID, 'base_url', DEFAULT_SPACEDECK_URL);
		$baseUrl = $baseUrl ?: DEFAULT_SPACEDECK_URL;

        $result = $this->apiService->cleanupSpacedeckStorage($baseUrl, $apiToken);
        if (isset($result['error'])) {
            $output->writeln('[ERROR] ' . $result['error']);
            return 1;
        } elseif (isset($result['actions'])) {
            foreach ($result['actions'] as $action) {
                $output->writeln($action);
            }
        }
        return 0;
    }
}
