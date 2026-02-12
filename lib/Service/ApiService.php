<?php

/**
 * Nextcloud - Approve links
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier
 * @copyright Julien Veyssier 2024
 */

namespace OCA\ApproveLinks\Service;

use Exception;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use OCA\ApproveLinks\AppInfo\Application;
use OCA\ApproveLinks\Db\Link;
use OCA\ApproveLinks\Db\LinkMapper;
use OCA\ApproveLinks\SignatureException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Security\ICrypto;
use Psr\Log\LoggerInterface;
use Throwable;

class ApiService {
	private IClient $client;

	public function __construct(
		string $appName,
		private LoggerInterface $logger,
		private IL10N $l10n,
		private ICrypto $crypto,
		private IUrlGenerator $urlGenerator,
		private LinkMapper $linkMapper,
		IClientService $clientService,
	) {
		$this->client = $clientService->newClient();
	}

	/**
	 * @param string $approveCallbackUri
	 * @param string $rejectCallbackUri
	 * @param string $description
	 * @param int|null $id
	 * @return string
	 */
	public function getSignature(string $approveCallbackUri, string $rejectCallbackUri, string $description, ?int $id): string {
		return hash('sha256', $this->crypto->calculateHMAC($approveCallbackUri . $rejectCallbackUri . $description . ($id ?? '')));
	}

	/**
	 * @param string $approveCallbackUri
	 * @param string $rejectCallbackUri
	 * @param string $description
	 * @param string $signature
	 * @param int|null $id
	 * @return bool
	 */
	public function checkSignature(
		string $approveCallbackUri, string $rejectCallbackUri, string $description, string $signature, ?int $id = null,
	): bool {
		return $this->getSignature($approveCallbackUri, $rejectCallbackUri, $description, $id) === $signature;
	}

	/**
	 * @param string $approveCallbackUri
	 * @param string $rejectCallbackUri
	 * @param string $description
	 * @param string|null $userId
	 * @return string
	 * @throws \OCP\DB\Exception
	 */
	public function generateLink(
		string $approveCallbackUri, string $rejectCallbackUri, string $description, ?string $userId = null,
	): string {
		// if it does not exist, create it
		$linkEntity = new Link();
		$linkEntity->setCreatedAt(time());
		$linkEntity->setUserId($userId ?? '');
		$linkEntity = $this->linkMapper->insert($linkEntity);

		$signature = $this->getSignature($approveCallbackUri, $rejectCallbackUri, $description, $linkEntity->getId());
		$link = $this->urlGenerator->linkToRouteAbsolute(Application::APP_ID . '.page.index', [
			'approveCallbackUri' => $approveCallbackUri,
			'rejectCallbackUri' => $rejectCallbackUri,
			'description' => $description,
			'signature' => $signature,
			'id' => $linkEntity->getId(),
		]);

		if (strlen($link) > Application::MAX_GENERATED_LINK_LENGTH) {
			$this->linkMapper->delete($linkEntity);
			throw new Exception('link_too_long');
		}

		return $link;
	}

	/**
	 * @param string $approveCallbackUri
	 * @param string $rejectCallbackUri
	 * @param string $description
	 * @param string $signature
	 * @param int|null $id
	 * @return array
	 * @throws MultipleObjectsReturnedException
	 * @throws SignatureException
	 * @throws Throwable
	 */
	public function approve(
		string $approveCallbackUri, string $rejectCallbackUri, string $description, string $signature, ?int $id = null,
	): array {
		if (!$this->checkSignature($approveCallbackUri, $rejectCallbackUri, $description, $signature, $id)) {
			throw new SignatureException();
		}
		if ($id !== null) {
			$this->checkDoneAt($id);
			$this->setDoneAt($id);
		}
		return $this->request($approveCallbackUri);
	}

	/**
	 * @param string $approveCallbackUri
	 * @param string $rejectCallbackUri
	 * @param string $description
	 * @param string $signature
	 * @param int|null $id
	 * @return array
	 * @throws MultipleObjectsReturnedException
	 * @throws SignatureException
	 * @throws Throwable
	 */
	public function reject(
		string $approveCallbackUri, string $rejectCallbackUri, string $description, string $signature, ?int $id = null,
	): array {
		if (!$this->checkSignature($approveCallbackUri, $rejectCallbackUri, $description, $signature, $id)) {
			throw new SignatureException();
		}
		if ($id !== null) {
			$this->checkDoneAt($id);
			$this->setDoneAt($id);
		}
		return $this->request($rejectCallbackUri);
	}

	/**
	 * Raise an exception if the link has already been done
	 *
	 * @param int $id
	 * @return void
	 * @throws MultipleObjectsReturnedException
	 * @throws \OCP\DB\Exception
	 */
	public function checkDoneAt(int $id): void {
		try {
			$linkEntity = $this->linkMapper->findById($id);
			if ($linkEntity->getDoneAt() !== null) {
				throw new Exception('link_already_done', Http::STATUS_CONFLICT);
			}
		} catch (DoesNotExistException $e) {
			throw new Exception('link_not_found', Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * @param int $id
	 * @return void
	 * @throws MultipleObjectsReturnedException
	 * @throws \OCP\DB\Exception
	 */
	private function setDoneAt(int $id): void {
		try {
			$linkEntity = $this->linkMapper->findById($id);
			$linkEntity->setDoneAt(time());
			$this->linkMapper->update($linkEntity);
		} catch (DoesNotExistException $e) {
			throw new Exception('link_not_found', Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * @param string $url
	 * @param array $params Query parameters (key/val pairs)
	 * @param string $method HTTP query method
	 * @return array decoded request result or error
	 * @throws Throwable
	 */
	public function request(string $url, array $params = [], string $method = 'GET'): array {
		try {
			$options = [
				'headers' => [
					'User-Agent' => 'Nextcloud ApproveLinks integration',
				],
			];

			if (count($params) > 0) {
				if ($method === 'GET') {
					$paramsContent = http_build_query($params);
					$url .= '?' . $paramsContent;
				} else {
					$options['body'] = json_encode($params);
				}
			}

			if ($method === 'GET') {
				$response = $this->client->get($url, $options);
			} elseif ($method === 'POST') {
				$response = $this->client->post($url, $options);
			} elseif ($method === 'PUT') {
				$response = $this->client->put($url, $options);
			} elseif ($method === 'DELETE') {
				$response = $this->client->delete($url, $options);
			} else {
				return ['error' => $this->l10n->t('Bad HTTP method')];
			}
			$body = $response->getBody();
			$respCode = $response->getStatusCode();

			if ($respCode >= 400) {
				throw new Exception('Bad credentials');
			} else {
				return ['body' => $body];
			}
		} catch (ClientException|ServerException $e) {
			$responseBody = $e->getResponse()->getBody();
			$this->logger->warning('ApproveLinks API error : ' . $e->getMessage(), ['response_body' => $responseBody, 'app' => Application::APP_ID]);
			throw $e;
		} catch (Exception|Throwable $e) {
			$this->logger->warning('ApproveLinks API error : ' . $e->getMessage(), ['app' => Application::APP_ID]);
			throw $e;
		}
	}
}
