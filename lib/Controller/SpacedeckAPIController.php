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

use OCA\Spacedeck\Service\FileService;
use OCA\Spacedeck\Service\SessionService;
use OCP\AppFramework\Http;
use OCP\IConfig;
use OCP\IServerContainer;
use OCP\IL10N;

use OCP\Share\IManager as IShareManager;
use Psr\Log\LoggerInterface;
use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Controller;

use OCA\Spacedeck\Service\SpacedeckAPIService;
use OCA\Spacedeck\AppInfo\Application;

if (!function_exists('getallheaders')) {
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
	/**
	 * @var FileService
	 */
	private $fileService;
	/**
	 * @var SessionService
	 */
	private $sessionService;

	public function __construct(string $AppName,
								IRequest $request,
								IServerContainer $serverContainer,
								IConfig $config,
								IL10N $l10n,
								IShareManager $shareManager,
								LoggerInterface $logger,
								SpacedeckAPIService $spacedeckApiService,
								SessionService $sessionService,
								FileService $fileService,
								?string $userId) {
		parent::__construct($AppName, $request);
		$this->userId = $userId;
		$this->l10n = $l10n;
		$this->shareManager = $shareManager;
		$this->serverContainer = $serverContainer;
		$this->config = $config;
		$this->logger = $logger;
		$this->spacedeckApiService = $spacedeckApiService;

		$this->useLocalSpacedeck = $this->config->getAppValue(Application::APP_ID, 'use_local_spacedeck', '1') === '1';
		if ($this->useLocalSpacedeck) {
			$this->baseUrl = Application::DEFAULT_SPACEDECK_URL;
			$this->apiToken = Application::DEFAULT_SPACEDECK_API_KEY;
		} else {
			$this->baseUrl = $this->config->getAppValue(Application::APP_ID, 'base_url', Application::DEFAULT_SPACEDECK_URL);
			$this->baseUrl = $this->baseUrl ?: Application::DEFAULT_SPACEDECK_URL;
			$this->apiToken = $this->config->getAppValue(Application::APP_ID, 'api_token', Application::DEFAULT_SPACEDECK_API_KEY);
			$this->apiToken = $this->apiToken ?: Application::DEFAULT_SPACEDECK_API_KEY;
		}
		$this->fileService = $fileService;
		$this->sessionService = $sessionService;
	}

	/**
	 * Wrapper for getallheaders to unset 0 length strings
	 *
	 * @return array
	 */
	private function getallheadersWrapper(): Array {
		$headers = getallheaders();

		foreach ($headers as $name => $value) {
			if ($value === '') {
				unset($headers[$name]);
			}
		}

		return $headers;
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

	public function getExtSpacedeckStylesheet(): DataResponse {
		if (!$this->apiToken || !$this->baseUrl) {
			return new DataResponse('Spacedeck not configured', 400);
		}

		$result = $this->spacedeckApiService->getExtSpacedeckStylesheet($this->baseUrl);
		if (isset($result['error'])) {
			$response = new DataResponse($result['error'], 401);
		} else {
			$response = new DataResponse(['styleContent' => $result['response']->getBody()]);
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
		if (!is_null($this->userId) && $this->fileService->getFileFromId($this->userId, $file_id) !== null) {
			return $this->proxyGet('spaces/' . $file_id);
		} elseif (is_null($this->userId) && !is_null($token) && $this->fileService->getFileFromShareToken($token, $file_id) !== null) {
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
				($needWriteAccess && $this->fileService->userHasWriteAccess($this->userId, $spaceName))
				|| (!$needWriteAccess && $this->fileService->getFileFromId($this->userId, $spaceName) !== null)
			)
		) {
			return true;
		} elseif (is_null($this->userId) && !is_null($shareToken)
			&& (
				($needWriteAccess && $this->fileService->isFileWriteableWithToken($shareToken, $spaceName))
				|| (!$needWriteAccess && $this->fileService->getFileFromShareToken($shareToken, $spaceName) !== null)
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
		$reqHeaders = $this->getallheadersWrapper();
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
		$reqHeaders = $this->getallheadersWrapper();
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
		$reqHeaders = $this->getallheadersWrapper();
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
		$reqHeaders = $this->getallheadersWrapper();
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
		$foundFile = $this->fileService->getFileFromShareToken($token, $file_id);
		if ($foundFile == null) {
			return new DataResponse('No such share', 400);
		}

		$result = $this->spacedeckApiService->saveSpaceToFile(
			$this->baseUrl, $this->apiToken, $this->userId, $space_id, $foundFile->getId()
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
			return new DataResponse('Spacedeck not configured', Http::STATUS_BAD_REQUEST);
		}

		$result = $this->spacedeckApiService->loadSpaceFromFile(
			$this->baseUrl, $this->apiToken, $this->userId, $file_id, $this->usesIndexDotPhp()
		);
		if (isset($result['error'])) {
			$response = new DataResponse($result['error'], Http::STATUS_UNAUTHORIZED);
		} else {
			$result['use_local_spacedeck'] = $this->useLocalSpacedeck;
			// session creation for external spacedeck
			if (!$this->useLocalSpacedeck) {
				$sessionInfo = $this->sessionService->createUserSession($this->userId, $file_id);
				if ($sessionInfo === null) {
					return new DataResponse('Failed to create the session', Http::STATUS_BAD_REQUEST);
				}
				$result['session_token'] = $sessionInfo['token'];
			}
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
			return new DataResponse('Spacedeck not configured', Http::STATUS_BAD_REQUEST);
		}
		$foundFile = $this->fileService->getFileFromShareToken($token, $file_id);
		if ($foundFile === null) {
			return new DataResponse('No such share', Http::STATUS_BAD_REQUEST);
		}

		$result = $this->spacedeckApiService->loadSpaceFromFile(
			$this->baseUrl, $this->apiToken, $this->userId, $foundFile->getId(), $this->usesIndexDotPhp()
		);
		if (isset($result['error'])) {
			$response = new DataResponse($result['error'], Http::STATUS_UNAUTHORIZED);
		} else {
			$result['use_local_spacedeck'] = $this->useLocalSpacedeck;
			// session creation for external spacedeck
			if (!$this->useLocalSpacedeck) {
				$sessionInfo = $this->sessionService->createShareSession($token, $file_id);
				if ($sessionInfo === null) {
					return new DataResponse('Failed to create the session', Http::STATUS_BAD_REQUEST);
				}
				$result['session_token'] = $sessionInfo['token'];
			}
			$response = new DataResponse($result);
		}
		return $response;
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
