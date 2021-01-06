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

require_once __DIR__ . '/../../vendor/autoload.php';

use Proxy\Proxy;
use Proxy\Adapter\Guzzle\GuzzleAdapter;
use Proxy\Filter\RemoveEncodingFilter;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;

use GuzzleHttp;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\Response;

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
	 *
	 */
	public function proxy2(?string $path) {
		echo $path;
		echo '!!!';
		echo $_GET['req'];
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 */
	public function proxy(string $path) {
		// Create a PSR7 request based on the current browser request.
		$request = ServerRequestFactory::fromGlobals();
		// var_dump($_SERVER['REQUEST_URI']);
		// $reqUri = $_SERVER['REQUEST_URI'];
		// $url2 = str_replace('/dev/server21/index.php/apps/integration_spacedeck', 'http://localhost:9666', $reqUri);
		// var_dump($url2);
		// $url2 = preg_replace('/\/proxy/', '', $url2);
		$url2 = 'http://localhost:9666/' . $path;
		// var_dump($url2);

		// Create a guzzle client
		$guzzle = new GuzzleHttp\Client();

		// $req = $_GET['req'];

		// Create the proxy instance
		$proxy = new Proxy(new GuzzleAdapter($guzzle));
		// $url = 'http://localhost:9666/' . $req;
		// $url = 'http://localhost:9666/spaces/246ddaa4-7422-434d-b689-798029650c01?spaceAuth=68ec9be';
		// $url = $toUrl;
		$proxy->filter(function ($request, $response, $next) use ($url2) {
			$request = $request->withUri(new Uri($url2));
			$response = $next($request, $response);
			return $response;
		});

		// Add a response filter that removes the encoding headers.
		$proxy->filter(new RemoveEncodingFilter());

		// Forward the request and get the response.
		$response = $proxy->forward($request)
			->filter(function ($request, $response, $next) {
				// Manipulate the request object.
				//$request = $request->withHeader('User-Agent', 'FishBot/1.0');
				//$request = $request->withHeader('Origin', 'https://free.fr');
				//$request = $request->withHeader('Host', 'free.fr');
				//$request = $request->withHeader('X-Request-URI', 'free.fr');

				// Call the next item in the middleware.
				$response = $next($request, $response);

				// Manipulate the response object.
				//$response = $response->withHeader('X-Proxy-Foo', 'Bar');
				$content = $response->getBody()->getContents();
				$content = preg_replace('/src="\//', 'src="https://localhost/dev/server21/index.php/apps/integration_whiteboard/proxy/', $content);
				$content = preg_replace('/href="\//', 'href="https://localhost/dev/server21/index.php/apps/integration_whiteboard/proxy/', $content);
				// $content = preg_replace('//', '?req=/', $content);
				$content = preg_replace('/"..\/images\//', '"https://localhost/dev/server21/index.php/apps/integration_whiteboard/proxy/images/', $content);
				$content = preg_replace('/"\/images\//', '"https://localhost/dev/server21/index.php/apps/integration_whiteboard/proxy/images/', $content);
				$content = preg_replace('/"..\/fonts\//', '"https://localhost/dev/server21/index.php/apps/integration_whiteboard/proxy/fonts/', $content);
				$content = preg_replace('/"\/fonts\//', '"https://localhost/dev/server21/index.php/apps/integration_whiteboard/proxy/fonts/', $content);
				$content = preg_replace('/url\(\/images\//', '"https://localhost/dev/server21/index.php/apps/integration_whiteboard/proxy/fonts/', $content);
				// $content = preg_replace('/api\//', '?req=/api/', $content);
				// $newBody = Utils::streamFor('PLPLPLPL');
				// // $newBody->write($content);
				// // var_dump($body);
				// $response->withBody($newBody);
				// $response->withBody($newBody);
				return new Response(200, $response->getHeaders(), $content);

				return $response;
			})
			//->to($targetUri);
			->to('http://localhost:9666');

		// Output response to the browser.
		(new SapiEmitter)->emit($response);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 */
	public function proxyGet(string $path) {
		$url = 'http://localhost:9666/' . $path;
		$result = $this->spacedeckApiService->basicRequest($url);
		if (isset($result['error'])) {
			return new DataDisplayResponse('error', 400);
		} else {
			$spdResponse = $result['response'];
			error_log('!!!!!!!!!!!!!!! '.$path. ' ' . $spdResponse->getHeaders()['Content-Type'][0]. ' OOOOOOOOO');
			$content = $spdResponse->getBody();
			$content = preg_replace('/src="\//', 'src="https://localhost/dev/server21/index.php/apps/integration_whiteboard/proxy/', $content);
			$content = preg_replace('/href="\//', 'href="https://localhost/dev/server21/index.php/apps/integration_whiteboard/proxy/', $content);
			// $content = preg_replace('//', '?req=/', $content);
			$content = preg_replace('/"..\/images\//', '"https://localhost/dev/server21/index.php/apps/integration_whiteboard/proxy/images/', $content);
			$content = preg_replace('/"\/images\//', '"https://localhost/dev/server21/index.php/apps/integration_whiteboard/proxy/images/', $content);
			$content = preg_replace('/"..\/fonts\//', '"https://localhost/dev/server21/index.php/apps/integration_whiteboard/proxy/fonts/', $content);
			$content = preg_replace('/"\/fonts\//', '"https://localhost/dev/server21/index.php/apps/integration_whiteboard/proxy/fonts/', $content);
			$content = preg_replace('/url\(\/images\//', '"https://localhost/dev/server21/index.php/apps/integration_whiteboard/proxy/fonts/', $content);
			$content = preg_replace('/api_endpoint\+/', '"https://localhost/dev/server21/index.php/apps/integration_whiteboard/proxy"+', $content);
			// return new Response(200, $spdResponse->getHeaders(), $content);

			$csp = new \OCP\AppFramework\Http\ContentSecurityPolicy();
			// $csp->allowInlineScript(true);
			// $csp->allowInlineStyle(true);
			// $csp->allowEvalScript(true);
			$csp->useJsNonce('');

			$csp->addAllowedScriptDomain("'unsafe-inline' 'unsafe-eval' *");
			$csp->addAllowedStyleDomain('*');
			$csp->addAllowedFontDomain('*');
			$csp->addAllowedImageDomain('*');
			$csp->addAllowedConnectDomain('*');
			$csp->addAllowedMediaDomain('*');
			$csp->addAllowedObjectDomain('*');
			$csp->addAllowedFrameDomain('*');
			$csp->addAllowedChildSrcDomain('*');

			$response = new DataDisplayResponse($content);
			$h = $response->getHeaders();
			$h['Content-Type'] = $spdResponse->getHeaders()['Content-Type'][0];
			$response->setHeaders($h);
			// $response->setHeaders($spdResponse->getHeaders());
			$response->setContentSecurityPolicy($csp);
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
