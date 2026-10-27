<?php
declare(strict_types=1);

namespace Epay\Magento2EpicPaymentModule\Block\Checkout;

use Epay\Magento2EpicPaymentModule\Model\Config\CardLogoProvider;
use Magento\Framework\View\Element\Template;

class PaymentLogos extends Template
{
    private CardLogoProvider $cardLogoProvider;

    public function __construct(
        Template\Context $context,
        CardLogoProvider $cardLogoProvider,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->cardLogoProvider = $cardLogoProvider;
    }

    public function getLogos(): array
    {
        $method = $this->getData('method');
        if (!$method || !method_exists($method, 'getCode')) {
            return [];
        }

        return $this->cardLogoProvider->getLogos((string)$method->getCode());
    }
}
