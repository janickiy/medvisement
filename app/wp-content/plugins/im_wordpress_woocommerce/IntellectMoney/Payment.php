<?php

namespace PaySystem;

use PaySystem\Exceptions as Exceptions;

require_once("UserSettings.php");
require_once("Order.php");
require_once("Customer.php");
require_once("MerchantReceiptHelper.php");
require_once("LanguageHelper.php");
require_once("Validator.php");

//error_reporting(E_ALL);
//ini_set("display_errors", 1);

class Payment {

    private static $instance;
    private $UserSettings;
    private $Order;
    private $Customer;
    private $MerchantReceipt;
    private $LanguageHelper;

    public static function getInstance($UserSettings = NULL, $Order = NULL, $Customer = NULL, $lang = 'ru') {
        if (empty(self::$instance)) {
            if (!is_null($UserSettings) && !is_null($Order) && !is_null($Customer)) {
                self::$instance = new self($UserSettings, $Order, $Customer, $lang);
            } else {
                return false;
            }
        }
        return self::$instance;
    }

    private function __construct($UserSettings, $Order, $Customer, $lang) {
        $this->UserSettings = $UserSettings;
        $this->Order = $Order;
        $this->Customer = $Customer;
        $this->LanguageHelper = LanguageHelper::getInstance($lang);
        $recipientCurrency = $this->UserSettings->getTestMode() && $this->UserSettings->getIntegrationMethod() != 'P2P' ? Currency::TST : $this->Order->getRecipientCurrency();
        $this->Order->setRecipientCurrency($recipientCurrency);
    }

    public function generateForm($isOrganization = true, $isAutoSubmit = false) {
        $params = $isOrganization ? $this->getParamsForOrganization() : $this->getParamsForP2P();
        return $this->getForm($params, $isAutoSubmit);
    }

    public function generateHideInput($isOrganization = true) {
        $params = $isOrganization ? $this->getParamsForOrganization() : $this->getParamsForP2P();
        return $this->getForm($params, false);
    }

    private function getHideInput($params) {
        $input = '';
        foreach ($params as $name => $value) {
            $input .= '<input type="hidden" name="' . $name . '" value="' . htmlentities($value) . '">';
        }
        return $input;
    }

    private function getForm($params, $isAutoSubmit) {
        $form = "<form id='IntellectMoneyPaymentForm' method='POST' action='" . $this->UserSettings->getMerchantUrl() . "/" . $this->LanguageHelper->getLanguage() . "/'>";
        foreach ($params as $name => $value) {
            $form .= '<input type="hidden" name="' . $name . '" value="' . htmlentities($value) . '">';
        }
        $form .= "</form>";
        if ($isAutoSubmit) {
            $form .= "<script>"
                    . "document.getElementById('IntellectMoneyPaymentForm').submit();"
                    . "</script>";
        }
        return $form;
    }

    public function getParamsForOrganization() {
        $this->setItems();
        $params = array(
            'eshopId' => $this->UserSettings->getEshopId(),
            'holdMode' => (int) $this->UserSettings->getHoldMode(),
            'merchantReceipt' => $this->MerchantReceipt->generateMerchantReceipt($this->UserSettings->getIsUseMerchantReceiptEntities()),
            'hash' => $this->getHash()
        );
        if ($this->UserSettings->getHoldMode()) {
            $params['holdTime'] = $this->UserSettings->getHoldTime();
        }

        return $this->replaceParamsToInvoiceIdIfThisIsset(
            array_filter(
                array_merge($params, $this->getCommonParams()), 
                array(__CLASS__, "isSetValue")
            )
        );
    }
    
    private function isSetValue($value) {
        return !empty($value);
    }

    private function replaceParamsToInvoiceIdIfThisIsset($params) {
        $invoiceId = $this->Order->getInvoiceId();
        return $invoiceId ? array('invoiceId' => $invoiceId) : $params;
    }

    private function setItems() {
        $this->MerchantReceipt = new MerchantReceiptHelper($this->Order->getRecipientAmount(), $this->UserSettings->getInn(), $this->Customer->getContact(), $this->UserSettings->getGroup(), $this->Order->getDiscount(), $this->Order->getOriginalAmount(), $this->UserSettings->getMerchantReceiptType());

        foreach ($this->Order->getItems() as $item) {
            $this->MerchantReceipt->addItem($item->price, $item->quantity, $item->text, $item->tax, $item->paymentSubjectType, $item->paymentMethodType);
        }
    }

    private function getCommonParams($forP2p = false) {
        return array_merge($this->UserSettings->getUserFields(), array(
            'orderId' => $this->Order->getOrderId(),
            'serviceName' => $this->getServiceName(),
            'recipientAmount' => $this->Order->getRecipientAmount(),
            'recipientCurrency' => $this->Order->getRecipientCurrency(),
            'userEmail' => $this->Customer->getEmail(),
            'resultUrl' => $this->UserSettings->getResultUrl(),
            'successUrl' => $this->UserSettings->getSuccessUrl(),
            'backUrl' => $this->UserSettings->getBackUrl(),
            'expireDate' => $this->UserSettings->getConvertedExpireDate(),
            'preference' => $this->UserSettings->getPreference(),
        ), $this->Order->getUserFields($forP2p));
    }

    private function getHash() {
        $paramsToHash = array(
            $this->UserSettings->getEshopId(),
            $this->Order->getOrderId(),
            $this->getServiceName(),
            $this->Order->getRecipientAmount(),
            $this->Order->getRecipientCurrency(),
            $this->UserSettings->getSecretKey()
        );
        $hashStr = implode('::', $paramsToHash);
        return md5($hashStr);
    }

    public function getParamsForP2P() {
        $params = array(
            'eshopId' => $this->UserSettings->getDefaultEshopIdForP2P(),
            'UserField_0' => $this->UserSettings->getAccountId(),
            'UserFieldName_0' => $this->LanguageHelper->getTitle('UserFieldName_0'),
            'UserField_9' => $this->UserSettings->getFormId(),
            'UserFieldName_9' => $this->LanguageHelper->getTitle('UserFieldName_9'),
        );
        return $this->replaceParamsToInvoiceIdIfThisIsset(
            array_filter(
                array_merge($params, $this->getCommonParams(true)), 
                array(__CLASS__, "isSetValue")
            )
        );
    }

    private function getServiceName() {
        return $this->LanguageHelper->getTitle('serviceName') . $this->Order->getOrderId();
    }

}

?>