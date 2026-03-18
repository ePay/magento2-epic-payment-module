<?php
declare(strict_types=1);

namespace Epay\Magento2EpicPaymentModule\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Api\Data\OrderAddressInterface;

class EpayPaymentHelper extends AbstractHelper
{
    private ProductMetadataInterface $productMetadata;
    private ModuleListInterface $moduleList;

    public function __construct(
        Context $context,
        ProductMetadataInterface $productMetadata,
        ModuleListInterface $moduleList
    ) {
        parent::__construct($context);
        $this->productMetadata = $productMetadata;
        $this->moduleList = $moduleList;
    }

    public function getModuleHeaderInfo(): string
    {
        $magentoVersion = $this->productMetadata->getVersion();
        $phpVersion = PHP_VERSION;

        $moduleName = 'Epay_Magento2EpicPaymentModule';
        $moduleInfo = $this->moduleList->getOne($moduleName);
        $moduleVersion = $moduleInfo['setup_version'] ?? 'unknown';

        return "Magento/{$magentoVersion} Module/{$moduleVersion} PHP/{$phpVersion}";
    }

    /**
     * Create invoice data array
     *
     * @param OrderInterface $order
     * @param bool $enableInvoice
     * @return array|false
     */
    public function createCustomerData(OrderInterface $order): array
    {
        $customerData = [];

        $billingAddress = $order->getBillingAddress();
        $shippingAddress = $order->getShippingAddress() ?: $billingAddress;

        if ($billingAddress instanceof OrderAddressInterface) {
            $email = trim((string)($billingAddress->getEmail() ?: $order->getCustomerEmail() ?: ''));
            $email = filter_var($email, FILTER_SANITIZE_EMAIL);

            $customerData['firstName'] = $this->nullIfEmpty(
                $this->limit((string)$billingAddress->getFirstname(), 50)
            );

            $customerData['lastName'] = $this->nullIfEmpty(
                $this->limit((string)$billingAddress->getLastname(), 50)
            );

            $customerData['email'] = filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;

            $customerData['phoneNumber'] = $this->normalizePhone(
                (string)$billingAddress->getTelephone(),
                (string)$billingAddress->getCountryId()
            );

            $customerData['billingAddress']['line1'] = $this->nullIfEmpty(
                $this->limit($this->getStreetLine($billingAddress, 0), 50)
            );

            $customerData['billingAddress']['postalCode'] = $this->nullIfEmpty(
                $this->limit((string)$billingAddress->getPostcode(), 16)
            );

            $customerData['billingAddress']['city'] = $this->nullIfEmpty(
                $this->limit((string)$billingAddress->getCity(), 50)
            );

            $customerData['billingAddress']['countryCode'] = $this->nullIfEmpty(
                strtoupper(trim((string)$billingAddress->getCountryId()))
            );
        }

        if ($shippingAddress instanceof OrderAddressInterface) {

            $customerData['shippingAddress']['line1'] = $this->nullIfEmpty(
                $this->limit($this->getStreetLine($shippingAddress, 0), 50)
            );

            $customerData['shippingAddress']['postalCode'] = $this->nullIfEmpty(
                $this->limit((string)$shippingAddress->getPostcode(), 16)
            );

            $customerData['shippingAddress']['city'] = $this->nullIfEmpty(
                $this->limit((string)$shippingAddress->getCity(), 50)
            );

            $customerData['shippingAddress']['countryCode'] = $this->nullIfEmpty(
                strtoupper(trim((string)$shippingAddress->getCountryId()))
            );
        }

        return $customerData;
    }

    private function getStreetLine(OrderAddressInterface $address, int $index = 0): string
    {
        $street = $address->getStreet();

        if (is_array($street)) {
            return trim((string)($street[$index] ?? ''));
        }

        return trim((string)$street);
    }

    private function limit(string $value, int $length): string
    {
        return mb_substr(trim($value), 0, $length);
    }

    private function nullIfEmpty(string $value): ?string
    {
        $value = trim($value);
        return $value !== '' ? $value : null;
    }

