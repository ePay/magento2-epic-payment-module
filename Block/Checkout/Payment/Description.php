<?php
declare(strict_types=1);

namespace Epay\Magento2EpicPaymentModule\Block\Checkout\Payment;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Template;
use Magento\Store\Model\ScopeInterface;

class Description extends Template
{
    private const CONFIG_PATH = 'payment/epayepicpayment/description';

    private ScopeConfigInterface $scopeConfig;

    public function __construct(
        Template\Context $context,
        ScopeConfigInterface $scopeConfig,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->scopeConfig = $scopeConfig;
    }

    public function getDescription(): string
    {
        return (string)$this->scopeConfig->getValue(
            self::CONFIG_PATH,
            ScopeInterface::SCOPE_STORE
        );
    }
}
