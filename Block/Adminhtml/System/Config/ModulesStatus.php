<?php
/**
 * Copyright © Digisoftech. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Digisoftech\Core\Block\Adminhtml\System\Config;

use Digisoftech\Core\Model\Config;
use Digisoftech\Core\Model\Service\LicenseStatusManager;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class ModulesStatus extends Field
{
    /**
     * @var string
     */
    protected $_template = 'Digisoftech_Core::system/config/modules_status.phtml';

    public function __construct(
        Context $context,
        private readonly LicenseStatusManager $licenseStatusManager,
        private readonly Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function render(AbstractElement $element): string
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();

        return parent::render($element);
    }

    protected function _getElementHtml(AbstractElement $element): string
    {
        return $this->toHtml();
    }

    /**
     * @return array<string, mixed>
     */
    public function getLicenseStatus(): array
    {
        return $this->licenseStatusManager->getStatus();
    }

    public function getRefreshUrl(): string
    {
        return $this->getUrl('digisoftech_core/license/refresh');
    }

    public function getPurchaseUrl(): string
    {
        return $this->config->getPurchaseUrl();
    }

    public function hasLicenseKey(): bool
    {
        return $this->config->getLicenseKey() !== null;
    }

    public function formatSupportExpiresAt(int|string|null $expiresAt): string
    {
        if ($expiresAt === null || $expiresAt === '') {
            return (string) __('N/A');
        }

        try {
            $date = is_numeric($expiresAt)
                ? (new \DateTimeImmutable())->setTimestamp((int) $expiresAt)
                : new \DateTimeImmutable((string) $expiresAt);

            return $this->_localeDate->formatDate($date, \IntlDateFormatter::MEDIUM);
        } catch (\Exception) {
            return (string) $expiresAt;
        }
    }
}
