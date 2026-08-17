<?php
namespace Epay\Magento2EpicPaymentModule\Controller\Payment;

use Epay\Magento2EpicPaymentModule\Model\Payment\PaymentRegistrationChecker;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Api\OrderRepositoryInterface;

class Accept extends Action
{
    /** @var CheckoutSession */
    protected $checkoutSession;
    /** @var OrderRepositoryInterface */
    protected $orderRepository;
    /** @var PageFactory */
    protected $resultPageFactory;
    /** @var PaymentRegistrationChecker */
    protected $paymentRegistrationChecker;

    public function __construct(
        Context $context,
        CheckoutSession $checkoutSession,
        OrderRepositoryInterface $orderRepository,
        PageFactory $resultPageFactory,
        PaymentRegistrationChecker $paymentRegistrationChecker
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->orderRepository = $orderRepository;
        $this->resultPageFactory = $resultPageFactory;
        $this->paymentRegistrationChecker = $paymentRegistrationChecker;
    }

    public function execute()
    {
        $orderId = $this->checkoutSession->getLastOrderId();
        if (!$orderId) {
            return $this->_redirect('checkout/cart');
        }

        $order = $this->orderRepository->get($orderId);

        if (!$this->paymentRegistrationChecker->isPaymentRegistered($order)) {
            $resultPage = $this->resultPageFactory->create();
            $resultPage->getConfig()->getTitle()->set(__('Payment being processed'));

            return $resultPage;
        }

        return $this->_redirect('checkout/onepage/success');
    }
}
