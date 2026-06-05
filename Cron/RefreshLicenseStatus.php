<?php
/**
 * Copyright © Digisoftech. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Digisoftech\Core\Cron;

use Digisoftech\Core\Model\Config;
use Digisoftech\Core\Model\Service\LicenseStatusManager;
use Psr\Log\LoggerInterface;

class RefreshLicenseStatus
{
    public function __construct(
        private readonly Config $config,
        private readonly LicenseStatusManager $licenseStatusManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        if ($this->config->getLicenseKey() === null) {
            return;
        }

        try {
            $this->licenseStatusManager->invalidateCache();
            $this->licenseStatusManager->getStatus(true);
        } catch (\Throwable $exception) {
            $this->logger->error('Digisoftech license status refresh failed.', [
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
