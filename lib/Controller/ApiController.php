<?php
/**
 * Nextcloud - ApproveLinks
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 * @copyright Julien Veyssier 2024
 */

namespace OCA\ApproveLinks\Controller;

use Exception;
use OCA\ApproveLinks\AppInfo\Application;
use OCA\ApproveLinks\Service\ApiService;
use OCA\ApproveLinks\Settings\Admin;
use OCA\ApproveLinks\SignatureException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\Attribute\BruteForceProtection;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;

use OCP\IRequest;

class ApiController extends OCSController {

	public function __construct(
		string $appName,
		IRequest $request,
		private ApiService $apiService,
		?string $userId
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * @param string $approveCallbackUri
	 * @param string $rejectCallbackUri
	 * @param string $description
	 * @return DataResponse
	 */
	#[NoCSRFRequired]
	#[AuthorizedAdminSetting(settings: Admin::class)]
	public function generateLink(string $approveCallbackUri, string $rejectCallbackUri, string $description): DataResponse {
		$link = $this->apiService->generateLink($approveCallbackUri, $rejectCallbackUri, $description);
		if (strlen($link) > Application::MAX_GENERATED_LINK_LENGTH) {
			$responseData = [
				'error' => 'link_too_long',
			];
			return new DataResponse($responseData, Http::STATUS_BAD_REQUEST);
		}
		$responseData = [
			'link' => $link,
		];
		$response = new DataResponse($responseData);
		$response->cacheFor(60 * 60 * 24, false, true);
		return $response;
	}

	/**
	 * @param string $approveCallbackUri
	 * @param string $rejectCallbackUri
	 * @param string $description
	 * @param string $signature
	 * @return DataResponse
	 * @throws \Throwable
	 */
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[PublicPage]
	#[BruteForceProtection(action: 'approveLink')]
	public function approve(string $approveCallbackUri, string $rejectCallbackUri, string $description, string $signature): DataResponse {
		try {
			$approveResult = $this->apiService->approve($approveCallbackUri, $rejectCallbackUri, $description, $signature);
			$responseData = [
				'result' => $approveResult,
			];
			return new DataResponse($responseData);
		} catch (SignatureException $e) {
			$response = new DataResponse(['error' => 'signature', 'message' => $e->getMessage()], Http::STATUS_UNAUTHORIZED);
			$response->throttle(['reason' => 'bad signature']);
			return $response;
		} catch (Exception $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * @param string $approveCallbackUri
	 * @param string $rejectCallbackUri
	 * @param string $description
	 * @param string $signature
	 * @return DataResponse
	 * @throws \Throwable
	 */
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[PublicPage]
	#[BruteForceProtection(action: 'rejectLink')]
	public function reject(string $approveCallbackUri, string $rejectCallbackUri, string $description, string $signature): DataResponse {
		try {
			$rejectResult = $this->apiService->reject($approveCallbackUri, $rejectCallbackUri, $description, $signature);
			$responseData = [
				'result' => $rejectResult,
			];
			return new DataResponse($responseData);
		} catch (SignatureException $e) {
			$response = new DataResponse(['error' => 'signature', 'message' => $e->getMessage()], Http::STATUS_UNAUTHORIZED);
			$response->throttle(['reason' => 'bad signature']);
			return $response;
		} catch (Exception $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}
}
