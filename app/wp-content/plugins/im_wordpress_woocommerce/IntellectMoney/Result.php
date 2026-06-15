<?php

namespace PaySystem;

require_once("UserSettings.php");
require_once("Order.php");
require_once("LanguageHelper.php");
require_once("Status.php");
require_once("Currency.php");
require_once("ProcessingResponseResultStruct.php");

class Result extends IntellectMoneyBase {

    private static $instance;
    private $UserSettings;
    private $Order;
    private $LanguageHelper;
    private $requestParams;
    private $errorMessage;

    public static function getInstance($request = array(), $UserSettings = "", $Order = "", $lang = 'ru', $isCp1251 = false) {
        if (empty(self::$instance)) {
            self::$instance = new self($request, $UserSettings, $Order, $lang, $isCp1251);
        }
        return self::$instance;
    }

    private function __construct($request, $UserSettings, $Order, $lang = 'ru', $isCp1251 = false) {
        $this->LanguageHelper = LanguageHelper::getInstance($lang, $isCp1251);
        $this->UserSettings = UserSettings::getInstance();
        $this->Order = Order::getInstance();
        if (!empty($UserSettings) && !empty($Order)) {
            $this->setParams($request, $UserSettings, $Order, $lang, $isCp1251);
        }
        if (!isset($request) || !isset($this->Order) || !isset($this->UserSettings)) {
            $this->resetParams();
        }
    }

    public function setParams($request, $userSettings, $order, $lang = 'ru', $isCp1251 = false) {
        $this->UserSettings = $userSettings;
        $this->Order = $order;
        $this->getParams($request);
        if ($lang != 'ru') {
            $this->LanguageHelper->setLanguage($lang, $isCp1251);
        }
        if ($this->UserSettings->getTestMode()) {
            $this->Order->setRecipientCurrency(Currency::TST);
        }
    }

    public function resetParams() {
        $this->getParams(array());
        $this->UserSettings->resetParams();
        $this->Order->resetParams();
        $this->LanguageHelper->setLanguage('ru');
        $this->errorMessage = "";
    }

    private function getParams($request) {
        $this->requestParams = array();
        foreach ($request as $key => $value) {
            switch ($key) {
                case 'recipientAmount':
                case 'originalAmount':
                    $this->requestParams[$key] = $this->convertToPriceFormat($value);
                    break;
                case 'recipientCurrency':
                    $this->requestParams[$key] = strtoupper($value);
                    break;
                default:
                    $this->requestParams[$key] = $value;
                    break;
            }
        }
    }

    public function processingResponse() {
        ob_start();
        $checkResult = $this->doChecks();
        $result = new ProcessingResponseResultStruct(false, 0);

        if ($checkResult) {
            switch ($this->requestParams['paymentStatus']) {
                case Status::created:
                    $result = new ProcessingResponseResultStruct($this->Order->setStatusCreated(), $this->UserSettings->getStatusCreated());
                    break;
                case Status::cancelled:
                    $result = new ProcessingResponseResultStruct($this->Order->setStatusCancelled(), $this->UserSettings->getStatusCancelled());
                    break;
                case Status::paid:
                    $result = new ProcessingResponseResultStruct($this->Order->setStatusPaid(), $this->UserSettings->getStatusPaid());
                    break;
                case Status::holded:
                    $result = new ProcessingResponseResultStruct($this->Order->setStatusHolded(), $this->UserSettings->getStatusHolded());
                    break;
                case Status::partiallyPaid:
                    $result = new ProcessingResponseResultStruct($this->Order->setStatusPartiallyPaid(), $this->UserSettings->getStatusPartiallyPaid());
                    $this->Order->setPaidAmount($this->requestParams['recipientAmount']);
                    break;
                case Status::refunded:
                    $result = new ProcessingResponseResultStruct($this->Order->setStatusRefunded(), $this->UserSettings->getStatusRefunded());
                    break;
                default:
                    $result = new ProcessingResponseResultStruct(true, 0);
                    break;
            }
        }

        return $result;
    }

