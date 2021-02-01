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
use OCP\Http\Client\IClientService;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\ConnectException;
use OCP\Http\Client\LocalServerException;

use OCA\Spacedeck\Service\SpacedeckBundleService;
use OCA\Spacedeck\AppInfo\Application;

require_once __DIR__ . '/../constants.php';

class SpacedeckAPIService {

	private $l10n;
	private $logger;

	/**
	 * Service to make requests to Spacedeck API
	 */
	public function __construct (string $appName,
								IRootFolder $root,
								LoggerInterface $logger,
								IL10N $l10n,
								IConfig $config,
								SpacedeckBundleService $spacedeckBundleService,
								IClientService $clientService) {
		$this->appName = $appName;
		$this->l10n = $l10n;
		$this->logger = $logger;
		$this->config = $config;
		$this->root = $root;
		$this->clientService = $clientService;
		$this->client = $clientService->newClient();
		$this->spacedeckBundleService = $spacedeckBundleService;
	}

	/**
	 * Save a space content in a file
	 *
	 * @param string $baseUrl
	 * @param string $apiToken
	 * @param ?string $userId
	 * @param string $space_id
	 * @param int $file_id
	 * @return array success state
	 */
	public function saveSpaceToFile(string $baseUrl, string $apiToken, ?string $userId, string $space_id, int $file_id): array {
		$targetFile = $this->getFileFromId($userId, $file_id);
		if ($targetFile) {
			try {
				$res = $targetFile->fopen('w');
			} catch (LockedException $e) {
				return ['error' => 'File is locked'];
			}

			// get db content !!!OR!!! directly get json artifacts and json space
			// endpoints:
			// * GET spaces/space_id
			// * GET spaces/space_id/artifacts
			// write { space: space_response, artifacts: artifacts_response }
			$space = $this->request($baseUrl, $apiToken, 'spaces/' . $space_id);
			if (isset($space['error'])) {
				return $space;
			}
			$artifacts = $this->request($baseUrl, $apiToken, 'spaces/' . $space_id . '/artifacts');
			if (isset($artifacts['error'])) {
				return $artifacts;
			}
			$content = [
				'space' => $space,
				'artifacts' => $artifacts,
			];
			$strContent = json_encode($content);

			// this produces a file version
			// $targetFile->putContent($strContent);
			fwrite($res, $strContent);
			fclose($res);
			return ['ok' => 1];
		} else {
			return ['error' => 'File does not exist'];
		}
	}

	/**
	 * Try to load the space from a file
	 * If the space exists, just return its ID and edit hash
	 * If the space does NOT exist, create it and load the file content
	 *
	 * @param string $baseUrl
	 * @param string $apiToken
	 * @param ?string $userId
	 * @param int $file_id
	 * @return array error or space information
	 */
	public function loadSpaceFromFile(string $baseUrl, string $apiToken, ?string $userId, int $file_id): array {
		if ($baseUrl === DEFAULT_SPACEDECK_URL) {
			$pid = $this->spacedeckBundleService->launchSpacedeck();
		}
		// load file json content
		$file = $this->getFileFromId($userId, $file_id);
		if (is_null($file)) {
			return ['error' => 'File does not exist'];
		}
		$fileContent = trim($file->getContent());

		// file is empty: create a space
		if (!$fileContent) {
			$newSpace = $this->createSpace($baseUrl, $apiToken, $userId, $file_id);
			if (is_null($newSpace)) {
				return ['error' => 'Impossible to create space'];
			}
			// write it to the file to update space_id
			$decoded['space']['_id'] = $newSpace['_id'];
			$decoded['space']['edit_hash'] = $newSpace['edit_hash'];
			$decoded['space']['edit_slug'] = $newSpace['edit_slug'];
			$decoded['space']['name'] = $newSpace['name'];
			$file->putContent(json_encode($decoded));
			return [
				'existed' => false,
				'base_url' => $baseUrl,
				'space_id' => $newSpace['_id'],
				'space_name' => $newSpace['name'],
				'edit_hash' => $newSpace['edit_hash'],
			];
		}

		// file is not empty, try to load it
		try {
			$decoded = json_decode($fileContent, true);
		} catch (Exception | Throwable $e) {
			return ['error' => 'File is invalid, impossible to parse JSON'];
		}
		if (isset($decoded['space'], $decoded['space']['_id'])) {
			$spaceId = $decoded['space']['_id'];
		} else {
			return ['error' => 'File is invalid, no "_id"'];
		}
		// check if space_id exists: GET spaces/space_id
		try {
			$space = $this->request($baseUrl, $apiToken, 'spaces/' . $spaceId);
		} catch (LocalServerException $e) {
			return ['error' => 'Nextcloud refuses to connect to local remote servers'];
		}
		// does not exist or wrong file ID
		if (isset($space['error']) || $decoded['space']['name'] !== strval($file_id)) {
			// create new space
			$newSpace = $this->createSpace($baseUrl, $apiToken, $userId, $file_id);
			if (is_null($newSpace)) {
				return ['error' => 'Impossible to create space'];
			}
			// load artifacts
			if (isset($decoded['artifacts']) && is_array($decoded['artifacts'])) {
				foreach ($decoded['artifacts'] as $artifact) {
					$artifact['space_id'] = $newSpace['_id'];
					$artifact['user_id'] = null;
					$this->loadArtifact($baseUrl, $apiToken, $newSpace['_id'], $artifact);
				}
			}
			// write it to the file to update space_id
			$decoded['space']['_id'] = $newSpace['_id'];
			$decoded['space']['edit_hash'] = $newSpace['edit_hash'];
			$decoded['space']['edit_slug'] = $newSpace['edit_slug'];
			$decoded['space']['name'] = $newSpace['name'];
			$file->putContent(json_encode($decoded));
			return [
				'existed' => false,
				'base_url' => $baseUrl,
				'space_id' => $newSpace['_id'],
				'space_name' => $newSpace['name'],
				'edit_hash' => $newSpace['edit_hash'],
			];
		} else {
			// exists
			return [
				'existed' => true,
				'base_url' => $baseUrl,
				'space_id' => $space['_id'],
				'space_name' => $space['name'],
				'edit_hash' => $space['edit_hash'],
			];
		}
	}

