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

use OCA\Spacedeck\AppInfo\Application;

require __DIR__ . '/../../vendor/autoload.php';

class SpacedeckWebsocketService {

	private $l10n;
	private $logger;

	public function __construct (string $appName,
								LoggerInterface $logger,
								IL10N $l10n,
								IConfig $config) {
		$this->appName = $appName;
		$this->l10n = $l10n;
		$this->logger = $logger;
		$this->config = $config;
	}

	public function proxySocket() {
		\Ratchet\Client\connect('ws://localhost:9666/socket')->then(function($conn) {
			$conn->on('message', function($msg) use ($conn) {
				error_log("SOSOSOSO Received: {$msg}\n");
				$conn->close();
			});

			$auth = [
				"action" => "auth",
				"auth_token" => "",
				"editor_auth" => "93a0425",
				"editor_name" => "",
				"space_id" => "8aeb28d0-5f1e-45bb-ba26-43d4d9801b95",
			];
			$conn->send(json_encode($auth));
			// $conn->send('Hello World!');
		}, function ($e) {
			error_log("Could not connect: {$e->getMessage()}\n");
		});
		sleep(10);
	}

	public function proxySocket2() {
		$loop = \React\EventLoop\Factory::create();
		$reactConnector = new \React\Socket\Connector($loop, [
			'dns' => '8.8.8.8',
			'timeout' => 10
		]);
		$connector = new \Ratchet\Client\Connector($loop, $reactConnector);

		error_log('BEGINNNNN');
		// $connector('ws://localhost:9666/socket', ['protocol1', 'subprotocol2'], ['Origin' => 'http://localhost'])
		$connector('ws://localhost:9666/socket')
		->then(function(Ratchet\Client\WebSocket $conn) {
			$conn->on('message', function(\Ratchet\RFC6455\Messaging\MessageInterface $msg) use ($conn) {
				// echo "Received: {$msg}\n";
				error_log("SOSOSOSO Received: {$msg}\n");
				// $conn->close();
			});

			$conn->on('close', function($code = null, $reason = null) {
				error_log("Connection closed ({$code} - {$reason})\n");
			});

			$auth = [
				"action" => "auth",
				"auth_token" => "",
				"editor_auth" => "93a0425",
				"editor_name" => "",
				"space_id" => "8aeb28d0-5f1e-45bb-ba26-43d4d9801b95",
			];
			$conn->send(json_encode($auth));
		}, function(\Exception $e) use ($loop) {
			error_log("Could not connect: {$e->getMessage()}\n");
			$loop->stop();
		});

		error_log('ENDDDDDD');
		$loop->run();
		error_log('ENDDDDDD222222');
	}
}
