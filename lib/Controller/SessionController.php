<?php
/**
 * Nextcloud - spacedeck
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2022
 */

namespace OCA\Spacedeck\Controller;

use OCA\Spacedeck\Service\SessionService;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

use OCP\IRequest;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Controller;

use OCA\Spacedeck\Service\SpacedeckAPIService;
use OCA\Spacedeck\AppInfo\Application;

class SessionController extends Controller {

	public function __construct(string              $appName,
								IRequest            $request,
								IConfig             $config,
								LoggerInterface     $logger,
								SpacedeckAPIService $spacedeckAPIService,
								?string             $userId) {
		parent::__construct($appName, $request);
		$this->appName = $appName;
		$this->userId = $userId;
		$this->config = $config;
		$this->logger = $logger;
		$this->spacedeckAPIService = $spacedeckAPIService;
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 * @param string $token
	 * @return DataResponse
	 */
	public function check(string $token): DataResponse {
		$permissions = $this->spacedeckAPIService->checkSessionPermissions($token);
		return new DataResponse([ 'access_level' => $permissions ]);
	}

	/**
	 * @NoAdminRequired
	 * @param int $fileId
	 * @return DataResponse
	 */
	public function create(int $fileId): DataResponse {
		$sessionInfo = $this->spacedeckAPIService->createUserSession($this->userId, $this->userId, $fileId);
		if ($sessionInfo !== null) {
			return new DataResponse($sessionInfo);
		} else {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * @PublicPage
	 * @param int $fileId
	 * @param string $shareToken
	 * @return DataResponse
	 */
	public function publicCreate(int $fileId, string $shareToken): DataResponse {
		$sessionInfo = $this->spacedeckAPIService->createShareSession($shareToken, $fileId);
		if ($sessionInfo !== null) {
			return new DataResponse($sessionInfo);
		} else {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * @NoAdminRequired
	 * @param string $token
	 * @return DataResponse
	 */
	public function delete(string $token): DataResponse {
		$success = $this->spacedeckAPIService->deleteUserSession($this->userId, $token);
		if ($success) {
			return new DataResponse(1);
		} else {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * @PublicPage
	 * @param string $token
	 * @param string $shareToken
	 * @return DataResponse
	 */
	public function publicDelete(string $token, string $shareToken): DataResponse {
		$success = $this->spacedeckAPIService->deleteShareSession($shareToken, $token);
		if ($success) {
			return new DataResponse(1);
		} else {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}
	}
}
