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
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[BruteForceProtection(action: 'approvePage')]
	public function index(
		string $approveCallbackUri, string $rejectCallbackUri, string $description, string $signature, ?string $userId = null,
	): TemplateResponse {
		$error = null;
		if (!$this->apiService->checkSignature($approveCallbackUri, $rejectCallbackUri, $description, $signature, $userId)) {
			$error = 'Bad signature';
		}
		// if the signature is correct but the current user is not authorized
		if ($error === null && $userId !== null && $userId !== $this->userId) {
			$error = 'Unauthorized user';
		}

		if ($error !== null) {
			$params = [
				'errors' => [
					['error' => $error],
				],
			];
			$response = new TemplateResponse(
				'',
				'error',
				$params,
				TemplateResponse::RENDER_AS_ERROR
			);
			$response->setStatus(Http::STATUS_UNAUTHORIZED);
			$response->throttle(['reason' => $error]);
			return $response;
		}

		$response = new PublicTemplateResponse('approve_links', 'page');
		// $response->setHeaderDetails($this->l10n->t('', []));
		$response->setFooterVisible(false);
		return $response;
	}
}
