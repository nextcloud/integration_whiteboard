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

class RegisterMimeType implements IRepairStep {
	protected $logger;
	private $customMimetypeMapping;

	public function __construct(ILogger $logger) {
		$this->logger = $logger;
	}

	public function getName() {
		return 'Register MIME type for Spacedeck';
	}

	public function run(IOutput $output) {
		$this->logger->info('Registering the Spacedeck mimetype...');

		$mimetypeMapping = [
			'spd' => ['application/spacedeck']
		];

		$mimetypeMappingFile = \OC::$configDir . 'mimetypemapping.json';

		if (file_exists($mimetypeMappingFile)) {
			$existingMimetypeMapping = json_decode(file_get_contents($mimetypeMappingFile), true);
			$mimetypeMapping = array_merge($existingMimetypeMapping, $mimetypeMapping);
		}

		file_put_contents($mimetypeMappingFile, json_encode($mimetypeMapping, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

		$this->logger->info('The Spacedeck mimetype was successfully registered.');
	}
}