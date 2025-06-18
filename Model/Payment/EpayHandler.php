<?php
namespace Epay\Magento2EpicPaymentModule\Model\Payment;

class EpayHandler {

    private $epay_apikey;
    private $epay_pos;
    private $base_url;
    private $urlBuilder;

    public function __construct()
    {
        $this->base_url = "https://payments.epay.eu";
    }

    public function setAuthData($apikey, $posid=null)
    {
        $this->epay_apikey = $apikey;
        $this->epay_pos = $posid;
    }

    public function createPaymentRequest($orderId, $amountMinorUnits, $currency, $instantCapture, $successUrl, $failureUrl=null, $notificationUrl=null)
    {
        $ePayParameters = array(
            "reference" => $orderId,
            "pointOfSaleId" => $this->epay_pos,
            "amount" => $amountMinorUnits,
            "currency" => $currency,
            "scaMode" => "NORMAL",
            "timeout" => 120,
            "instantCapture" => $instantCapture,
            "maxAttempts" => 5,
            "notificationUrl" => $notificationUrl,
            "successUrl" => $successUrl,
            "failureUrl" => $failureUrl
        );
        
        $endpoint_URL = $this->base_url."/public/api/v1/cit";
        $result = $this->post($endpoint_URL, $ePayParameters);

        return json_decode($result);
    }

    public function capture($transactionId, $amount)
    {
        $ePayParameters = array(
            "amount" => $amount
        );

        $endpoint_URL = $this->base_url."/public/api/v1/transactions/".$transactionId."/capture";
        
        $result = $this->post($endpoint_URL, $ePayParameters);

        return $result;
    }

    public function refund($transactionId, $amount)
    {
        $ePayParameters = array(
            "amount" => $amount
        );

        $endpoint_URL = $this->base_url."/public/api/v1/transactions/".$transactionId."/refund";
        
        $result = $this->post($endpoint_URL, $ePayParameters);

        return $result;
    }

    public function void($transactionId, $amount=null)
    {
        $ePayParameters = array(
            "amount" => $amount
        );

        $endpoint_URL = $this->base_url."/public/api/v1/transactions/".$transactionId."/void";
        
        $result = $this->post($endpoint_URL, $ePayParameters);

        return $result;
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

    private function post($endpoint_URL, array $data)
    {
        $jsonData = json_encode($data);

        $ch = curl_init($endpoint_URL);

        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json', 
            'Authorization: Bearer ' . $this->epay_apikey,
            'Content-Length: ' . strlen($jsonData)
        ));

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);

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

    private function get($endpoint_URL)
    {
        $ch = curl_init($endpoint_URL);

        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json', 
            'Authorization: Bearer ' . $this->epay_apikey
        ));

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

        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json', 
            'Authorization: Bearer ' . $this->epay_apikey
        ));

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
