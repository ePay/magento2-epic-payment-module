<?php
namespace Epay\Magento2EpicPaymentModule\Model\Config\Source;

use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;

class AgeVerificationOptions extends AbstractSource
{
    /**
     * Get all options
     *
     * @return array
     */
    public function getAllOptions()
    {
        if (null === $this->_options) {
            $this->_options = [
                ['label' => 'None', 'value' => 0],
                ['label' => '15 years', 'value' => 15],
                ['label' => '16 years', 'value' => 16],
                ['label' => '18 years', 'value' => 18],
                ['label' => '21 years', 'value' => 21]
            ];
        }
        return $this->_options;
    }
}