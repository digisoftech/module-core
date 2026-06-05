<?php
/**
 * Copyright © Digisoftech. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Digisoftech\Core\Controller\Adminhtml\License;

use Digisoftech\Core\Model\Config;
use Digisoftech\Core\Model\Service\LicenseStatusManager;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;

class Refresh extends Action
{
    public const ADMIN_RESOURCE = Config::ACL_CONFIGURATION;

    public function __construct(
        Context $context,
        private readonly LicenseStatusManager $licenseStatusManager
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        /** @var Json $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        $this->licenseStatusManager->invalidateCache();
        $status = $this->licenseStatusManager->getStatus(true);

        return $result->setData($status);
    }
}
