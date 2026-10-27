<?php
declare(strict_types=1);

namespace Epay\Magento2EpicPaymentModule\Controller\Payment;

use Epay\Magento2EpicPaymentModule\Model\Payment\PaymentRegistrationChecker;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Api\OrderRepositoryInterface;

class Accept extends Action
{
    private CheckoutSession $checkoutSession;
    private OrderRepositoryInterface $orderRepository;
    private PageFactory $resultPageFactory;
    private PaymentRegistrationChecker $paymentRegistrationChecker;

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
        $orderId = (int)$this->checkoutSession->getLastOrderId();
        if ($orderId <= 0) {
            return $this->createPaymentResultPage(true);
        }

        $order = $this->orderRepository->get($orderId);
        if (!$this->paymentRegistrationChecker->isPaymentRegistered($order)) {
            return $this->createPaymentResultPage(false);
        }

        return $this->_redirect('checkout/onepage/success');
    }

    private function createPaymentResultPage(bool $isPaymentCompleted): Page
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->set(
            $isPaymentCompleted ? __('Payment completed') : __('Payment being processed')
        );

        $block = $resultPage->getLayout()->getBlock('epay.payment.pending');
        if ($block) {
            $block->setData('is_payment_completed', $isPaymentCompleted);
        }

        return $resultPage;
    }
}
