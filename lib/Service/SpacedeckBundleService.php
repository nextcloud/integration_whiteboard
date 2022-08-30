<?php
/**
 * Nextcloud - spacedeck
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier
 * @copyright Julien Veyssier 2021
 */

namespace OCA\Spacedeck\Service;

use OCP\IL10N;
use Psr\Log\LoggerInterface;
use OCP\IConfig;
use OCP\Files\IRootFolder;
use OCP\IURLGenerator;

function recursiveCopy($src, $dst) {
	$dir = opendir($src);
	@mkdir($dst);
	while (($file = readdir($dir)) !== false) {
		if (($file !== '.') && ($file !== '..')) {
			if (is_dir($src . '/' . $file)) {
				recursiveCopy($src . '/' . $file, $dst . '/' . $file);
			} else {
				copy($src . '/' . $file, $dst . '/' . $file);
			}
		}
	}
	closedir($dir);
}

function recursiveDelete($str) {
	if (is_file($str)) {
		return @unlink($str);
	} elseif (is_dir($str)) {
		$scan = glob(rtrim($str, '/') . '/{,.}*', GLOB_BRACE);
		foreach ($scan as $index => $path) {
			if (!preg_match('/.*\/\.+$/', $path)) {
				recursiveDelete($path);
			}
		}
		return @rmdir($str);
	}
	return true;
}

class SpacedeckBundleService {

	/**
	 * @var IURLGenerator
	 */
	private $urlGenerator;
	/**
	 * @var IConfig
	 */
	private $config;

	public function __construct (string $appName,
								 IURLGenerator $urlGenerator,
								 IConfig $config) {
		$this->urlGenerator = $urlGenerator;
		$this->config = $config;
		$dataDirPath = $this->config->getSystemValue('datadirectory');
		$instanceId = $this->config->getSystemValue('instanceid');
		$this->appDataDirPath = $dataDirPath . '/appdata_' . $instanceId . '/spacedeck';
	}

	/**
	 * Launch a command, wait until it ends and return outputs and return code
	 *
	 * @param string $cmd command string
	 * @return ?array outputs and return code, null if process launch failed
	 */
	private function runCommand(string $cmd): ?array {
		$descriptorspec = [fopen('php://stdin', 'r'), ['pipe', 'w'], ['pipe', 'w']];
		$process = proc_open($cmd, $descriptorspec, $pipes);
		if ($process) {
			$output = stream_get_contents($pipes[1]);
			$errorOutput = stream_get_contents($pipes[2]);
			fclose($pipes[1]);
			fclose($pipes[2]);
			$returnCode = proc_close($process);
			return [
				'stdout' => trim($output),
				'stderr' => trim($errorOutput),
				'return_code' => $returnCode,
			];
		}
		return null;
	}

	/**
	 * Get a list of Spacedeck process IDs that were launched by the webserver user
	 *
	 * @return array list of pids
	 */
	private function getSpacedeckPids(): array {
		$pids = [];
		$cmd = 'ps x -o user,pid,args';
		$cmdResult = $this->runCommand($cmd);
		if ($cmdResult && isset($cmdResult['return_code']) && $cmdResult['return_code'] === 0) {
			$lines = explode("\n", $cmdResult['stdout']);
			foreach ($lines as $l) {
				if (preg_match('/spacedeck.*\.bin/i', $l)) {
					$items = preg_split('/\s+/', $l);
					if (count($items) > 1 && is_numeric($items[1])) {
						$pids[] = (int) $items[1];
					}
				}
			}
		}
		return $pids;
	}

	/**
	 * Check if there is at least one spacedeck process (launched by the webserver user) running
	 *
	 * @return ?int one spacedeck pid, null if there is none
	 */
	private function spacedeckIsRunning(): ?int {
		$pids = $this->getSpacedeckPids();
		return (count($pids) > 0) ? $pids[0] : null;
	}

	/**
	 * Kill all Spacedeck processes
	 *
	 * @return bool true on success, false if there is still a process running
	 */
	public function killSpacedeck(): bool {
		$pids = $this->getSpacedeckPids();
		foreach ($pids as $pid) {
			$this->killOneSpacedeck($pid);
		}
		return !$this->spacedeckIsRunning();
	}

	/**
	 * Utility to kill a process
	 *
	 * @param int $pid the process ID to kill
	 * @return bool if it was successfully killed
	 */
	private function killOneSpacedeck(int $pid): bool {
		// kill
		$cmdResult = $this->runCommand(sprintf('kill -9 %d', $pid));
		return !is_null($cmdResult) && $cmdResult['return_code'] === 0;
	}

	/**
	 * Launch Spacedeck binary if it's not running already
	 *
	 * @param bool $usesIndexDotPhp is this instance accessed with index.php?
	 * @return ?int the pid of the created/existing process, null if there was a problem
	 */
	public function launchSpacedeck(bool $usesIndexDotPhp): ?int {
		$pid = $this->spacedeckIsRunning();
		$indexDotPhpMismastch = ($usesIndexDotPhp !== $this->configUsesIndexDotPhp());
		// if it's running but config base URL is incorrect, kill it
		if ($pid && $indexDotPhpMismastch) {
			$this->killSpacedeck();
			$pid = 0;
		}
		if (!$pid) {
			$this->setBaseUrl($usesIndexDotPhp);
			$binaryDirPath = $this->appDataDirPath;
			$binaryName = 'spacedeck.nexe.bin';
			$outputName = 'spacedeck.log';
			$cmd = sprintf('cd "%s" ; nice -n19 ./%s > %s 2>&1 & echo $!', $binaryDirPath, $binaryName, $outputName);
			$cmdResult = $this->runCommand($cmd);
			if (!is_null($cmdResult) && $cmdResult['return_code'] === 0 && is_numeric($cmdResult['stdout'] ?? 0)) {
				sleep(5);
				return (int) $cmdResult['stdout'];
			} else {
				return null;
			}
		} else {
			return $pid;
		}
	}

