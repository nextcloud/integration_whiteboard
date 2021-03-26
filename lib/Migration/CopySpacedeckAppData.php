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

use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use OCP\ILogger;
use OCP\IConfig;

use OCA\Spacedeck\AppInfo\Application;
use OCA\Spacedeck\Service\SpacedeckBundleService;

class CopySpacedeckAppData implements IRepairStep {
	protected $logger;
	private $customMimetypeMapping;

	public function __construct(ILogger $logger,
								SpacedeckBundleService $service,
								IConfig $config) {
		$this->logger = $logger;
		$this->service = $service;
		$this->config = $config;
	}

	public function getName() {
		return 'Copy Spacedeck package data';
	}

	public function run(IOutput $output) {
		$this->logger->info('Copying Spacedeck data...');

		$this->service->killSpacedeck();
		try {
			$this->service->copySpacedeckData();
			$this->config->setAppValue(Application::APP_ID, 'spacedeck_data_copied', '1');
			$this->logger->info('Spacedeck data successfully copied!');
		} catch (Exception | Throwable $e) {
			$this->config->setAppValue(Application::APP_ID, 'spacedeck_data_copied', '0');
			$this->logger->warning('Impossible to copy Spacedeck data');
		}
	}
}