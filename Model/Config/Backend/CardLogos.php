<?php
declare(strict_types=1);

namespace Epay\Magento2EpicPaymentModule\Model\Config\Backend;

use Epay\Magento2EpicPaymentModule\Model\Config\CardLogoProvider;
use Magento\Framework\App\Config\Value;

class CardLogos extends Value
{
    public function beforeSave()
    {
        $value = $this->getValue();
        if (is_array($value)) {
            $value = array_filter(array_map('trim', $value));
            $this->setValue($value ? implode(',', $value) : CardLogoProvider::NO_LOGOS);
        } elseif ($value === null || $value === '') {
            $this->setValue(CardLogoProvider::NO_LOGOS);
        }

        return parent::beforeSave();
    }
}
