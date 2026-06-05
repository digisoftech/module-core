<?php
/**
 * Copyright © Digisoftech. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Digisoftech\Core\Model\Service;

use Digisoftech\Core\Model\Config;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\HTTP\ClientInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class LicenseApiClient
{
    private const VERIFY_ENDPOINT = '/api/v1/magento/license/verify';
    private const REQUEST_TIMEOUT = 15;

    public function __construct(
        private readonly Config $config,
        private readonly InstalledModulesProvider $installedModulesProvider,
        private readonly StoreManagerInterface $storeManager,
        private readonly ProductMetadataInterface $productMetadata,
        private readonly ClientInterface $curl,
        private readonly Json $json,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function verifyLicense(?string $licenseKey = null): array
    {
        $licenseKey = $licenseKey ?? $this->config->getLicenseKey();

        if ($licenseKey === null || $licenseKey === '') {
            return $this->buildErrorResponse(
                'MISSING_LICENSE_KEY',
                (string) __('Please enter a license key before verifying.')
            );
        }

        return $this->sendRequest(self::VERIFY_ENDPOINT, $this->buildPayload($licenseKey));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(string $licenseKey): array
    {
        return [
            'license_key' => $licenseKey,
            'domain' => $this->getDomain(),
            'base_url' => $this->getBaseUrl(),
            'magento_edition' => $this->productMetadata->getEdition(),
            'magento_version' => $this->productMetadata->getVersion(),
            'php_version' => PHP_VERSION,
            'modules' => $this->installedModulesProvider->getInstalledExtensionModules(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sendRequest(string $endpoint, array $payload): array
    {
        $url = $this->config->getApiBaseUrl() . $endpoint;

        try {
            $this->curl->setTimeout(self::REQUEST_TIMEOUT);
            $this->curl->addHeader('Content-Type', 'application/json');
            $this->curl->addHeader('Accept', 'application/json');
            $this->curl->addHeader('User-Agent', 'Digisoftech-Core/1.0');
            $this->curl->post($url, $this->json->serialize($payload));

            $statusCode = $this->curl->getStatus();
            $body = $this->curl->getBody();

            if ($statusCode < 200 || $statusCode >= 300) {
                $this->logger->warning('Digisoftech license API returned a non-success status.', [
                    'status_code' => $statusCode,
                ]);

                return $this->buildErrorResponse(
                    'API_HTTP_ERROR',
                    (string) __('Unable to verify the license at this time. Please try again later.')
                );
            }

            if ($body === '') {
                return $this->buildErrorResponse(
                    'API_EMPTY_RESPONSE',
                    (string) __('The license server returned an empty response.')
                );
            }

            /** @var array<string, mixed> $response */
            $response = $this->json->unserialize($body);

            return $response;
        } catch (\Throwable $exception) {
            $this->logger->error('Digisoftech license API request failed.', [
                'exception' => $exception->getMessage(),
            ]);

            return $this->buildErrorResponse(
                'API_CONNECTION_ERROR',
                (string) __('Unable to connect to the Digisoftech license server. Please try again later.')
            );
        }
    }

    /**
     * @return array{success: false, error_code: string, message: string}
     */
    private function buildErrorResponse(string $errorCode, string $message): array
    {
        return [
            'success' => false,
            'error_code' => $errorCode,
            'message' => $message,
        ];
    }

    private function getDomain(): string
    {
        $host = (string) $this->storeManager->getStore()->getBaseUrl(
            \Magento\Framework\UrlInterface::URL_TYPE_WEB,
            false
        );

        $host = (string) parse_url($host, PHP_URL_HOST);

        return $host !== '' ? $host : 'localhost';
    }

    private function getBaseUrl(): string
    {
        return rtrim(
            (string) $this->storeManager->getStore()->getBaseUrl(
                \Magento\Framework\UrlInterface::URL_TYPE_WEB,
                true
            ),
            '/'
        ) . '/';
    }
}
