<?php
namespace Epay\Magento2EpicPaymentModule\Model\Config\Source;

class Ageverificationmode implements \Magento\Framework\Option\ArrayInterface
{
    /**
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'ageverification_disabled', 'label' => "Disabled"],
            ['value' => 'ageverification_enabled_all', 'label' => "Enabled on all orders"],
            ['value' => 'ageverification_enabled_dk', 'label' => "Enabled on DK orders"],
        ];
    }
}