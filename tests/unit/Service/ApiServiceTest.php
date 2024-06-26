<?php

namespace OCA\ApproveLinks\Tests;

use OCA\ApproveLinks\AppInfo\Application;
use OCA\ApproveLinks\Service\ApiService;

class ApiServiceTest extends \PHPUnit\Framework\TestCase {

	private ApiService $apiService;

	protected function setUp(): void {
		$app = new Application();
		$c = $app->getContainer();
		$this->apiService = $c->get(ApiService::class);
	}

	public function testDummy() {
		$app = new Application();
		$this->assertEquals('approve_links', $app::APP_ID);
	}

	public function testSignature() {
		$approveCallbackUri = 'http://localhost/approve';
		$rejectCallbackUri = 'http://localhost/reject';
		$description = 'description';

		$link = $this->apiService->generateLink($approveCallbackUri, $rejectCallbackUri, $description);

		$urlQuery = parse_url($link, PHP_URL_QUERY);
		parse_str($urlQuery, $query);
		$linkSignature = $query['signature'] ?? null;

		$signature = $this->apiService->getSignature($approveCallbackUri, $rejectCallbackUri, $description);

		$this->assertEquals($linkSignature, $signature);
	}
}
