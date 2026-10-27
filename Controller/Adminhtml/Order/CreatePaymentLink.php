<?php
declare(strict_types=1);

namespace Epay\Magento2EpicPaymentModule\Controller\Adminhtml\Order;

use Epay\Magento2EpicPaymentModule\Model\Payment\LinkGenerator;
use Epay\Magento2EpicPaymentModule\Model\Payment\PaymentRegistrationChecker;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

class CreatePaymentLink extends Action
{
    public const ADMIN_RESOURCE = 'Epay_Magento2EpicPaymentModule::create_payment_link';

    private LinkGenerator $linkGenerator;
    private PaymentRegistrationChecker $paymentRegistrationChecker;
    private OrderRepositoryInterface $orderRepository;

    public function __construct(
        Context $context,
        LinkGenerator $linkGenerator,
        PaymentRegistrationChecker $paymentRegistrationChecker,
        OrderRepositoryInterface $orderRepository
    ) {
        parent::__construct($context);
        $this->linkGenerator = $linkGenerator;
        $this->paymentRegistrationChecker = $paymentRegistrationChecker;
        $this->orderRepository = $orderRepository;
    }

    public function execute(): Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $orderId = (int)$this->getRequest()->getParam('order_id');

        if ($orderId <= 0) {
            $this->messageManager->addErrorMessage(__('The order could not be found.'));

            return $resultRedirect->setPath('sales/order/index');
        }

        try {
            $order = $this->orderRepository->get($orderId);
            $this->assertPaymentLinkCanBeCreated($order);

            $paymentLink = $this->linkGenerator->generatePaymentLink($order);
            $this->messageManager->addSuccessMessage(
                __('Payment link created. Copy and send this link to the customer: %1', $paymentLink)
            );
        } catch (NoSuchEntityException $exception) {
            $this->messageManager->addErrorMessage(__('The order could not be found.'));
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        } catch (\Throwable $exception) {
            $this->messageManager->addErrorMessage(__('The payment link could not be created.'));
        }

        return $resultRedirect->setPath('sales/order/view', ['order_id' => $orderId]);
    }

    private function assertPaymentLinkCanBeCreated(Order $order): void
    {
        $payment = $order->getPayment();
        if (!$payment || !$this->linkGenerator->supportsPaymentMethod((string)$payment->getMethod())) {
            throw new LocalizedException(__('This order does not use an ePay EPIC payment method.'));
        }

        if ($this->paymentRegistrationChecker->isPaymentRegistered($order)) {
            throw new LocalizedException(__('A payment link cannot be created for an order that has already been paid.'));
        }

        if (in_array($order->getState(), [
            Order::STATE_CANCELED,
            Order::STATE_CLOSED,
            Order::STATE_COMPLETE,
        ], true)) {
            throw new LocalizedException(__('A payment link cannot be created for this order.'));
        }
    }
}