    private function doChecks() {
        $this->errorMessage = "";

        return $this->checkPaymentCurrency() &&
                $this->checkEshopId() &&
                $this->checkSecretKey() &&
                $this->checkPaymentAmount() &&
                $this->checkHash();
    }

    private function checkPaymentAmount() {
        if ((!empty($this->Order->getOriginalAmount() && isset($this->requestParams['recipientOriginalAmount']) && $this->requestParams['recipientOriginalAmount'] != $this->Order->getOriginalAmount())) || ($this->requestParams['paymentStatus'] == Status::partiallyPaid && $this->requestParams['recipientAmount'] >= $this->Order->getRecipientAmount())) {
            $this->errorMessage = $this->LanguageHelper->getError('amountDidNotMatch');
            return false;
        }
        return true;
    }

    private function checkPaymentCurrency() {
        if ($this->requestParams['recipientCurrency'] != $this->Order->getRecipientCurrency()) {
            $this->errorMessage = $this->LanguageHelper->getError('currencyDidNotMatch');
            return false;
        }
        return true;
    }

    private function checkEshopId() {
        if (
            $this->requestParams['eshopId'] != $this->UserSettings->getEshopId() 
            && !empty($this->requestParams['UserField_0']) 
            && $this->requestParams['eshopId'] != $this->UserSettings->getDefaultEshopIdForP2P()
        ) {
            $this->errorMessage = $this->LanguageHelper->getError('eshopIdDidNotMatch');
            return false;
        }
        return true;
    }

    private function checkSecretKey() {
        if (!empty($this->requestParams['secretKey']) && $this->requestParams['secretKey'] != $this->UserSettings->getSecretKey()) {
            $this->errorMessage = $this->LanguageHelper->getError('secretKeyDidNotMatch');
            return false;
        }
        return true;
    }

    private function checkHash() {
        $controlHashStr = implode('::', array(
            $this->requestParams['eshopId'], $this->requestParams['orderId'], $this->requestParams['serviceName'], $this->requestParams['eshopAccount'], $this->requestParams['recipientAmount'], $this->requestParams['recipientCurrency'], $this->requestParams['paymentStatus'], $this->requestParams['userName'], $this->requestParams['userEmail'], $this->requestParams['paymentData'], $this->UserSettings->getSecretKey()
        ));

        $controlHash = md5($controlHashStr);
        $controlHashUtf8 = md5(iconv('windows-1251', 'utf-8', $controlHashStr));
        if (!$this->requestParams['hash'] || ($this->requestParams['hash'] != $controlHash && $this->requestParams['hash'] != $controlHashUtf8)) {
            $this->errorMessage = sprintf($this->LanguageHelper->getError('hashDidNotMatch'), $controlHashStr, $controlHash, $controlHashUtf8, $this->requestParams['hash']);
            return false;
        }
        return true;
    }

    public function getMessage() {
        if (!empty($this->errorMessage)) {
            ob_end_flush();
            if ($this->checkIP()) {
                return $this->errorMessage;
            }
        } else {
            ob_end_clean();
            header("HTTP/1.0 200 OK");
            return "OK";
        }
    }

    private function checkIP() {
        $validIps = array("139.45.224.254", "62.122.184.254", "127.0.0.1");
    
        $result = in_array($_SERVER['REMOTE_ADDR'], $validIps);
        
        // Проверка IP за CDN.
        if (!$result && !empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $result = in_array($_SERVER['HTTP_CF_CONNECTING_IP'], $validIps);
        }
        if (!$result && !empty($_SERVER['HTTP_X_REAL_IP'])) {
            $result = in_array($_SERVER['HTTP_X_REAL_IP'], $validIps);
        }
        if (!$result && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            foreach ($ips as $ip) {
                $ip = trim($ip);
                if (in_array($ip, $validIps)) {
                    $result = true;
                    break;
                }
            }
        }
        
        return $result;
    }

}
