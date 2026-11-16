<?php
declare(strict_types=1);

namespace Epay\Magento2EpicPaymentModule\Plugin\Inventory;

use Magento\InventorySales\Model\GetSkuFromOrderItem;
use Magento\Sales\Api\Data\OrderItemInterface;

class SkipSurchargeSkuLookup
{
    private const SURCHARGE_SKU = 'epay_surcharge_fee';

    public function aroundExecute(
        GetSkuFromOrderItem $subject,
        callable $proceed,
        OrderItemInterface $orderItem
    ): string {
        if ($orderItem->getSku() === self::SURCHARGE_SKU) {
            return self::SURCHARGE_SKU;
        }

        return $proceed($orderItem);
    }
}
