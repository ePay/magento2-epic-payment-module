<?php
declare(strict_types=1);

namespace Epay\Magento2EpicPaymentModule\Model\Config\Source;

use Epay\Magento2EpicPaymentModule\Model\Config\CardLogoProvider;

class CardLogos
{
    private CardLogoProvider $cardLogoProvider;

    public function __construct(CardLogoProvider $cardLogoProvider)
    {
        $this->cardLogoProvider = $cardLogoProvider;
    }

    public function toOptionArray(): array
    {
        return $this->cardLogoProvider->getCardLogoOptions();
    }
}
