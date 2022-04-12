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
		['name' => 'spacedeckAPI#exportSpaceToPdf', 'url' => '/space/{file_id}/pdf', 'verb' => 'POST'],
		['name' => 'spacedeckAPI#saveSpaceToFile', 'url' => '/space/{space_id}/{file_id}', 'verb' => 'POST'],
		['name' => 'spacedeckAPI#loadSpaceFromFile', 'url' => '/space/{file_id}', 'verb' => 'GET'],
		['name' => 'spacedeckAPI#getSpaceList', 'url' => '/spaces', 'verb' => 'GET'],
		['name' => 'spacedeckAPI#getExtSpacedeckStylesheet', 'url' => '/test-get-style', 'verb' => 'GET'],

		['name' => 'spacedeckAPI#privateProxyGetMain', 'url' => '/proxy/spaces/{file_id}', 'verb' => 'GET'],
		['name' => 'spacedeckAPI#privateProxyGet', 'url' => '/proxy/{path}', 'verb' => 'GET', 'requirements' => ['path' => '.*']],
		['name' => 'spacedeckAPI#privateProxyDelete', 'url' => '/proxy/{path}', 'verb' => 'DELETE', 'requirements' => ['path' => '.*']],
		['name' => 'spacedeckAPI#privateProxyPut', 'url' => '/proxy/{path}', 'verb' => 'PUT', 'requirements' => ['path' => '.*']],
		['name' => 'spacedeckAPI#privateProxyPost', 'url' => '/proxy/{path}', 'verb' => 'POST', 'requirements' => ['path' => '.*']],

		// public share
		['name' => 'spacedeckAPI#publicSaveSpaceToFile', 'url' => '/s/{token}/space/{space_id}/{file_id}', 'verb' => 'POST'],
		['name' => 'spacedeckAPI#publicLoadSpaceFromFile', 'url' => '/s/{token}/space/{file_id}', 'verb' => 'GET'],

		// wopi-like access control
		['name' => 'Session#check', 'url' => '/session/check/{token}', 'verb' => 'GET'],
		['name' => 'Session#create', 'url' => '/session', 'verb' => 'POST'],
		['name' => 'Session#publicCreate', 'url' => '/s/session', 'verb' => 'POST'],
		['name' => 'Session#delete', 'url' => '/session/{token}', 'verb' => 'DELETE'],
		['name' => 'Session#publicDelete', 'url' => '/s/session/{token}', 'verb' => 'DELETE'],
	]
];
