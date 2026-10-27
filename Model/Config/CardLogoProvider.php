<?php
declare(strict_types=1);

namespace Epay\Magento2EpicPaymentModule\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Asset\Repository as AssetRepository;
use Magento\Store\Model\ScopeInterface;

class CardLogoProvider
{
    public const NO_LOGOS = '__none__';

    private const CARD_LOGOS = [
        'visa' => ['file' => 'visa.svg', 'alt' => 'Visa'],
        'mastercard' => ['file' => 'mastercard.svg', 'alt' => 'Mastercard'],
        'dankort' => ['file' => 'dankort.svg', 'alt' => 'Dankort'],
        'american_express' => ['file' => 'american_express.svg', 'alt' => 'American Express'],
        'diners_club' => ['file' => 'diners_club.svg', 'alt' => 'Diners Club'],
        'discover' => ['file' => 'discover.svg', 'alt' => 'Discover'],
        'jcb' => ['file' => 'jcb.svg', 'alt' => 'JCB'],
        'maestro' => ['file' => 'maestro.svg', 'alt' => 'Maestro'],
    ];

    private const PAYMENT_METHOD_LOGOS = [
        'epayepicpayment_anyday' => [['file' => 'anyday.svg', 'alt' => 'Anyday']],
        'epayepicpayment_applepay' => [['file' => 'applepay.svg', 'alt' => 'Apple Pay']],
        'epayepicpayment_googlepay' => [['file' => 'googlepay.svg', 'alt' => 'Google Pay']],
        'epayepicpayment_klarna' => [['file' => 'klarna.svg', 'alt' => 'Klarna']],
        'epayepicpayment_mobilepay' => [['file' => 'mobilepay.svg', 'alt' => 'MobilePay']],
        'epayepicpayment_swish' => [['file' => 'swish.svg', 'alt' => 'Swish']],
        'epayepicpayment_viabill' => [['file' => 'viabill.svg', 'alt' => 'ViaBill']],
    ];

    private ScopeConfigInterface $scopeConfig;
    private AssetRepository $assetRepository;

    public function __construct(ScopeConfigInterface $scopeConfig, AssetRepository $assetRepository)
    {
        $this->scopeConfig = $scopeConfig;
        $this->assetRepository = $assetRepository;
    }

    public function getCardLogoOptions(): array
    {
        $options = [];
        foreach (self::CARD_LOGOS as $id => $logo) {
            $options[] = ['value' => $id, 'label' => $logo['alt']];
        }

        return $options;
    }

    public function getLogos(string $paymentMethodCode): array
    {
        if ($paymentMethodCode === 'epayepicpayment') {
            return $this->resolveLogos($this->getConfiguredCardLogoIds());
        }

        return $this->resolveLogos(self::PAYMENT_METHOD_LOGOS[$paymentMethodCode] ?? []);
    }

    private function getConfiguredCardLogoIds(): array
    {
        $configuredValue = (string)$this->scopeConfig->getValue(
            'payment/epayepicpayment/card_logos',
            ScopeInterface::SCOPE_STORE
        );
        if ($configuredValue === '' || $configuredValue === self::NO_LOGOS) {
            return [];
        }

        return array_filter(array_map('trim', explode(',', $configuredValue)));
    }

    private function resolveLogos(array $logoIds): array
    {
        $logos = [];
        foreach ($logoIds as $logoId) {
            $logo = is_array($logoId) ? $logoId : self::CARD_LOGOS[$logoId] ?? null;
            if (!$logo) {
                continue;
            }

            $logos[] = [
                'alt' => $logo['alt'],
                'url' => $this->assetRepository->getUrl(
                    'Epay_Magento2EpicPaymentModule::images/' . $logo['file']
                ),
            ];
        }

        return $logos;
    }
}
