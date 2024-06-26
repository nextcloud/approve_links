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
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\Template\PublicTemplateResponse;
use OCP\AppFramework\Http\TemplateResponse;

use OCP\IRequest;

class PageController extends Controller {

	public function __construct(
		string   $appName,
		IRequest $request,
		private ApiService $apiService,
		private ?string $userId
	) {
		parent::__construct($appName, $request);
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[BruteForceProtection(action: 'approvePage')]
	public function index(string $approveCallbackUri, string $rejectCallbackUri, string $description, string $signature): TemplateResponse {
		if (!$this->apiService->checkSignature($approveCallbackUri, $rejectCallbackUri, $description, $signature)) {
			$params = [
				'errors' => [
					['error' => 'Bad signature'],
				],
			];
			$response = new TemplateResponse(
				'',
				'error',
				$params,
				TemplateResponse::RENDER_AS_ERROR
			);
			$response->setStatus(Http::STATUS_UNAUTHORIZED);
			$response->throttle(['reason' => 'bad signature']);
			return $response;
		}
		$response = new PublicTemplateResponse('approve_links', 'page');
		//$response->setHeaderDetails($this->trans->t('Enter link password of project %s', [$publicShareInfo['projectid']]));
		$response->setFooterVisible(false);
		return $response;
	}
}
