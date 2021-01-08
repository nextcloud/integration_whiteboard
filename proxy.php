<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once 'websocket_client.php';

use Proxy\Proxy;
use Proxy\Adapter\Guzzle\GuzzleAdapter;
use Proxy\Filter\RemoveEncodingFilter;
use Laminas\Diactoros\ServerRequestFactory;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Stream;
use GuzzleHttp\Psr7\Utils;

// Create a PSR7 request based on the current browser request.
$request = ServerRequestFactory::fromGlobals();

$lala = $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'];
//var_dump(json_encode($_SERVER));
$uri = $_SERVER['REQUEST_URI'];
$url = str_replace('/dev/server21/apps/integration_whiteboard/proxy.php', '', $uri);
// var_dump($url);
// exit();
if ($url === '/socket') {
    // var_dump('PLPLPLPLPLPLPLPL');
    // exit();
}

// Create a guzzle client
$guzzle = new GuzzleHttp\Client();

//$req = $_GET['req'];
$req = $url;

// Create the proxy instance
$proxy = new Proxy(new GuzzleAdapter($guzzle));
$url = 'http://localhost:9666' . $req;
// $url = $toUrl;
$proxy->filter(function ($request, $response, $next) use ($url) {
	$request = $request->withUri(new Uri($url));
	$response = $next($request, $response);
	return $response;
});

// Add a response filter that removes the encoding headers.
$proxy->filter(new RemoveEncodingFilter());

// Forward the request and get the response.
$response = $proxy->forward($request)
    ->filter(function ($request, $response, $next) {
        // Manipulate the request object.
        // $request = $request->withHeader('User-Agent', 'FishBot/1.0');
        // $request = $request->withHeader('Origin', 'https://free.fr');
        // $request = $request->withHeader('Host', 'free.fr');

        // Call the next item in the middleware.
        $response = $next($request, $response);

        // Manipulate the response object.
        //$response = $response->withHeader('X-Proxy-Foo', 'Bar');
        //$response = $response->withHeader('X-Forwarded-Host', 'toto.com');
        //$response = $response->withHeader('Origin', 'https://free.fr');
        //$response = $response->withHeader('Host', 'free.fr');
        // $content = $response->getBody()->getContents();
        // $content = preg_replace('/src="\//', 'src="?req=/', $content);
        // $content = preg_replace('/href="\//', 'href="?req=/', $content);
        // // $content = preg_replace('//', '?req=/', $content);
        // $content = preg_replace('/"..\/images\//', '"https://localhost/dev/server21/apps/integration_whiteboard/proxy.php?req=/images/', $content);
        // $content = preg_replace('/"\/images\//', '"https://localhost/dev/server21/apps/integration_whiteboard/proxy.php?req=/images/', $content);
        // $content = preg_replace('/"..\/fonts\//', '"https://localhost/dev/server21/apps/integration_whiteboard/proxy.php?req=/fonts/', $content);
        // $content = preg_replace('/"\/fonts\//', '"https://localhost/dev/server21/apps/integration_whiteboard/proxy.php?req=/fonts/', $content);
        // $content = preg_replace('/url\(\/images\//', '"https://localhost/dev/server21/apps/integration_whiteboard/proxy.php?req=/fonts/', $content);
        // // $content = preg_replace('/api\//', '?req=/api/', $content);
        // // $newBody = Utils::streamFor('PLPLPLPL');
        // // // $newBody->write($content);
        // // // var_dump($body);
        // // $response->withBody($newBody);
        // // $response->withBody($newBody);
        // return new Response(200, $response->getHeaders(), $content);

        return $response;
    })
    ->to('http://localhost:9666');

// Output response to the browser.
(new Laminas\HttpHandlerRunner\Emitter\SapiEmitter)->emit($response);

?>
