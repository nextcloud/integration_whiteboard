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
use OCP\Constants;
use Psr\Log\LoggerInterface;
use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Controller;

use OCA\Spacedeck\Service\SpacedeckAPIService;
use OCA\Spacedeck\AppInfo\Application;

require_once __DIR__ . '/../constants.php';

if (!function_exists('getallheaders'))
{
	// polyfill, e.g. on PHP 7.2 setups with nginx.
	// Can be removed when 7.2 becomes unsupported
	function getallheaders() {
		$headers = [];
		if (!is_array($_SERVER)) {
			return $headers;
		}
		foreach ($_SERVER as $name => $value) {
			if (substr($name, 0, 5) == 'HTTP_') {
				$headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
			}
		}
		return $headers;
	}
}

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
		$this->apiToken = $this->config->getAppValue(Application::APP_ID, 'api_token', DEFAULT_SPACEDECK_API_KEY);
		$this->apiToken = $this->apiToken ?: DEFAULT_SPACEDECK_API_KEY;
		$this->baseUrl = $this->config->getAppValue(Application::APP_ID, 'base_url', DEFAULT_SPACEDECK_URL);
		$this->baseUrl = $this->baseUrl ?: DEFAULT_SPACEDECK_URL;
	}

	/**
	 * Get spaces list, used by admin settings to test if spacedeck API is accessible
	 *
	 * @return DataResponse
	 */
	public function getSpaceList(): DataResponse {
		if (!$this->apiToken || !$this->baseUrl) {
			return new DataResponse('Spacedeck not configured', 400);
		}

		$result = $this->spacedeckApiService->getSpaceList($this->baseUrl, $this->apiToken, $this->usesIndexDotPhp());
		if (isset($result['error'])) {
			$response = new DataResponse($result['error'], 401);
		} else {
			$response = new DataResponse($result);
		}
		return $response;
	}

	/**
	 * @NoAdminRequired
	 * Export the space related to a file to PDF
	 *
	 * @param string $file_id
	 *
	 * @return DataResponse
	 */
	public function exportSpaceToPdf(int $file_id, string $outputDir): DataResponse {
		if (!$this->apiToken || !$this->baseUrl) {
			return new DataResponse('Spacedeck is not configured', 400);
		}

		$result = $this->spacedeckApiService->exportSpaceToPdf(
			$this->baseUrl, $this->apiToken, $this->userId, $file_id,  $outputDir, $this->usesIndexDotPhp()
		);
		if (isset($result['error'])) {
			$response = new DataResponse(['message' => $result['error']], 401);
		} else {
			$response = new DataResponse($result);
		}
		return $response;
	}

	/**
	 * Get main spacedeck public page
	 * This checks the corresponding file is accessible by requesting user (or the provided token)
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 * @param int $file_id the file ID (which is also the space name and edit_slug)
	 * @param ?string $token the public share token
	 * @return DataDisplayResponse
	 */
	public function privateProxyGetMain(int $file_id, ?string $token = null): DataDisplayResponse {
		if (!is_null($this->userId) && !is_null($this->spacedeckApiService->getFileFromId($this->userId, $file_id))) {
			return $this->proxyGet('spaces/' . $file_id);
		} elseif (is_null($this->userId) && !is_null($token) && $this->isFileSharedWithToken($token, $file_id)) {
			return $this->proxyGet('spaces/' . $file_id);
		} else {
			return new DataDisplayResponse('Unauthorized', 400);
		}
	}

	/**
	 * Proxy a GET request to Spacedeck
	 * This checks auth headers for API requests only
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 * @param string $path requested spacedeck path
	 * @return DataDisplayResponse
	 */
	public function privateProxyGet(string $path): DataDisplayResponse {
		// check auth for /api/spaces/*
		if (!preg_match('/^api\/spaces\/.*/', $path) || $this->checkAuthHeaders()) {
			return $this->proxyGet($path);
		} else {
			return new DataDisplayResponse('Unauthorized!', 401);
		}
	}

	/**
	 * Proxy a DELETE request to Spacedeck
	 * This checks auth headers
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 * @param string $path requested spacedeck path
	 * @return DataDisplayResponse
	 */
	public function privateProxyDelete(string $path): DataDisplayResponse {
		if (preg_match('/^api\/sessions\/current$/', $path)) {
			return new DataDisplayResponse('', 400);
		} elseif ($this->checkAuthHeaders(true)) {
			return $this->proxyDelete($path);
		} else {
			return new DataDisplayResponse('Unauthorized!', 401);
		}
	}

	/**
	 * Proxy a PUT request to Spacedeck
	 * This checks auth headers
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 * @param string $path requested spacedeck path
	 * @return DataDisplayResponse
	 */
	public function privateProxyPut(string $path): DataDisplayResponse {
		if ($this->checkAuthHeaders(true)) {
			return $this->proxyPut($path);
		} else {
			return new DataDisplayResponse('Unauthorized!', 401);
		}
	}

	/**
	 * Proxy a POST request to Spacedeck
	 * This checks auth headers
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 * @param string $path requested spacedeck path
	 * @return DataDisplayResponse
	 */
	public function privateProxyPost(string $path): DataDisplayResponse {
		if ($this->checkAuthHeaders(true)) {
			return $this->proxyPost($path);
		} else {
			return new DataDisplayResponse('Unauthorized!', 401);
		}
	}

	/**
	 * Check if current user (or share token found in auth headers) has access to a spacedeck file
	 *
	 * @param bool $needWriteAccess
	 * @return bool true if has access
	 */
	private function checkAuthHeaders(bool $needWriteAccess = false): bool {
		$spaceName = $_SERVER['HTTP_X_SPACEDECK_SPACE_NAME'] ?? null;
		$shareToken = $_SERVER['HTTP_X_SPACEDECK_SPACE_TOKEN'] ?? null;
		if (!is_null($this->userId) && !is_null($spaceName)
			&& (
				($needWriteAccess && $this->spacedeckApiService->userHasWriteAccess($this->userId, $spaceName))
				|| (!$needWriteAccess && !is_null($this->spacedeckApiService->getFileFromId($this->userId, $spaceName)))
			)
		) {
			return true;
		} elseif (is_null($this->userId) && !is_null($shareToken)
			&& (
				($needWriteAccess && $this->isFileWriteableWithToken($shareToken, $spaceName))
				|| (!$needWriteAccess && $this->isFileSharedWithToken($shareToken, $spaceName))
			)
		) {
			return true;
		}
		return false;
	}

	/**
	 * Actually forward a GET request with all headers
	 * Change the response CSP to let Spacedeck live
	 *
	 * @param string $path requested spacedeck path
	 * @return DataDisplayResponse
	 */
	private function proxyGet(string $path): DataDisplayResponse {
		if ($path === 'socket') {
			return new DataDisplayResponse('impossible to forward socket', 400);
		}
		$reqHeaders = getallheaders();
		$url = $this->baseUrl . '/' . $path;
		$result = $this->spacedeckApiService->basicRequest($url, [], 'GET', false, $reqHeaders);
		if (isset($result['error'])) {
			return new DataDisplayResponse($result['error'], 400);
		} else {
			$spdResponse = $result['response'];
			$content = $spdResponse->getBody();
			$respCode = $spdResponse->getStatusCode();

			$h = $spdResponse->getHeaders();
			foreach ($h as $k => $v) {
				if (is_array($v)) {
					$h[$k] = $v[0];
				}
			}
			$h['content-security-policy'] = 'script-src * \'unsafe-eval\' \'unsafe-inline\'';
			return new DataDisplayResponse($content, $respCode, $h);
		}
	}

	/**
	 * Actually forward a DELETE request with all headers
	 *
	 * @param string $path requested spacedeck path
	 * @return DataDisplayResponse
	 */
	private function proxyDelete(string $path): DataDisplayResponse {
		$reqHeaders = getallheaders();
		$url = $this->baseUrl . '/' . $path;
		$result = $this->spacedeckApiService->basicRequest($url, [], 'DELETE', false, $reqHeaders);
		if (isset($result['error'])) {
			return new DataDisplayResponse($result['error'], 400);
		} else {
			// save if necessary
			if (preg_match('/.*\/spaces\/.*\/artifacts\/.+$/', $path)) {
				$this->saveSpace();
			}

			$spdResponse = $result['response'];
			$content = $spdResponse->getBody();
			$respCode = $spdResponse->getStatusCode();

			$h = $spdResponse->getHeaders();
			foreach ($h as $k => $v) {
				if (is_array($v)) {
					$h[$k] = $v[0];
				}
			}
			return new DataDisplayResponse($content, $respCode, $h);
		}
	}

	/**
	 * Actually forward a PUT request with all headers
	 *
	 * @param string $path requested spacedeck path
	 * @return DataDisplayResponse
	 */
	private function proxyPut(string $path): DataDisplayResponse {
		$body = file_get_contents('php://input');
		$bodyArray = json_decode($body, true);
		$reqHeaders = getallheaders();
		$url = $this->baseUrl . '/' . $path;
		$result = $this->spacedeckApiService->basicRequest($url, $bodyArray, 'PUT', false, $reqHeaders);
		if (isset($result['error'])) {
			return new DataDisplayResponse($result['error'], 400);
		} else {
			// save if necessary
			if (preg_match('/.*\/spaces\/.*\/artifacts\/.+$/', $path)) {
				$this->saveSpace();
			}

			$spdResponse = $result['response'];
			$content = $spdResponse->getBody();
			$respCode = $spdResponse->getStatusCode();

			$h = $spdResponse->getHeaders();
			foreach ($h as $k => $v) {
				if (is_array($v)) {
					$h[$k] = $v[0];
				}
			}
			return new DataDisplayResponse($content, $respCode, $h);
		}
	}

	/**
	 * Actually forward a POST request with all headers
	 *
	 * @param string $path requested spacedeck path
	 * @return DataDisplayResponse
	 */
	private function proxyPost(string $path): DataDisplayResponse {
		$body = file_get_contents('php://input');
		$bodyArray = json_decode($body, true);
		$reqHeaders = getallheaders();
		$url = $this->baseUrl . '/' . $path;
		// don't miss the get param in post request...
		if (preg_match('/.*\/payload$/', $path)) {
			$url .= '?filename=' . urlencode($_GET['filename']);
		}
		$result = $this->spacedeckApiService->basicRequest($url, $bodyArray ?: [], 'POST', false, $reqHeaders, $bodyArray ? null : $body);
		if (isset($result['error'])) {
			return new DataDisplayResponse($result['error'], 400);
		} else {
			// save if necessary
			if (preg_match('/.*\/artifacts$/', $path)) {
				$this->saveSpace();
			}

			$spdResponse = $result['response'];
			$content = $spdResponse->getBody();
			$respCode = $spdResponse->getStatusCode();

			$h = $spdResponse->getHeaders();
			foreach ($h as $k => $v) {
				if (is_array($v)) {
					$h[$k] = $v[0];
				}
			}
			if ($path === 'api/sessions') {
				$h['Set-Cookie'] = $h['Set-Cookie'][0];
			}
			return new DataDisplayResponse($content, $respCode, $h);
		}
	}

	/**
	 * Save space to file
	 */
	private function saveSpace(): void {
		$spaceName = $_SERVER['HTTP_X_SPACEDECK_SPACE_NAME'] ?? null;
		$result = $this->spacedeckApiService->saveSpaceToFile(
			$this->baseUrl, $this->apiToken, $this->userId, $spaceName, intval($spaceName)
		);
	}

	/**
	 * @NoAdminRequired
	 *
	 * Called by private pages
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
	 * Called by public pages
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
	 * Load space information from a file
	 *
	 * @NoAdminRequired
	 *
	 * @param int $file_id
	 * @return DataResponse
	 */
	public function loadSpaceFromFile(int $file_id): DataResponse {
		if (!$this->apiToken || !$this->baseUrl) {
			return new DataResponse('Spacedeck not configured', 400);
		}

		$result = $this->spacedeckApiService->loadSpaceFromFile(
			$this->baseUrl, $this->apiToken, $this->userId, $file_id, $this->usesIndexDotPhp()
		);
		if (isset($result['error'])) {
			$response = new DataResponse($result['error'], 401);
		} else {
			$response = new DataResponse($result);
		}
		return $response;
	}

	/**
	 * Load space information from a file
	 *
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
			$this->baseUrl, $this->apiToken, $this->userId, $foundFileId, $this->usesIndexDotPhp()
		);
		if (isset($result['error'])) {
			$response = new DataResponse($result['error'], 401);
		} else {
			$response = new DataResponse($result);
		}
		return $response;
	}

	/**
	 * Check if a share token can access a file
	 *
	 * @param string $token
	 * @param int $file_id the file ID or 0 if the token target is a file (public file share page context)
	 * @return ?int the file ID or null if this token does not exist or can't access this file
	 */
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

	/**
	 * Check if a token has write access to a file
	 *
	 * @param string $token
	 * @param int $file_id
	 * @return bool true if has write access
	 */
	private function isFileWriteableWithToken(string $token, int $file_id): bool {
		try {
			$share = $this->shareManager->getShareByToken($token);
			$perms = $share->getPermissions();
			return (($perms & Constants::PERMISSION_UPDATE) !== 0);
		} catch (ShareNotFound $e) {
			return false;
		}
		return false;
	}

	/**
	 * Check if current request URI includes index.php
	 *
	 * @return bool
	 */
	private function usesIndexDotPhp(): bool {
		return preg_match('/index\.php\/apps\/integration_whiteboard/', $_SERVER['REQUEST_URI']) === 1;
	}
}
