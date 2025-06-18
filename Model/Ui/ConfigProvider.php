<?php
namespace Epay\Magento2EpicPaymentModule\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class ConfigProvider implements ConfigProviderInterface
{
    const CODE = 'epayepicpayment';

    /** @var UrlInterface **/
    private $urlBuilder;

    protected $scopeConfig;

    public function __construct(ScopeConfigInterface $scopeConfig, UrlInterface $urlBuilder)
    {
        $this->scopeConfig = $scopeConfig;
        $this->urlBuilder = $urlBuilder;
    }

    public function getConfig()
    {
        $logger = \Magento\Framework\App\ObjectManager::getInstance()->get(\Psr\Log\LoggerInterface::class);
        $logger->info('Epay ConfigProvider loaded');

        $description = $this->scopeConfig->getValue(
            'payment/' . self::CODE . '/description',
            ScopeInterface::SCOPE_STORE
        );

        return [
            'payment' => [
                self::CODE => [
                    'description' => $description,
                    'jsComponent' => 'Epay_Magento2EpicPaymentModule/js/view/payment/method-renderer/epayepicpayment',
                    'isActive' => true,
                    'redirectUrl' => $this->urlBuilder->getUrl(
                        'epay/payment/redirect',
                        ['_secure' => true]
                    )
                ]
            ]
        ];
    }
}
