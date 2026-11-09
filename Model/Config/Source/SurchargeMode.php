<?php
declare(strict_types=1);

namespace Epay\Magento2EpicPaymentModule\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class SurchargeMode implements OptionSourceInterface
{
    public const ORDER_LINE = 'surcharge_order_line';
    public const SHIPPING = 'surcharge_shipping';

    public function toOptionArray(): array
    {
        return [
            ['value' => self::ORDER_LINE, 'label' => __('Create order line')],
            ['value' => self::SHIPPING, 'label' => __('Add to shipping & handling')],
        ];
    }
}
