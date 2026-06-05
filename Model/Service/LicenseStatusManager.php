<?php
/**
 * Copyright © Digisoftech. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Digisoftech\Core\Model\Service;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\Serializer\Json;

class LicenseStatusManager
{
    private const CACHE_KEY = 'digisoftech_core_license_status';
    private const CACHE_LIFETIME = 3600;
    private const CACHE_TAG = 'DIGISOFTECH_LICENSE';

    public function __construct(
        private readonly LicenseApiClient $licenseApiClient,
        private readonly InstalledModulesProvider $installedModulesProvider,
        private readonly CacheInterface $cache,
        private readonly Json $json
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getStatus(bool $forceRefresh = false, ?string $licenseKey = null): array
    {
        if (!$forceRefresh) {
            $cached = $this->getCachedStatus();

            if ($cached !== null) {
                return $cached;
            }
        }

        $response = $this->licenseApiClient->verifyLicense($licenseKey);
        $normalized = $this->normalizeResponse($response);

        if (($normalized['success'] ?? false) === true) {
            $this->cache->save(
                $this->json->serialize($normalized),
                self::CACHE_KEY,
                [self::CACHE_TAG],
                self::CACHE_LIFETIME
            );
        }

        return $normalized;
    }

    public function invalidateCache(): void
    {
        $this->cache->remove(self::CACHE_KEY);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getCachedStatus(): ?array
    {
        $cached = $this->cache->load(self::CACHE_KEY);

        if ($cached === false || $cached === '') {
            return null;
        }

        try {
            /** @var array<string, mixed> $data */
            $data = $this->json->unserialize($cached);
        } catch (\InvalidArgumentException) {
            return null;
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $response
     * @return array<string, mixed>
     */
    private function normalizeResponse(array $response): array
    {
        if (($response['success'] ?? false) !== true) {
            return [
                'success' => false,
                'error_code' => (string) ($response['error_code'] ?? 'UNKNOWN_ERROR'),
                'message' => (string) ($response['message'] ?? __('Unable to verify license status.')),
                'summary' => $this->buildLocalSummary([]),
                'modules' => $this->buildLocalModuleRows([]),
            ];
        }

        /** @var array<int, array<string, mixed>> $remoteModules */
        $remoteModules = $response['modules'] ?? [];

        return [
            'success' => true,
            'message' => (string) ($response['message'] ?? ''),
            'license' => $response['license'] ?? [],
            'summary' => $this->buildLocalSummary($remoteModules),
            'modules' => $this->buildLocalModuleRows($remoteModules),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $remoteModules
     * @return array{
     *     total_installed: int,
     *     support_active: int,
     *     support_expired: int
     * }
     */
    private function buildLocalSummary(array $remoteModules): array
    {
        $rows = $this->buildLocalModuleRows($remoteModules);
        $supportActive = 0;
        $supportExpired = 0;

        foreach ($rows as $row) {
            $status = (string) ($row['support_status'] ?? 'unknown');

            if ($status === 'active') {
                $supportActive++;
            } elseif ($status === 'expired' || $status === 'unlicensed') {
                $supportExpired++;
            }
        }

        return [
            'total_installed' => count($rows),
            'support_active' => $supportActive,
            'support_expired' => $supportExpired,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $remoteModules
     * @return array<int, array<string, mixed>>
     */
    private function buildLocalModuleRows(array $remoteModules): array
    {
        $remoteByPackage = [];
        $remoteByModule = [];

        foreach ($remoteModules as $remoteModule) {
            if (!empty($remoteModule['package_name'])) {
                $remoteByPackage[(string) $remoteModule['package_name']] = $remoteModule;
            }

            if (!empty($remoteModule['module_name'])) {
                $remoteByModule[(string) $remoteModule['module_name']] = $remoteModule;
            }
        }

        $rows = [];

        foreach ($this->installedModulesProvider->getInstalledExtensionModules() as $installedModule) {
            $remote = $remoteByPackage[$installedModule['package_name']]
                ?? $remoteByModule[$installedModule['module_name']]
                ?? [];

            $supportStatus = (string) ($remote['support_status'] ?? 'unknown');

            $rows[] = [
                'module_name' => $installedModule['module_name'],
                'package_name' => $installedModule['package_name'],
                'title' => $installedModule['title'],
                'version' => $installedModule['version'],
                'license_status' => (string) ($remote['license_status'] ?? 'unknown'),
                'support_status' => $supportStatus,
                'support_expires_at' => $remote['support_expires_at'] ?? null,
                'purchase_url' => (string) ($remote['purchase_url'] ?? ''),
            ];
        }

        return $rows;
    }
}
