<?php
/**
 * Copyright © Digisoftech. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Digisoftech\Core\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    public const ACL_CONFIGURATION = 'Digisoftech_Core::configuration';

    private const XML_PATH_LICENSE_KEY = 'digisoftech_core/license/license_key';
    private const XML_PATH_API_BASE_URL = 'digisoftech_core/license/api_base_url';
    private const XML_PATH_PURCHASE_URL = 'digisoftech_core/license/purchase_url';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor
    ) {
    }

    public function getLicenseKey(): ?string
    {
        $value = (string) $this->scopeConfig->getValue(
            self::XML_PATH_LICENSE_KEY,
            ScopeInterface::SCOPE_STORE
        );

        if ($value === '') {
            return null;
        }

        return $this->encryptor->decrypt($value);
    }

    public function getApiBaseUrl(): string
    {
        return rtrim((string) $this->scopeConfig->getValue(
            self::XML_PATH_API_BASE_URL,
            ScopeInterface::SCOPE_STORE
        ), '/');
    }

    public function getPurchaseUrl(): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_PURCHASE_URL,
            ScopeInterface::SCOPE_STORE
        );
    }
}
