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
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;

use OCP\IRequest;

class ApiController extends OCSController {

	public function __construct(
		string $appName,
		IRequest $request,
		private ApiService $apiService,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Generate a link
	 *
	 * Generate an approval link from the callback URIs and a description.
	 *
	 * @param string $approveCallbackUri The URI to request on approval
	 * @param string $rejectCallbackUri The URI to request on rejection
	 * @param string $description The approval description
	 * @return DataResponse<Http::STATUS_OK, array{link: string}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: string}, array{}>
	 *
	 * 200: The link has been successfully generated
	 * 400: The generated link is too long
	 */
	#[NoCSRFRequired]
	#[AuthorizedAdminSetting(settings: Admin::class)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['api'])]
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
	 * Approve
	 *
	 * Approve to trigger a request to the approve callback URI.
	 *
	 * @param string $approveCallbackUri The URI to request on approval
	 * @param string $rejectCallbackUri The URI to request on rejection
	 * @param string $description The approval description
	 * @param string $signature The approval link signature
	 * @return DataResponse<Http::STATUS_OK, array{result: array{body: string}}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_UNAUTHORIZED, array{error: string, message: string}, array{}>
	 * @throws \Throwable
	 *
	 * 200: The callback URI has been successfully requested
	 * 401: The signature is incorrect
	 * 400: There was an error while approving
	 */
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[PublicPage]
	#[BruteForceProtection(action: 'approveLink')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['api'])]
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
			return new DataResponse(['error' => 'unknown', 'message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Reject
	 *
	 * Reject to trigger a request to the reject callback URI.
	 *
	 * @param string $approveCallbackUri The URI to request on approval
	 * @param string $rejectCallbackUri The URI to request on rejection
	 * @param string $description The approval description
	 * @param string $signature The approval link signature
	 * @return DataResponse<Http::STATUS_OK, array{result: array{body: string}}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_UNAUTHORIZED, array{error: string, message: string}, array{}>
	 * @throws \Throwable
	 *
	 * 200: The callback URI has been successfully requested
	 * 401: The signature is incorrect
	 * 400: There was an error while rejecting
	 */
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[PublicPage]
	#[BruteForceProtection(action: 'rejectLink')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['api'])]
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
			return new DataResponse(['error' => 'unknown', 'message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}
}
