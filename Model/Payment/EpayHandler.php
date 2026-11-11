<?php
declare(strict_types=1);

namespace Epay\Magento2EpicPaymentModule\Model\Payment;

use Epay\Magento2EpicPaymentModule\Helper\EpayPaymentHelper;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

class EpayHandler
{
    private const CONNECT_TIMEOUT_SECONDS = 10;
    private const REQUEST_TIMEOUT_SECONDS = 30;

    private $epay_apikey;
    private $epay_pos;
    private $base_url;
    private EpayPaymentHelper $epayPaymentHelper;
    private LoggerInterface $logger;

    public function __construct(EpayPaymentHelper $epayPaymentHelper, LoggerInterface $logger)
    {
        $this->base_url = "https://payments.epay.eu";
        $this->epayPaymentHelper = $epayPaymentHelper;
        $this->logger = $logger;
    }

    public function setAuthData($apikey, $posid=null)
    {
        $this->epay_apikey = $apikey;
        $this->epay_pos = $posid;
    }

    public function createPaymentRequest($orderId, $amountMinorUnits, $currency, $instantCapture, $successUrl, $returnUrl=null, $failureUrl=null, $notificationUrl=null, $ageVerificationMinimumAge=null, $ageVerificationCountry=null, $customerId=null, $customerData=null, $orderLines=null, array $allowedPaymentMethods = [], bool $isPaymentLink = false)
    {
        $ePayParameters = array(
            "reference" => preg_replace('/[^A-Za-z0-9._-]/', '', (string)$orderId),
            "pointOfSaleId" => $this->epay_pos,
            "amount" => $amountMinorUnits,
            "currency" => $currency,
            "scaMode" => "NORMAL",
            "timeout" => 120,
            "instantCapture" => $instantCapture,
            "maxAttempts" => 5,
            "notificationUrl" => $notificationUrl,
            "successUrl" => $successUrl,
            "returnUrl" => $returnUrl,
            "failureUrl" => $failureUrl
        );

        if ($allowedPaymentMethods) {
            $ePayParameters['allowedPaymentMethods'] = $allowedPaymentMethods;
        }

        if ($isPaymentLink) {
            unset($ePayParameters['timeout']);
        }

        if ($customerData && is_array($customerData)) {
            $ePayParameters['customer'] = $customerData;
        }

        if ($orderLines && is_array($orderLines)) {
            $ePayParameters['orderLines'] = $orderLines;
        }

        if(isset($ageVerificationMinimumAge) && $ageVerificationMinimumAge > 0)
        {
            $ePayParameters['ageVerification']['minimumAge'] = intval($ageVerificationMinimumAge);
            $ePayParameters['ageVerification']['country'] = $ageVerificationCountry;
        }

        if($customerId != null)
        {
            $ePayParameters['customerId'] = $customerId;
        }
        
        $endpoint_URL = $this->base_url . ($isPaymentLink
            ? '/public/api/v1/payment-links'
            : '/public/api/v1/cit');
        $headers = $isPaymentLink
            ? ['Idempotency-Key' => hash('sha256', (string)$orderId)]
            : [];
        $result = $this->post($endpoint_URL, $ePayParameters, $headers);

        return json_decode($result);
    }

    public function createPaymentLink(...$arguments)
    {
        $arguments[] = true;

        return $this->createPaymentRequest(...$arguments);
    }

    public function capture($transactionId, $amount)
    {
        $ePayParameters = array(
            "amount" => $amount
        );

        $endpoint_URL = $this->base_url."/public/api/v1/transactions/".$transactionId."/capture";
        
        return $this->requireSuccessfulOperation(
            $this->post($endpoint_URL, $ePayParameters, [], 'capture'),
            'capture'
        );
    }

    public function refund($transactionId, $amount)
    {
        $ePayParameters = array(
            "amount" => $amount
        );

        $endpoint_URL = $this->base_url."/public/api/v1/transactions/".$transactionId."/refund";
        
        return $this->requireSuccessfulOperation(
            $this->post($endpoint_URL, $ePayParameters, [], 'refund'),
            'refund'
        );
    }

