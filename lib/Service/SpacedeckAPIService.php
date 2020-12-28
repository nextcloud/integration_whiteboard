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
use OCP\Http\Client\IClientService;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\ConnectException;

use OCA\Spacedeck\AppInfo\Application;

class SpacedeckAPIService {

	private $l10n;
	private $logger;

	/**
	 * Service to make requests to Spacedeck API
	 */
	public function __construct (string $appName,
								LoggerInterface $logger,
								IL10N $l10n,
								IConfig $config,
								IClientService $clientService) {
		$this->appName = $appName;
		$this->l10n = $l10n;
		$this->logger = $logger;
		$this->config = $config;
		$this->clientService = $clientService;
		$this->client = $clientService->newClient();
	}

	public function saveSpaceToFile(string $apiToken, string $baseUrl, string $userId, string $space_id, int $file_id): array {
		// get db content !!!OR!!! directly get json artifacts and json space
		// endpoints:
		// * GET spaces/space_id
		// * GET spaces/space_id/artifacts
		// write { space: space_response, artifacts: artifacts_response }
	}

	public function loadSpaceFromFile(string $apiToken, string $baseUrl, string $userId, int $file_id): array {
		// load file json content
		// check if space_id exists: GET spaces/space_id

		// YES: return response -> edit_hash and space_id
		// URL is like http://localhost:9666/spaces/1a3f64fc-6beb-4705-af74-c5908155c38a?spaceAuth=ea65276

		// NO:
		//  * create new space, get its ID and edit_hash
		//	* insert json content in space
		//     * for each artifact: POST spaces/space_id/artifacts with all stored artifact params
		//  * return edit_hash and space_id
		return [
			'space_id' => '',
			'edit_hash' => '',
		];
	}

	/**
	 * @param string $endPoint
	 * @param array $params
	 * @param string $method
	 * @return array
	 */
	private function request(string $base_url, string $endPoint, array $params = [], string $method = 'GET'): array {
		try {
			$url = $base_url . '/api/' . $endPoint;
			$options = [
				'headers' => [
					'X-Spacedeck-API-Token' => $apiToken,
					'User-Agent' => 'Nextcloud Spacedeck integration',
				],
			];

			if (count($params) > 0) {
				if ($method === 'GET') {
					$paramsContent = http_build_query($params);
					$url .= '?' . $paramsContent;
				} else {
					$options['headers']['Content-Type'] = 'application/json';
					$options['body'] = json_encode($params);
				}
			}

			if ($method === 'GET') {
				$response = $this->client->get($url, $options);
			} else if ($method === 'POST') {
				$response = $this->client->post($url, $options);
			} else if ($method === 'PUT') {
				$response = $this->client->put($url, $options);
			} else if ($method === 'DELETE') {
				$response = $this->client->delete($url, $options);
			}
			$body = $response->getBody();
			$respCode = $response->getStatusCode();

			if ($respCode >= 400) {
				return ['error' => 'Bad credentials'];
			} else {
				return json_decode($body, true);
			}
		} catch (ServerException | ClientException $e) {
			$response = $e->getResponse();
			$this->logger->warning('Spacedeck API error : '.$e->getMessage(), ['app' => $this->appName]);
			return ['error' => $e->getMessage()];
		}
	}
}
