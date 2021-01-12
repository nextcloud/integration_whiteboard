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

return [
	'routes' => [
		['name' => 'config#setAdminConfig', 'url' => '/admin-config', 'verb' => 'PUT'],
		['name' => 'spacedeckAPI#saveSpaceToFile', 'url' => '/space/{space_id}/{file_id}', 'verb' => 'POST'],
		['name' => 'spacedeckAPI#loadSpaceFromFile', 'url' => '/space/{file_id}', 'verb' => 'GET'],

		['name' => 'spacedeckAPI#privateProxyGetMain', 'url' => '/proxy/spaces/{file_id}', 'verb' => 'GET'],
		['name' => 'spacedeckAPI#privateProxyGet', 'url' => '/proxy/{path}', 'verb' => 'GET', 'requirements' => ['path' => '.*']],
		['name' => 'spacedeckAPI#privateProxyDelete', 'url' => '/proxy/{path}', 'verb' => 'DELETE', 'requirements' => ['path' => '.*']],
		['name' => 'spacedeckAPI#privateProxyPut', 'url' => '/proxy/{path}', 'verb' => 'PUT', 'requirements' => ['path' => '.*']],
		['name' => 'spacedeckAPI#privateProxyPost', 'url' => '/proxy/{path}', 'verb' => 'POST', 'requirements' => ['path' => '.*']],

		// ['name' => 'spacedeckAPI#publicProxyGet', 'url' => '/proxy/{path}', 'verb' => 'GET', 'requirements' => ['path' => '.*']],
		// ['name' => 'spacedeckAPI#publicProxyDelete', 'url' => '/proxy/{path}', 'verb' => 'DELETE', 'requirements' => ['path' => '.*']],
		// ['name' => 'spacedeckAPI#publicProxyPut', 'url' => '/proxy/{path}', 'verb' => 'PUT', 'requirements' => ['path' => '.*']],
		// ['name' => 'spacedeckAPI#publicProxyPost', 'url' => '/proxy/{path}', 'verb' => 'POST', 'requirements' => ['path' => '.*']],

		// public share
		['name' => 'spacedeckAPI#publicSaveSpaceToFile', 'url' => '/s/{token}/space/{space_id}/{file_id}', 'verb' => 'POST'],
		['name' => 'spacedeckAPI#publicLoadSpaceFromFile', 'url' => '/s/{token}/space/{file_id}', 'verb' => 'GET'],
	]
];
