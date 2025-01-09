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
use OCA\ApproveLinks\SignatureException;
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
		IClientService $clientService,
	) {
		$this->client = $clientService->newClient();
	}

	/**
	 * @param string $approveCallbackUri
	 * @param string $rejectCallbackUri
	 * @param string $description
	 * @return string
	 */
	public function getSignature(string $approveCallbackUri, string $rejectCallbackUri, string $description): string {
		return hash('sha256', $this->crypto->calculateHMAC($approveCallbackUri . $rejectCallbackUri . $description));
	}

	/**
	 * @param string $approveCallbackUri
	 * @param string $rejectCallbackUri
	 * @param string $description
	 * @param string $signature
	 * @return bool
	 */
	public function checkSignature(
		string $approveCallbackUri, string $rejectCallbackUri, string $description, string $signature,
	): bool {
		return $this->getSignature($approveCallbackUri, $rejectCallbackUri, $description) === $signature;
	}

	/**
	 * @param string $approveCallbackUri
	 * @param string $rejectCallbackUri
	 * @param string $description
	 * @return string
	 */
	public function generateLink(string $approveCallbackUri, string $rejectCallbackUri, string $description): string {
		return $this->urlGenerator->linkToRouteAbsolute(Application::APP_ID . '.page.index', [
			'approveCallbackUri' => $approveCallbackUri,
			'rejectCallbackUri' => $rejectCallbackUri,
			'description' => $description,
			'signature' => $this->getSignature($approveCallbackUri, $rejectCallbackUri, $description),
		]);
	}

	/**
	 * @param string $approveCallbackUri
	 * @param string $rejectCallbackUri
	 * @param string $description
	 * @param string $signature
	 * @return array
	 * @throws SignatureException
	 * @throws Throwable
	 */
	public function approve(
		string $approveCallbackUri, string $rejectCallbackUri, string $description, string $signature,
	): array {
		if (!$this->checkSignature($approveCallbackUri, $rejectCallbackUri, $description, $signature)) {
			throw new SignatureException();
		}
		return $this->request($approveCallbackUri);
	}

	/**
	 * @param string $approveCallbackUri
	 * @param string $rejectCallbackUri
	 * @param string $description
	 * @param string $signature
	 * @return array
	 * @throws SignatureException
	 * @throws Throwable
	 */
	public function reject(
		string $approveCallbackUri, string $rejectCallbackUri, string $description, string $signature,
	): array {
		if (!$this->checkSignature($approveCallbackUri, $rejectCallbackUri, $description, $signature)) {
			throw new SignatureException();
		}
		return $this->request($rejectCallbackUri);
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
