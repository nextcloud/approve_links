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

	public function testOldValidLink() {
		$approveCallbackUri = 'http://localhost/approve';
		$rejectCallbackUri = 'http://localhost/reject';
		$description = 'description';
		$signature = $this->apiService->getSignature($approveCallbackUri, $rejectCallbackUri, $description, null);
		$signatureIsValid = $this->apiService->checkSignature($approveCallbackUri, $rejectCallbackUri, $description, $signature);
		$this->assertTrue($signatureIsValid);
	}

	public function testOldInvalidLinks() {
		$approveCallbackUri = 'http://localhost/approve';
		$rejectCallbackUri = 'http://localhost/reject';
		$description = 'description';
		$validSignature = $this->apiService->getSignature($approveCallbackUri, $rejectCallbackUri, $description, null);

		$signatureIsValid = $this->apiService->checkSignature($approveCallbackUri, $rejectCallbackUri, $description, $validSignature);
		$this->assertTrue($signatureIsValid);

		$signatureIsValid = $this->apiService->checkSignature($approveCallbackUri, $rejectCallbackUri, $description, $validSignature . 'a');
		$this->assertFalse($signatureIsValid, 'Signature check should fail with an invalid signature');

		$signatureIsValid = $this->apiService->checkSignature($approveCallbackUri . 'a', $rejectCallbackUri, $description, $validSignature);
		$this->assertFalse($signatureIsValid, 'Signature check should fail with an incorrect approve callback URI');

		$signatureIsValid = $this->apiService->checkSignature($approveCallbackUri, $rejectCallbackUri . 'a', $description, $validSignature);
		$this->assertFalse($signatureIsValid, 'Signature check should fail with an incorrect reject callback URI');

		$signatureIsValid = $this->apiService->checkSignature($approveCallbackUri, $rejectCallbackUri, $description . 'a', $validSignature);
		$this->assertFalse($signatureIsValid, 'Signature check should fail with an incorrect description');
	}

	public function testValidLink() {
		$approveCallbackUri = 'http://localhost/approve';
		$rejectCallbackUri = 'http://localhost/reject';
		$description = 'description';

		$link = $this->apiService->generateLink($approveCallbackUri, $rejectCallbackUri, $description);

		$urlQuery = parse_url($link, PHP_URL_QUERY);
		parse_str($urlQuery, $query);
		$linkSignature = $query['signature'] ?? null;
		$this->assertNotNull($linkSignature, 'There should be a signature in the link');
		$linkId = $query['id'] ?? null;
		$this->assertNotNull($linkId, 'There should be an ID in the link');
		$linkId = (int)$linkId;

		$signatureIsValid = $this->apiService->checkSignature($approveCallbackUri, $rejectCallbackUri, $description, $linkSignature, $linkId);
		$this->assertTrue($signatureIsValid);
	}

	public function testInvalidLinks() {
		$approveCallbackUri = 'http://localhost/approve';
		$rejectCallbackUri = 'http://localhost/reject';
		$description = 'description';

		$link = $this->apiService->generateLink($approveCallbackUri, $rejectCallbackUri, $description);

		$urlQuery = parse_url($link, PHP_URL_QUERY);
		parse_str($urlQuery, $query);
		$linkSignature = $query['signature'] ?? null;
		$this->assertNotNull($linkSignature, 'There should be a signature in the link');
		$linkId = $query['id'] ?? null;
		$this->assertNotNull($linkId, 'There should be an ID in the link');
		$linkId = (int)$linkId;

		$signatureIsValid = $this->apiService->checkSignature($approveCallbackUri, $rejectCallbackUri, $description, $linkSignature, $linkId);
		$this->assertTrue($signatureIsValid);

		$signatureIsValid = $this->apiService->checkSignature($approveCallbackUri, $rejectCallbackUri, $description, $linkSignature . 'a', $linkId);
		$this->assertFalse($signatureIsValid);

		$signatureIsValid = $this->apiService->checkSignature($approveCallbackUri, $rejectCallbackUri, $description, $linkSignature, $linkId + 1);
		$this->assertFalse($signatureIsValid, 'Signature check should fail with an incorrect link ID');

		$signatureIsValid = $this->apiService->checkSignature($approveCallbackUri . 'a', $rejectCallbackUri, $description, $linkSignature, $linkId);
		$this->assertFalse($signatureIsValid, 'Signature check should fail with an incorrect approve callback URI');

		$signatureIsValid = $this->apiService->checkSignature($approveCallbackUri, $rejectCallbackUri . 'a', $description, $linkSignature, $linkId);
		$this->assertFalse($signatureIsValid, 'Signature check should fail with an incorrect reject callback URI');

		$signatureIsValid = $this->apiService->checkSignature($approveCallbackUri, $rejectCallbackUri, $description . 'a', $linkSignature, $linkId);
		$this->assertFalse($signatureIsValid, 'Signature check should fail with an incorrect description');
	}
}
