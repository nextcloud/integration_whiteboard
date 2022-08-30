<?php
/**
 * @author Julien Veyssier <eneiluj@posteo.net>
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

namespace OCA\Spacedeck\Migration;

use Exception;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use OCP\IConfig;

use OCA\Spacedeck\AppInfo\Application;
use OCA\Spacedeck\Service\SpacedeckBundleService;
use Psr\Log\LoggerInterface;
use Throwable;

class CopySpacedeckAppData implements IRepairStep {
	/**
	 * @var LoggerInterface
	 */
	private $logger;
	/**
	 * @var SpacedeckBundleService
	 */
	private $bundleService;
	/**
	 * @var IConfig
	 */
	private $config;

	public function __construct(LoggerInterface $logger,
								SpacedeckBundleService $bundleService,
								IConfig $config) {
		$this->logger = $logger;
		$this->bundleService = $bundleService;
		$this->config = $config;
	}

	public function getName() {
		return 'Copy Spacedeck package data';
	}

	public function run(IOutput $output) {
		$this->logger->info('Copying Spacedeck data...');

		$this->bundleService->killSpacedeck();
		try {
			$this->bundleService->copySpacedeckData();
			$this->config->setAppValue(Application::APP_ID, 'spacedeck_data_copied', '1');
			$this->logger->info('Spacedeck data successfully copied!', ['app' => Application::APP_ID]);
		} catch (Exception | Throwable $e) {
			$this->config->setAppValue(Application::APP_ID, 'spacedeck_data_copied', '0');
			$this->logger->warning('Impossible to copy Spacedeck data', ['app' => Application::APP_ID]);
		}
	}
}
