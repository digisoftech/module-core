<?php
/**
 * Copyright © Digisoftech. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Digisoftech\Core\Model\Config\Backend;

use Digisoftech\Core\Model\Service\LicenseStatusManager;
use Magento\Config\Model\Config\Backend\Encrypted;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\ValidatorException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

class LicenseKey extends Encrypted
{
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        EncryptorInterface $encryptor,
        private readonly LicenseStatusManager $licenseStatusManager,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $config,
            $cacheTypeList,
            $encryptor,
            $resource,
            $resourceCollection,
            $data
        );
    }

    public function beforeSave()
    {
        $value = (string) $this->getValue();

        if ($value !== '' && !preg_match('/^\*+$/', $value)) {
            $this->licenseStatusManager->invalidateCache();
            $response = $this->licenseStatusManager->getStatus(true, $value);

            if (($response['success'] ?? false) !== true) {
                throw new ValidatorException(
                    __((string) ($response['message'] ?? 'The license key could not be verified.'))
                );
            }
        }

        parent::beforeSave();

        return $this;
    }
}
