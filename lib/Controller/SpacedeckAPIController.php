<?php
/**
 * Nextcloud - spacedeck
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2020
 */

namespace OCA\Spacedeck\Controller;

use OCP\IConfig;
use OCP\IServerContainer;
use OCP\IL10N;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\RedirectResponse;

use OCP\AppFramework\Http\ContentSecurityPolicy;

use OCP\Files\FileInfo;
use OCP\Share\IManager as IShareManager;
use Psr\Log\LoggerInterface;
use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Controller;

use OCA\Spacedeck\Service\SpacedeckAPIService;
use OCA\Spacedeck\AppInfo\Application;

class SpacedeckAPIController extends Controller {


	private $userId;
	private $config;
	private $dbconnection;

	public function __construct(string $AppName,
								IRequest $request,
								IServerContainer $serverContainer,
								IConfig $config,
								IL10N $l10n,
								IShareManager $shareManager,
								LoggerInterface $logger,
								SpacedeckAPIService $spacedeckApiService,
								?string $userId) {
		parent::__construct($AppName, $request);
		$this->userId = $userId;
		$this->l10n = $l10n;
		$this->shareManager = $shareManager;
		$this->serverContainer = $serverContainer;
		$this->config = $config;
		$this->logger = $logger;
		$this->spacedeckApiService = $spacedeckApiService;
		$this->apiToken = $this->config->getAppValue(Application::APP_ID, 'api_token', '');
		$this->baseUrl = $this->config->getAppValue(Application::APP_ID, 'base_url', '');
	}

	/**
	 * @NoAdminRequired
	 *
	 * @return DataResponse
	 */
	public function saveSpaceToFile(string $space_id, int $file_id): DataResponse {
		if (!$this->apiToken || !$this->baseUrl) {
			return new DataResponse('Spacedeck not configured', 400);
		}

		$result = $this->spacedeckApiService->saveSpaceToFile(
			$this->baseUrl, $this->apiToken, $this->userId, $space_id, $file_id
		);
		if (isset($result['error'])) {
			$response = new DataResponse($result['error'], 401);
		} else {
			$response = new DataResponse($result);
		}
		return $response;
	}

	/**
	 * @NoAdminRequired
	 * @PublicPage
	 *
	 * @return DataResponse
	 */
	public function publicSaveSpaceToFile(string $token, string $space_id, int $file_id): DataResponse {
		if (!$this->apiToken || !$this->baseUrl) {
			return new DataResponse('Spacedeck not configured', 400);
		}
		$foundFileId = $this->isFileSharedWithToken($token, $file_id);
		if (!$foundFileId) {
			return new DataResponse('No such share', 400);
		}

		$result = $this->spacedeckApiService->saveSpaceToFile(
			$this->baseUrl, $this->apiToken, $this->userId, $space_id, $foundFileId
		);
		if (isset($result['error'])) {
			$response = new DataResponse($result['error'], 401);
		} else {
			$response = new DataResponse($result);
		}
		return $response;
	}

	/**
	 * @NoAdminRequired
	 *
	 * @return DataResponse
	 */
	public function loadSpaceFromFile(int $file_id): DataResponse {
		if (!$this->apiToken || !$this->baseUrl) {
			return new DataResponse('Spacedeck not configured', 400);
		}

		$result = $this->spacedeckApiService->loadSpaceFromFile(
			$this->baseUrl, $this->apiToken, $this->userId, $file_id
		);
		if (isset($result['error'])) {
			$response = new DataResponse($result['error'], 401);
		} else {
			$response = new DataResponse($result);
		}
		return $response;
	}

	/**
	 * @NoAdminRequired
	 * @PublicPage
	 *
	 * @return DataResponse
	 */
	public function publicLoadSpaceFromFile(string $token, int $file_id): DataResponse {
		if (!$this->apiToken || !$this->baseUrl) {
			return new DataResponse('Spacedeck not configured', 400);
		}
		$foundFileId = $this->isFileSharedWithToken($token, $file_id);
		if (!$foundFileId) {
			return new DataResponse('No such share', 400);
		}

		$result = $this->spacedeckApiService->loadSpaceFromFile(
			$this->baseUrl, $this->apiToken, $this->userId, $foundFileId
		);
		if (isset($result['error'])) {
			$response = new DataResponse($result['error'], 401);
		} else {
			$response = new DataResponse($result);
		}
		return $response;
	}

	private function isFileSharedWithToken(string $token, int $file_id): ?int {
		try {
			$share = $this->shareManager->getShareByToken($token);
			$node = $share->getNode();
			// in single file share, we get 0 as file ID
			if ($node->getType() === FileInfo::TYPE_FILE && ($file_id === 0 || $node->getId() === $file_id)) {
				return $node->getId();
			} elseif ($node->getType() === FileInfo::TYPE_FOLDER) {
				$file = $node->getById($file_id);
				if ( (is_array($file) && count($file) > 0)
					|| (!is_array($file) && $file->getType() === FileInfo::TYPE_FILE)
				) {
					return $file_id;
				}
			}
		} catch (ShareNotFound $e) {
			return null;
		}
		return null;
	}
}
