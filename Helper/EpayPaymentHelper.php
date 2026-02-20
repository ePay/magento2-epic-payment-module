<?php
declare(strict_types=1);

namespace Epay\Magento2EpicPaymentModule\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Module\ModuleListInterface;

class EpayPaymentHelper extends AbstractHelper
{
    private ProductMetadataInterface $productMetadata;
    private ModuleListInterface $moduleList;

    public function __construct(
        Context $context,
        ProductMetadataInterface $productMetadata,
        ModuleListInterface $moduleList
    ) {
        parent::__construct($context);
        $this->productMetadata = $productMetadata;
        $this->moduleList = $moduleList;
    }

    public function getModuleHeaderInfo(): string
    {
        $magentoVersion = $this->productMetadata->getVersion();
        $phpVersion     = PHP_VERSION;

        $moduleName = 'Epay_Magento2EpicPaymentModule';
        $moduleInfo = $this->moduleList->getOne($moduleName);
        $moduleVersion = $moduleInfo['setup_version'] ?? 'unknown';

        return "Magento/{$magentoVersion} Module/{$moduleVersion} PHP/{$phpVersion}";
    }
}