	/**
	 * Make the request to load an artifact from JSON
	 *
	 * @param string $baseUrl
	 * @param string $apiToken
	 * @param string $spaceId
	 * @param array $artifact
	 * @return void
	 */
	private function loadArtifact(string $baseUrl, string $apiToken, string $spaceId, array $artifact): void {
		$response = $this->request($baseUrl, $apiToken, 'spaces/' . $spaceId . '/artifacts', $artifact, 'POST');
		if (isset($response['error'])) {
			$this->logger->error('Creating artifact in ' . $spaceId);
		}
	}

	/**
	 * Make the request to create a space
	 *
	 * @param string $baseUrl
	 * @param string $apiToken
	 * @param ?string $userId
	 * @param int $fileId
	 * @return ?array new space information or null if failed to create
	 */
	private function createSpace(string $baseUrl, string $apiToken, ?string $userId, int $fileId): ?array {
		$strFileId = strval($fileId);
		$params = [
			'name' => $strFileId,
			'edit_slug' => $strFileId,
		];
		$newSpace = $this->request($baseUrl, $apiToken, 'spaces', $params, 'POST');
		if (isset($newSpace['error'])) {
			return null;
		} else {
			return $newSpace;
		}
	}

	/**
	 * Get a user file from a fileId
	 *
	 * @param ?string $userId
	 * @param int $fileID
	 * @return ?Node the file or null if it does not exist (or is not accessible by this user)
	 */
	public function getFileFromId(?string $userId, int $fileId): ?Node {
		if (is_null($userId)) {
			$file = $this->root->getById($fileId);
		} else {
			$userFolder = $this->root->getUserFolder($userId);
			$file = $userFolder->getById($fileId);
		}
		if (is_array($file) && count($file) > 0) {
			return $file[0];
		} elseif (!is_array($file) && $file->getType() === FileInfo::TYPE_FILE) {
			return $file;
		}
		return null;
	}

	/**
	 * Get spaces list from spacedeck API
	 *
	 * @param string $baseUrl
	 * @param string $apiToken
	 * @return array API response or request error
	 */
	public function getSpaceList(string $baseUrl, string $apiToken): array {
		if ($baseUrl === DEFAULT_SPACEDECK_URL) {
			$this->spacedeckBundleService->launchSpacedeck();
		}
		try {
			return $this->request($baseUrl, $apiToken, 'spaces');
		} catch (LocalServerException $e) {
			return ['error' => 'Nextcloud refuses to connect to local remote servers'];
		}
	}

	/**
	 * @param string $baseUrl
	 * @param string $apiToken
	 * @param string $endPoint
	 * @param array $params
	 * @param string $method
	 * @return array
	 */
	private function request(string $baseUrl, string $apiToken, string $endPoint, array $params = [], string $method = 'GET'): array {
		try {
			$url = $baseUrl . '/api/' . $endPoint;
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
			// $this->logger->warning('Spacedeck API error : '.$e->getMessage(), ['app' => $this->appName]);
			return ['error' => $e->getMessage()];
		} catch (ConnectException $e) {
			$this->logger->warning('Spacedeck request connection error : '.$e->getMessage(), ['app' => $this->appName]);
			return ['error' => $e->getMessage()];
		}
	}

	/**
	 */
	public function basicRequest(string $url, array $params = [], string $method = 'GET',
								bool $jsonOutput = false, array $extraHeaders = [], ?string $stringBody = null): array {
		try {
			$options = [
				'headers' => [
					'User-Agent' => 'Nextcloud Spacedeck integration',
				],
			];
			foreach ($extraHeaders as $key => $val) {
				// $options['headers']['X-Spacedeck-Space-Auth'] = $spaceAuth;
				$options['headers'][$key] = $val;
			}

			if (count($params) > 0) {
				if ($method === 'GET') {
					$paramsContent = http_build_query($params);
					$url .= '?' . $paramsContent;
				} else {
					$options['headers']['Content-Type'] = 'application/json';
					$options['body'] = json_encode($params);
				}
			} elseif ($stringBody) {
				$options['body'] = $stringBody;
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
			$respCode = $response->getStatusCode();

			if ($respCode >= 400) {
				return ['error' => 'Bad credentials'];
			} else {
				if ($jsonOutput) {
					$body = $response->getBody();
					return json_decode($body, true);
				} else {
					return ['response' => $response];
				}
			}
		} catch (ServerException | ClientException $e) {
			$response = $e->getResponse();
			// $this->logger->warning('Spacedeck API error : '.$e->getMessage(), ['app' => $this->appName]);
			return ['error' => $e->getMessage()];
		} catch (ConnectException $e) {
			$this->logger->warning('Spacedeck request connection error : '.$e->getMessage(), ['app' => $this->appName]);
			return ['error' => $e->getMessage()];
		}
	}
}
