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
		$this->apiToken = $this->config->getAppValue(Application::APP_ID, 'api_token', '');
		$this->baseUrl = $this->config->getAppValue(Application::APP_ID, 'base_url', '');
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 */
	public function privateProxyGetMain(string $file_id, ?string $token = null): DataDisplayResponse {
		error_log('fid '. $file_id. ' and token '. $token);
		if (!is_null($this->userId) && !is_null($this->spacedeckApiService->getFileFromId($this->userId, $file_id))) {
			return $this->proxyGet('spaces/' . $file_id);
		} elseif (is_null($this->userId) && !is_null($token) && $this->isFileSharedWithToken($token, $file_id)) {
			return $this->proxyGet('spaces/' . $file_id);
		} else {
			return new DataDisplayResponse('Unauthorized', 400);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 */
	public function privateProxyGet(string $path): DataDisplayResponse {
		return $this->proxyGet($path);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 */
	public function privateProxyDelete(string $path): DataDisplayResponse {
		return $this->proxyDelete($path);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 */
	public function privateProxyPut(string $path): DataDisplayResponse {
		return $this->proxyPut($path);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 */
	public function privateProxyPost(string $path): DataDisplayResponse {
		return $this->proxyPost($path);
	}

	private function proxyGet(string $path) {
		if ($path === 'socket') {
			return new DataDisplayResponse('impossible to forward socket', 400);
		}
		// HINT: set @PublicPage to be able to access outside NC
		$reqHeaders = getallheaders();
		$url = 'http://localhost:9666/' . $path;
		$result = $this->spacedeckApiService->basicRequest($url, [], 'GET', false, $reqHeaders);
		if (isset($result['error'])) {
			return new DataDisplayResponse($result['error'], 400);
		} else {
			$spdResponse = $result['response'];
			// error_log('!!!!!!!!!!!!!!! '.$path. ' ' . $spdResponse->getHeaders()['Content-Type'][0]. ' OOOOOOOOO');
			$content = $spdResponse->getBody();

			$csp = new \OCP\AppFramework\Http\ContentSecurityPolicy();
			$csp->allowInlineScript(true);
			$csp->allowInlineStyle(true);
			$csp->allowEvalScript(true);
			//$csp->useJsNonce('');

			$csp->addAllowedScriptDomain("*");
			$csp->addAllowedStyleDomain('*');
			$csp->addAllowedFontDomain('*');
			$csp->addAllowedImageDomain('*');
			$csp->addAllowedConnectDomain('*');
			$csp->addAllowedMediaDomain('*');
			$csp->addAllowedObjectDomain('*');
			$csp->addAllowedFrameDomain('*');
			$csp->addAllowedChildSrcDomain('*');

			$h = $spdResponse->getHeaders();
			$h['Content-Type'] = $spdResponse->getHeaders()['Content-Type'][0];
			$h['content-security-policy'] = 'script-src * \'unsafe-eval\' \'unsafe-inline\'';
			// error_log('LLLLLLL '.$spdResponse->getHeaders()['Content-Type'][0]);
			// $response = new Response(200, $h, $content);
			$response = new DataDisplayResponse($content, 200, $h);
			// $response = new DataDisplayResponse($content);
			// $response->setContentSecurityPolicy($csp);
			// $response->setHeaders($h);
			// $response->setHeaders($spdResponse->getHeaders());
			return $response;
		}
	}

	private function proxyDelete(string $path) {
		// $spaceAuth = $_SERVER['HTTP_X_SPACEDECK_SPACE_AUTH'] ?? null;
		$reqHeaders = getallheaders();
		$url = 'http://localhost:9666/' . $path;
		$result = $this->spacedeckApiService->basicRequest($url, [], 'DELETE', false, $reqHeaders);
		if (isset($result['error'])) {
			return new DataDisplayResponse($result['error'], 400);
		} else {
			$spdResponse = $result['response'];
			// error_log('!!!!!!!!!!!!!!! '.$path. ' ' . $spdResponse->getHeaders()['Content-Type'][0]. ' OOOOOOOOO');
			$content = $spdResponse->getBody();

			$csp = new \OCP\AppFramework\Http\ContentSecurityPolicy();
			$csp->allowInlineScript(true);
			$csp->allowInlineStyle(true);
			$csp->allowEvalScript(true);

			$csp->addAllowedScriptDomain("*");
			$csp->addAllowedStyleDomain('*');
			$csp->addAllowedFontDomain('*');
			$csp->addAllowedImageDomain('*');
			$csp->addAllowedConnectDomain('*');
			$csp->addAllowedMediaDomain('*');
			$csp->addAllowedObjectDomain('*');
			$csp->addAllowedFrameDomain('*');
			$csp->addAllowedChildSrcDomain('*');

			$h = $spdResponse->getHeaders();
			$h['Content-Type'] = $spdResponse->getHeaders()['Content-Type'][0];
			$h['content-security-policy'] = 'script-src * \'unsafe-eval\' \'unsafe-inline\'';
			$response = new DataDisplayResponse($content, 200, $h);
			return $response;
		}
	}

	private function proxyPut(string $path) {
		// var_dump(file_get_contents('php://input'));
		$body = file_get_contents('php://input');
		$bodyArray = json_decode($body, true);
		// $spaceAuth = $_SERVER['HTTP_X_SPACEDECK_SPACE_AUTH'] ?? null;
		$reqHeaders = getallheaders();
		$url = 'http://localhost:9666/' . $path;
		$result = $this->spacedeckApiService->basicRequest($url, $bodyArray, 'PUT', false, $reqHeaders);
		if (isset($result['error'])) {
			return new DataDisplayResponse($result['error'], 400);
		} else {
			$spdResponse = $result['response'];
			// error_log('!!!!!!!!!!!!!!! '.$path. ' ' . $spdResponse->getHeaders()['Content-Type'][0]. ' OOOOOOOOO');
			$content = $spdResponse->getBody();

			$csp = new \OCP\AppFramework\Http\ContentSecurityPolicy();
			$csp->allowInlineScript(true);
			$csp->allowInlineStyle(true);
			$csp->allowEvalScript(true);

			$csp->addAllowedScriptDomain("*");
			$csp->addAllowedStyleDomain('*');
			$csp->addAllowedFontDomain('*');
			$csp->addAllowedImageDomain('*');
			$csp->addAllowedConnectDomain('*');
			$csp->addAllowedMediaDomain('*');
			$csp->addAllowedObjectDomain('*');
			$csp->addAllowedFrameDomain('*');
			$csp->addAllowedChildSrcDomain('*');

			$h = $spdResponse->getHeaders();
			$h['Content-Type'] = $spdResponse->getHeaders()['Content-Type'][0];
			$h['content-security-policy'] = 'script-src * \'unsafe-eval\' \'unsafe-inline\'';
			$response = new DataDisplayResponse($content, 200, $h);
			return $response;
		}
	}

	private function proxyPost(string $path) {
		// var_dump(file_get_contents('php://input'));
		$body = file_get_contents('php://input');
		$bodyArray = json_decode($body, true);
		// $spaceAuth = $_SERVER['HTTP_X_SPACEDECK_SPACE_AUTH'] ?? null;
		$reqHeaders = getallheaders();
		$url = 'http://localhost:9666/' . $path;
		$result = $this->spacedeckApiService->basicRequest($url, $bodyArray, 'POST', false, $reqHeaders);
		if (isset($result['error'])) {
			return new DataDisplayResponse($result['error'], 400);
		} else {
			$spdResponse = $result['response'];
			$content = $spdResponse->getBody();

			$h = $spdResponse->getHeaders();
			$h['Content-Type'] = $spdResponse->getHeaders()['Content-Type'][0];
			if ($path === 'api/sessions') {
				error_log('API cookie');
				error_log($h['Set-Cookie'][0]. ' !!!!!!!!!!');
				$h['Set-Cookie'] = $h['Set-Cookie'][0];
			}
			$response = new DataDisplayResponse($content, 200, $h);
			return $response;
		}
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