    public function void($transactionId, $amount=null)
    {
        $ePayParameters = array(
            "amount" => $amount
        );

        $endpoint_URL = $this->base_url."/public/api/v1/transactions/".$transactionId."/void";
        
        return $this->requireSuccessfulOperation(
            $this->post($endpoint_URL, $ePayParameters, [], 'void'),
            'void'
        );
    }

    public function delete_subscription($subscription_id)
    {
        $endpoint_URL = $this->base_url."/public/api/v1/subscriptions/".$subscription_id;
        
        $result = $this->delete($endpoint_URL);

        return $result;
    }

    public function payment_info($transactionId)
    {
        $endpoint_URL = $this->base_url."/public/api/v1/transactions/".$transactionId;

        $authamount = 0;
        $capturedamount = 0;
        $refundedamount = 0;
        $voidedamount = 0;

        $result = $this->get($endpoint_URL);

        $data = json_decode($result);
        if(!isset($data->success) && is_array($data->operations))
        {
            foreach($data->operations AS $operation)
            {
                if($operation->state == "SUCCESS" && ($operation->type == "AUTHORIZATION" || $operation->type == "SALE"))
                {
                    $authamount += $operation->amount;
                }
                elseif($operation->state == "SUCCESS" && ($operation->type == "CAPTURE" || $operation->type == "SALE"))
                {
                    $capturedamount += $operation->amount;
                }
                elseif($operation->state == "SUCCESS" && $operation->type == "REFUND")
                {
                    $refundedamount += $operation->amount;
                }
                elseif($operation->state == "SUCCESS" && $operation->type == "VOID")
                {
                    $voidedamount += $operation->amount;
                }
            }

            $data->authamount = $authamount;
            $data->capturedamount = $capturedamount;
            $data->refundedamount = $refundedamount;
            $data->voidedamount = $voidedamount;
        }
        
        $result = json_encode($data);

        return $result;
    }

    private function post(string $endpointUrl, array $data, array $additionalHeaders = [], string $operation = 'request'): string
    {
        $jsonData = json_encode($data);
        if ($jsonData === false) {
            throw new LocalizedException(__('The ePay request could not be prepared.'));
        }

        $ch = curl_init($endpointUrl);
        if ($ch === false) {
            throw new LocalizedException(__('The ePay connection could not be initialized.'));
        }

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->epay_apikey,
            'X-EPay-System: ' . $this->epayPaymentHelper->getModuleHeaderInfo(),
        ];
        foreach ($additionalHeaders as $name => $value) {
            $headers[] = $name . ': ' . $value;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::CONNECT_TIMEOUT_SECONDS);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::REQUEST_TIMEOUT_SECONDS);

        $result = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($result === false) {
            $this->logger->error('ePay request failed before receiving a response', [
                'operation' => $operation,
                'curl_error' => $curlError,
            ]);
            throw new LocalizedException(__('The ePay request failed. Verify the payment status before trying again.'));
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $this->logger->error('ePay request returned an unsuccessful HTTP status', [
                'operation' => $operation,
                'http_status' => $httpCode,
            ]);
            throw new LocalizedException(__('The ePay request was rejected.'));
        }

        return $result;
    }

    private function requireSuccessfulOperation(string $response, string $operation): string
    {
        $data = json_decode($response, true);
        if (!is_array($data) || !array_key_exists('success', $data) || $data['success'] !== true) {
            $this->logger->error('ePay operation returned an unsuccessful response', [
                'operation' => $operation,
            ]);
            throw new LocalizedException(__('The ePay %1 operation was not completed.', $operation));
        }

        return $response;
    }

    private function get($endpoint_URL)
    {
        $ch = curl_init($endpoint_URL);

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->epay_apikey,
            'X-EPay-System: ' . $this->epayPaymentHelper->getModuleHeaderInfo(),
        ]);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if($http_code == "200")
        {
            return $result;
        }
        else
        {
            return $result;
        }

    }

    private function delete($endpoint_URL)
    {
        $ch = curl_init($endpoint_URL);

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->epay_apikey,
            'X-EPay-System: ' . $this->epayPaymentHelper->getModuleHeaderInfo(),
        ]);

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if($http_code == "200")
        {
            return $result;
        }
        else
        {
            return $result;
        }

    }
}
