<?php
/**
 * Nextcloud - spacedeck
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier
 * @copyright Julien Veyssier 2020
 */

namespace OCA\Spacedeck\Service;

use OCP\IL10N;
use Psr\Log\LoggerInterface;
use OCP\IConfig;
use OCP\Files\IRootFolder;
use OCP\Files\FileInfo;
use OCP\Files\Node;
use OCP\Lock\LockedException;

use OCA\Spacedeck\AppInfo\Application;

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
}

class SpacedeckBundleService {

	private $l10n;
	private $logger;

	public function __construct (string $appName,
								IRootFolder $root,
								LoggerInterface $logger,
								IL10N $l10n,
								IConfig $config) {
		$this->appName = $appName;
		$this->l10n = $l10n;
		$this->logger = $logger;
		$this->config = $config;
		$this->root = $root;

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

	private function spacedeckIsRunning(): ?int {
		$pids = $this->getSpacedeckPids();
		return (count($pids) > 0) ? $pids[0] : null;
	}

	private function killSpacedeck(): ?int {
		$cmd = 'ps x -o user,pid,args';
		$cmdResult = $this->runCommand($cmd);
		if ($cmdResult && isset($cmdResult['return_code']) && $cmdResult['return_code'] === 0) {
			$lines = explode("\n", $cmdResult['stdout']);
			foreach ($lines as $l) {
				if (preg_match('/spacedeck.*\.bin/i', $l)) {
					$items = preg_split('/\s+/', $l);
					if (count($items) > 1 && is_numeric($items[1])) {
						return (int) $items[1];
					}
				}
			}
		}
		return null;
	}

	public function launchSpacedeck(): ?int {
		$pid = $this->spacedeckIsRunning();
		if (!$pid) {
			error_log('NOT RUNNING   ');
			$binaryDirPath = $this->appDataDirPath;
			$binaryName = 'spacedeck.pkg.bin';
			$outputName = 'spacedeck.log';
			$cmd = sprintf('cd "%s" ; nice -n19 ./%s > %s 2>&1 & echo $!', $binaryDirPath, $binaryName, $outputName);
			error_log('!! CMD : ' . $cmd . ' !!');
			$cmdResult = $this->runCommand($cmd);
			if (!is_null($cmdResult) && $cmdResult['return_code'] === 0 && is_numeric($cmdResult['stdout'] ?? 0)) {
				sleep(5);
				return (int) $cmdResult['stdout'];
			} else {
				error_log('1111');
				return null;
			}
		} else {
			error_log('ALREADY RUNNING   ');
			return $pid;
		}
	}

	public function copySpacedeckData() {
		if (is_dir($this->appDataDirPath . '.bak')) {
			// just in case
			recursiveDelete($this->appDataDirPath . '.bak');
		}
		// temporary backup of old spacedeck
		if (is_dir($this->appDataDirPath)) {
			rename($this->appDataDirPath, $this->appDataDirPath . '.bak');
		}

		// copy the one from the app directory
		$newSpacedeckDataPath = dirname(__DIR__, 2) . '/spacedeck';
		recursiveCopy($newSpacedeckDataPath, $this->appDataDirPath);
		// change rights of binaries
		chmod($this->appDataDirPath . '/spacedeck.nexe.bin', 0700);
		chmod($this->appDataDirPath . '/spacedeck.pkg.bin', 0700);

		// keep old storage and database
		if (is_dir($this->appDataDirPath . '.bak')) {
			if (is_dir($this->appDataDirPath . '.bak/storage')) {
				if (is_dir($this->appDataDirPath . '/storage')) {
					recursiveDelete($this->appDataDirPath . '/storage');
				}
				recursiveCopy($this->appDataDirPath . '.bak/storage', $this->appDataDirPath . '/storage');
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
}
