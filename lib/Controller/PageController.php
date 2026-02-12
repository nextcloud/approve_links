<?php

/**
 * Nextcloud - ApproveLinks
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 * @copyright Julien Veyssier 2022
 */

namespace OCA\ApproveLinks\Controller;

use OCA\ApproveLinks\Service\ApiService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\BruteForceProtection;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\Template\PublicTemplateResponse;
use OCP\AppFramework\Http\TemplateResponse;

use OCP\IRequest;

#[OpenAPI(scope: OpenAPI::SCOPE_IGNORE)]
class PageController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private ApiService $apiService,
	) {
		parent::__construct($appName, $request);
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[BruteForceProtection(action: 'approvePage')]
	public function index(
		string $approveCallbackUri, string $rejectCallbackUri, string $description, string $signature, ?int $id = null,
	): TemplateResponse {
		if (!$this->apiService->checkSignature($approveCallbackUri, $rejectCallbackUri, $description, $signature, $id)) {
			return $this->getErrorResponse('Bad signature');
		}

		if ($id !== null) {
			try {
				$this->apiService->checkDoneAt($id);
			} catch (\Exception $e) {
				if ($e->getCode() === Http::STATUS_CONFLICT) {
					return $this->getErrorResponse('This link has already been used', false);
				}
			}
		}

		$response = new PublicTemplateResponse('approve_links', 'page');
		$response->setFooterVisible(false);
		return $response;
	}

	private function getErrorResponse(string $message, bool $throttle = true): TemplateResponse {
		$params = [
			'errors' => [
				['error' => $message],
			],
		];
		$response = new TemplateResponse(
			'',
			'error',
			$params,
			TemplateResponse::RENDER_AS_ERROR
		);
		$response->setStatus(Http::STATUS_UNAUTHORIZED);
		if ($throttle) {
			$response->throttle(['reason' => $message]);
		}
		return $response;
	}
}