    /**
     * Normalize and validate a phone number.
     *
     * Accepted formats:
     *  - +CCxxxxxxxx
     *  - +CC xx xx xx
     *  - 00CCxxxxxxxx
     *  - 00CC xx xx xx
     *
     * If no country code is present and billing country is Denmark (DK),
     * the Danish country code (+45) will be prepended.
     *
     * @param string|null $rawPhone Raw phone number input.
     * @param string|null $country  Billing country (ISO-2).
     * @return string|null Normalized phone number or null if invalid.
     */
    public function normalizePhone(?string $rawPhone, ?string $country = null): ?string
    {
        $raw = trim(strip_tags((string)$rawPhone));

        if ($raw === '') {
            return null;
        }

        // Allow only digits, + and whitespace
        $raw = preg_replace('/[^\d+\s]/', '', $raw);
        $raw = preg_replace('/\s+/', ' ', $raw);

        $country = strtoupper(trim((string)$country));

        $cc  = null;
        $num = null;

        $knownCc = [
            '358', // Finland
            '359', // Bulgarien
            '370', // Litauen
            '371', // Letland
            '372', // Estland
            '373', // Moldova
            '374', // Armenien
            '375', // Belarus
            '376', // Andorra
            '377', // Monaco
            '378', // San Marino
            '380', // Ukraine
            '381', // Serbien
            '382', // Montenegro
            '383', // Kosovo
            '385', // Kroatien
            '386', // Slovenien
            '387', // Bosnien-Hercegovina
            '389', // Nordmakedonien
            '420', // Tjekkiet
            '421', // Slovakiet
            '423', // Liechtenstein

            '30', // Grækenland
            '31', // Holland
            '32', // Belgien
            '33', // Frankrig
            '34', // Spanien
            '36', // Ungarn
            '39', // Italien
            '40', // Rumænien
            '41', // Schweiz
            '43', // Østrig
            '44', // Storbritannien
            '45', // Danmark
            '46', // Sverige
            '47', // Norge
            '48', // Polen
            '49', // Tyskland

            '350', // Gibraltar
            '351', // Portugal
            '352', // Luxembourg
            '353', // Irland
            '354', // Island
            '355', // Albanien
            '356', // Malta
            '357', // Cypern
        ];

        // +CC xxxx (requires space)
        if (preg_match('/^\+(\d{1,3})\s+(\d[\d\s]*)$/', $raw, $m)) {
            $cc  = $m[1];
            $num = preg_replace('/\s+/', '', $m[2]);

            // +CCxxxx (no space → use knownCc)
        } elseif (preg_match('/^\+(\d+)$/', $raw, $m)) {
            $digits = $m[1];

            foreach ($knownCc as $code) {
                if (str_starts_with($digits, $code)) {
                    $cc  = $code;
                    $num = substr($digits, strlen($code));
                    break;
                }
            }

            // 00CC xxxx (requires space)
        } elseif (preg_match('/^00(\d{1,3})\s+(\d[\d\s]*)$/', $raw, $m)) {
            $cc  = $m[1];
            $num = preg_replace('/\s+/', '', $m[2]);

            // 00CCxxxx (no space → use knownCc)
        } elseif (preg_match('/^00(\d+)$/', $raw, $m)) {
            $digits = $m[1];

            foreach ($knownCc as $code) {
                if (str_starts_with($digits, $code)) {
                    $cc  = $code;
                    $num = substr($digits, strlen($code));
                    break;
                }
            }

            // No explicit country code → DK fallback
        } elseif ($country === 'DK') {
            $cc  = '45';
            $num = preg_replace('/\s+/', '', $raw);
        }

        // Final validation
        if ($cc && $num && ctype_digit($num)) {
            return '+' . $cc . ' ' . $num;
        }

        return null;
    }

    public function createOrderLines(OrderInterface $order): array
    {
        $lines = [];

        foreach ($order->getItems() as $item) {

            if ($item->getParentItemId()) {
                continue;
            }

            $qty = (float)$item->getQtyOrdered();
            if ($qty <= 0) {
                continue;
            }

            $unitPrice = (float)$item->getPriceInclTax();
            $rowTotalInclTax = (float)$item->getRowTotalInclTax();

            if ($rowTotalInclTax <= 0) {
                $rowTotalInclTax = (float)$item->getRowTotal() + (float)$item->getTaxAmount();
            }

            $taxAmount = (float)$item->getTaxAmount();
            $taxPercent = $rowTotalInclTax > 0
                ? round(($taxAmount / ($rowTotalInclTax - $taxAmount)) * 100, 2)
                : 0;

            $lines[] = [
                'type' => 'PHYSICAL',
                'description' => $this->limit((string)$item->getName(), 255),
                'quantity' => (int)$qty,

                'unitPrice' => (int)round($unitPrice * 100),
                'totalAmount' => (int)round($rowTotalInclTax * 100),

                //'taxAmount' => (int)round($taxAmount * 100),
                //'taxPercent' => $taxPercent,
            ];
        }

        if ((float)$order->getShippingAmount() > 0) {
            $shippingInclTax = (float)$order->getShippingInclTax();
            $shippingTax = (float)$order->getShippingTaxAmount();

            $lines[] = [
                'type' => 'SHIPPING_FEE',
                'description' => 'Shipping',
                'quantity' => 1,
                'unitPrice' => (int)round($shippingInclTax * 100),
                'totalAmount' => (int)round($shippingInclTax * 100),
                //'taxAmount' => (int)round($shippingTax * 100),
                //'taxPercent' => $shippingInclTax > 0
                //    ? round(($shippingTax / ($shippingInclTax - $shippingTax)) * 100, 2)
                //    : 0,
            ];
        }

        if ((float)$order->getDiscountAmount() < 0) {
            $discount = abs((float)$order->getDiscountAmount());

            $lines[] = [
                'type' => 'DISCOUNT',
                'description' => 'Discount',
                'quantity' => 1,
                'unitPrice' => (int)round($discount * 100),
                'totalAmount' => (int)round($discount * 100),
                // 'taxAmount' => 0,
                // 'taxPercent' => 0,
            ];
        }

        return $lines;
    }
}