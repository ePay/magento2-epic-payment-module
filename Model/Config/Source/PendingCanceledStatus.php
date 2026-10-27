<?php
namespace Epay\Magento2EpicPaymentModule\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;


class PendingCanceledStatus implements ArrayInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'pending_payment', 'label' => __('Pending Payment')],
            ['value' => 'canceled', 'label' => __('Canceled')],
        ];
    }
}