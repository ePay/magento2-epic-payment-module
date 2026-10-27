<?php
declare(strict_types=1);

namespace Epay\Magento2EpicPaymentModule\Block\Info;

use Magento\Payment\Block\Info;

class Epay extends Info
{
    protected $_template = 'Epay_Magento2EpicPaymentModule::info/epayepicpayment.phtml';

    public function getPaymentType(): ?string
    {
        $info = $this->getInfo();
        if (!$info) {
            return null;
        }

        $ccType = trim((string)$info->getCcType());
        if ($ccType !== '') {
            return $ccType;
        }

        $subtype = trim((string)$info->getAdditionalInformation('epay_payment_subtype'));
        if ($subtype !== '') {
            return $subtype;
        }

        return null;
    }

    public function getPaymentMethodDisplayText(): ?string
    {
        $method_title = $this->getInfo()->getAdditionalInformation('method_title');

        return $method_title ?? null;
    }

    public function getCardNumber(): ?string
    {
        $info = $this->getInfo();
        if (!$info) {
            return null;
        }

        $ccNumber = $info->getCcNumberEnc();
        if (!empty($ccNumber)) {
            return $ccNumber;
        }

        $alt = $info->getAdditionalInformation('epay_payment_cardnumber');
        if (!empty($alt)) {
            return $alt;
        }

        return null;
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
