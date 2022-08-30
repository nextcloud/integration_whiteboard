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

use OCA\Spacedeck\AppInfo\Application;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

use OCA\Spacedeck\Service\SpacedeckBundleService;
use Psr\Log\LoggerInterface;

class StopSpacedeck implements IRepairStep {

	/**
	 * @var LoggerInterface
	 */
	private $logger;
	/**
	 * @var SpacedeckBundleService
	 */
	private $bundleService;

	public function __construct(LoggerInterface $logger,
								SpacedeckBundleService $bundleService) {
		$this->logger = $logger;
		$this->bundleService = $bundleService;
	}

	public function getName() {
		return 'Stop Spacedeck';
	}

	public function run(IOutput $output) {
		$this->logger->info('Stopping Spacedeck...');

		$stopped = $this->bundleService->killSpacedeck();
		if ($stopped) {
			$this->logger->info('Spacedeck stopped!', ['app' => Application::APP_ID]);
		} else {
			$this->logger->warning('Failed to stop Spacedeck', ['app' => Application::APP_ID]);
		}
	}
}
