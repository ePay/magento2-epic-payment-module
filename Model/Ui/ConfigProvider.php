<?php
namespace Epay\Magento2EpicPaymentModule\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Epay\Magento2EpicPaymentModule\Model\Config\CardLogoProvider;
use Magento\Store\Model\ScopeInterface;

class ConfigProvider implements ConfigProviderInterface
{
    private const BNPL_CODES = [
        'epayepicpayment_anyday',
        'epayepicpayment_klarna',
        'epayepicpayment_viabill',
    ];

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
        $bnplWarningText = (string)$this->scopeConfig->getValue(
            'payment/epayepicpayment/bnpl_warning_text',
            ScopeInterface::SCOPE_STORE
        );
        foreach (self::CODES as $code) {
            $paymentConfig[$code] = [
                'description' => (string)$this->scopeConfig->getValue(
                    'payment/' . $code . '/description',
                    ScopeInterface::SCOPE_STORE
                ),
                'logos' => $this->cardLogoProvider->getLogos($code),
                'bnplWarningText' => $bnplWarningText,
                'showBnplWarning' => in_array($code, self::BNPL_CODES, true)
                    || ($code === 'epayepicpayment' && $this->cardLogoProvider->hasBnplLogo()),
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
