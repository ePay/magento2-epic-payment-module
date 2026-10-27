<?php
namespace Epay\Magento2EpicPaymentModule\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Controller\Result\RedirectFactory;
use Epay\Magento2EpicPaymentModule\Model\Payment\LinkGenerator;

class Redirect extends Action
{
    /** @var CheckoutSession */
    protected $checkoutSession;

    /** @var RedirectFactory */
    protected $resultRedirectFactory;

    /** @var LinkGenerator */
    protected $linkGenerator;

    public function __construct(
        Context $context,
        CheckoutSession $checkoutSession,
        RedirectFactory $resultRedirectFactory,
        LinkGenerator $linkGenerator
    ) {
        parent::__construct($context);
        $this->checkoutSession      = $checkoutSession;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->linkGenerator        = $linkGenerator;
    }

    public function execute()
    {
        $order = $this->checkoutSession->getLastRealOrder();
        if (!$order->getId()) {
            return $this->resultRedirectFactory->create()->setPath('checkout/cart');
        }

        $url = $this->linkGenerator->generateLink($order);

        $redirect = $this->resultRedirectFactory->create();
        return $redirect->setUrl($url);
    }
}
