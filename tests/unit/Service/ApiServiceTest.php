<?php

namespace OCA\ApproveLinks\Tests;

use OCA\ApproveLinks\AppInfo\Application;

class ApiServiceTest extends \PHPUnit\Framework\TestCase {

	public function testDummy() {
		$app = new Application();
		$this->assertEquals('approve_links', $app::APP_ID);
	}

	public function testPaginationConversion() {
		$expecteds = [
			// mediaUrl => domainPrefix, fileName, cid, rid, ct
			[
				'https://media4.giphy.com/media/BaDsH4FpMBnqdK8J0g/giphy.gif?cid=ae23904804a21bf61bc9d904e66605c31a584d73c05db5ad&rid=giphy.gif&ct=g',
				['domainPrefix' => 'media4', 'fileName' => 'giphy.gif', 'cid' => 'ae23904804a21bf61bc9d904e66605c31a584d73c05db5ad', 'rid' => 'giphy.gif', 'ct' => 'g'],
			],
		];

		foreach ($expecteds as $expected) {
			$mediaUrl = $expected[0];
			$expected = $expected[1];
			$result = GiphyAPIService::getGifUrlInfo($mediaUrl);
			$this->assertEquals($expected, $result);
		}
	}
}