	/**
	 * On app install/upgrade, the new spacedeck sources+bin is copied in appdata
	 */
	public function copySpacedeckData(): void {
		$newSpacedeckDataPath = dirname(__DIR__, 2) . '/data/spacedeck';
		// check if the app contains spacedeck
		if (!is_dir($newSpacedeckDataPath)) {
			return;
		}

		if (is_dir($this->appDataDirPath . '.bak')) {
			// just in case
			recursiveDelete($this->appDataDirPath . '.bak');
		}
		// temporary backup of old spacedeck
		if (is_dir($this->appDataDirPath)) {
			rename($this->appDataDirPath, $this->appDataDirPath . '.bak');
		}

		// copy the one from the app directory
		recursiveCopy($newSpacedeckDataPath, $this->appDataDirPath);
		// change rights of binaries
		chmod($this->appDataDirPath . '/spacedeck.nexe.bin', 0700);
		// chmod($this->appDataDirPath . '/spacedeck.pkg.bin', 0700);

		// keep old storage and database
		if (is_dir($this->appDataDirPath . '.bak')) {
			if (is_dir($this->appDataDirPath . '.bak/storage')) {
				if (is_dir($this->appDataDirPath . '/storage')) {
					recursiveDelete($this->appDataDirPath . '/storage');
				}
				rename($this->appDataDirPath . '.bak/storage', $this->appDataDirPath . '/storage');
			}
			if (file_exists($this->appDataDirPath . '.bak/database.sqlite')) {
				if (file_exists($this->appDataDirPath . '/database.sqlite')) {
					unlink($this->appDataDirPath . '/database.sqlite');
				}
				copy($this->appDataDirPath . '.bak/database.sqlite', $this->appDataDirPath . '/database.sqlite');
			}
			recursiveDelete($this->appDataDirPath . '.bak');
		}
	}

	/**
	 * Check if congif base URL value contains index.php
	 *
	 * @return bool
	 */
	private function configUsesIndexDotPhp(): bool {
		$configPath = $this->appDataDirPath . '/config/default.json';
		if (file_exists($configPath)) {
			$config = json_decode(file_get_contents($configPath), true);
			return preg_match('/index\.php\/apps\/integration_whiteboard/', $config['endpoint']) === 1;
		}
		return false;
	}

	/**
	 * Add or remove index.php to spacedeck config baseUrl
	 * @param bool $usesIndexDotPhp
	 */
	private function setBaseUrl(bool $usesIndexDotPhp): void {
		$instanceBaseUrl = rtrim($this->urlGenerator->getBaseUrl(), '/');
		$configPath = $this->appDataDirPath . '/config/default.json';
		if (file_exists($configPath)) {
			$config = json_decode(file_get_contents($configPath), true);
			$config['endpoint'] = $usesIndexDotPhp
				? $instanceBaseUrl . '/index.php/apps/integration_whiteboard/proxy'
				: $instanceBaseUrl . '/apps/integration_whiteboard/proxy';
			file_put_contents($configPath, json_encode($config, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
		}
	}

	/**
	 * Delete all files related to a space in Spacedeck storage
	 *
	 * @param string $spaceId
	 * @return void
	 */
	public function deleteSpaceStorage(string $spaceId): void {
		$spaceStoragePath = $this->appDataDirPath . '/storage/my_spacedeck_bucket/s' . $spaceId;
		if (is_dir($spaceStoragePath)) {
			recursiveDelete($spaceStoragePath);
		}
	}

	/**
	 * For a given space, delete all artifact files that are not present in the database
	 *
	 * @param string $spaceId
	 * @param array $dbArtifactIds list of artifact IDs obtained from Spacedeck API
	 * @return array list of deleted artifact IDs
	 */
	public function cleanArtifactStorage(string $spaceId, array $dbArtifactIds): array {
		$idsToDelete = [];
		$spaceStoragePath = $this->appDataDirPath . '/storage/my_spacedeck_bucket/s' . $spaceId;

		if (is_dir($spaceStoragePath)) {
			$dir = opendir($spaceStoragePath);
			while (($elem = readdir($dir)) !== false) {
				if (($elem !== '.') && ($elem !== '..') && is_dir($spaceStoragePath . '/' . $elem) && preg_match('/^a/', $elem) === 1) {
					$fsId = preg_replace('/^a/', '', $elem);
					if (!in_array($fsId, $dbArtifactIds)) {
						$idsToDelete[] = $fsId;
					}
				}
			}
			closedir($dir);

			foreach ($idsToDelete as $id) {
				$artifactStoragePath = $spaceStoragePath . '/a' . $id;
				if (is_dir($artifactStoragePath)) {
					recursiveDelete($artifactStoragePath);
				}
			}
		}
		return $idsToDelete;
	}
}
