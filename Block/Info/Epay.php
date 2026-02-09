<?php
declare(strict_types=1);

namespace Epay\Magento2EpicPaymentModule\Block\Info;

use Magento\Payment\Block\Info;

class Epay extends Info
{
    protected $_template = 'Epay_Magento2EpicPaymentModule::info/epayepicpayment.phtml';

    public function getPaymentType(): ?string
    {
        return $this->getInfo()->getCcType();
    }

    public function getPaymentMethodDisplayText(): ?string
    {
        $method_title = $this->getInfo()->getAdditionalInformation('method_title');

        return $method_title ?? null;
    }

    public function getCardNumber(): ?string
    {
        return $this->getInfo()->getCcNumberEnc();
    }

    public function getTransactionId(): ?string
    {
        $lastTransId = $this->getInfo()->getLastTransId();
        if ($lastTransId) {
            return $lastTransId;
        }

        return $this->getInfo()->getTransactionId();
    }
}
