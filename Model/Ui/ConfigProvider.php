<?php
namespace Epay\Magento2EpicPaymentModule\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Epay\Magento2EpicPaymentModule\Model\Config\CardLogoProvider;
use Magento\Store\Model\ScopeInterface;

class ConfigProvider implements ConfigProviderInterface
{
    private const CODES = [
        'epayepicpayment',
        'epayepicpayment_anyday',
        'epayepicpayment_applepay',
        'epayepicpayment_googlepay',
        'epayepicpayment_klarna',
        'epayepicpayment_mobilepay',
        'epayepicpayment_swish',
        'epayepicpayment_viabill',
    ];

    /** @var UrlInterface **/
    private $urlBuilder;
    private CardLogoProvider $cardLogoProvider;

    protected $scopeConfig;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        UrlInterface $urlBuilder,
        CardLogoProvider $cardLogoProvider
    )
    {
        $this->scopeConfig = $scopeConfig;
        $this->urlBuilder = $urlBuilder;
        $this->cardLogoProvider = $cardLogoProvider;
    }

    public function getConfig()
    {
        $paymentConfig = [];
        foreach (self::CODES as $code) {
            $paymentConfig[$code] = [
                'description' => (string)$this->scopeConfig->getValue(
                    'payment/' . $code . '/description',
                    ScopeInterface::SCOPE_STORE
                ),
                'logos' => $this->cardLogoProvider->getLogos($code),
                'jsComponent' => 'Epay_Magento2EpicPaymentModule/js/view/payment/method-renderer/epayepicpayment',
                'isActive' => true,
                'redirectUrl' => $this->urlBuilder->getUrl(
                    'epay/payment/redirect',
                    ['_secure' => true]
                ),
            ];
        }

        return [
            'payment' => $paymentConfig,
        ];
    }
}
