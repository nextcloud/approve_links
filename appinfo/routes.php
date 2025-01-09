<?php

/**
 * Nextcloud - Giphy
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 * @copyright Julien Veyssier 2022
 */

$requirements = [
	'apiVersion' => '(v1)',
	//	'token' => '^[a-z0-9]{4,30}$',
];

return [
	'routes' => [
		['name' => 'config#setAdminConfig', 'url' => '/admin-config', 'verb' => 'PUT'],
		['name' => 'page#index', 'url' => '/link', 'verb' => 'GET'],
	],
	'ocs' => [
		['name' => 'api#generateLink', 'url' => '/api/{apiVersion}/link', 'verb' => 'POST', 'requirements' => $requirements],
		['name' => 'api#approve', 'url' => '/api/{apiVersion}/approve', 'verb' => 'POST', 'requirements' => $requirements],
		['name' => 'api#reject', 'url' => '/api/{apiVersion}/reject', 'verb' => 'POST', 'requirements' => $requirements],
	],
];
