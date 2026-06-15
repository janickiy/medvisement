<?php

namespace PaySystem;

require_once("Validator.php");
require_once("IntegrationMethod.php");

class UserSettings {

    const DefaultEshopIdForP2P = 450157;
    const DefaultTestEshopIdForP2P = 450040;

    private static $instance;
    private $isAllowChangeValues;
    //Общие значения
    private $eshopId;
    private $secretKey;
    private $expireDate;
    private $preference;
    private $successUrl;
    private $backUrl;
    private $resultUrl;
    private $userFields;
    //Значения для юр. лица
    private $testMode;
    private $holdMode;
    private $inn;
    private $holdTime;
    private $group;
    private $tax;
    private $deliveryTax;
    private $paymentSubjectType;
    private $paymentMethodType;
    //Значения для физ. лица
    private $accountId;
    private $formId;
    private $formType;
    //Статусы
    private $statusCreated;
    private $statusCancelled;
    private $statusPaid;
    private $statusHolded;
    private $statusPartiallyPaid;
    private $statusRefunded;
    private $isUseMerchantReceiptEntities;
    private $merchantReceiptType;
    private $merchantURL;
    private $defaultEshopIdForP2P;
    private $integrationMethod;

    public static function getInstance($params = array(), $isAllowChangeValues = true) {
        if (empty(self::$instance)) {
            self::$instance = new self($params, $isAllowChangeValues);
        }
        return self::$instance;
    }

    private function __construct($params = array(), $isAllowChangeValues = true) {
        $this->isAllowChangeValues = $isAllowChangeValues;
        if (!empty($params)) {
            $this->setParams($params);
        }
        if (empty($this->merchantURL)) {
            $this->setMerchantUrl();
        }
        if (empty($this->merchantReceiptType)) {
            $this->setMerchantReceiptType();
        }
        if (empty($this->defaultEshopIdForP2P)) {
            $this->setDefaultEshopIdForP2P();
        }
        if (empty($this->isUseMerchantReceiptEntities)) {
            $this->setIsUseMerchantReceiptEntities();
        }
    }

    public function getParams() {
        $allSettings = array();
        foreach (get_class_vars(__CLASS__) as $name => $value) {
            if (!in_array($name, array('instance', 'isAllowChangeValues')) && isset($name)) {
                $allSettings[$name] = $this->$name;
            }
        }
        return $allSettings;
    }

    public function setParams($params, &$errors = array()) {
        $err = array();
        foreach ($params as $name => $value) {
            $functionName = "set" . ucfirst($name);
            if (!$this->$functionName($value)) {
                $err[$name] = $value;
            }
        }
        $errors = array_merge($err, $errors);
        return empty($err) ? true : false;
    }

    private function validateAndSetValue($name, $value) {
        if (Validator::validate($name, $value, $this->isAllowChangeValues)) {
            $this->$name = $value;
            return true;
        }
        return false;
    }

    public function setIsAllowChangeValues($allow = false) {
        if (is_bool($allow)) {
            $this->isAllowChangeValues = $allow;
            return true;
        } else {
            return false;
        }
    }

    public function getIsAllowChangeValues() {
        return $this->isAllowChangeValues;
    }

    public function getConvertedExpireDate() {
        if (empty($this->expireDate)) {
            Validator::validate('expireDate', $this->expireDate);
        }
        return date('Y-m-d H:i:s', strtotime('+' . $this->expireDate . ' hour'));
    }

    public function resetParams() {
        foreach ($this as $name => $value) {
            if (!in_array($name, array('instance', 'isAvailableChangeValues'))) {
                $this->$name = NULL;
            }
        }
    }

    public function getEshopId() {
        return $this->eshopId;
    }

    public function getSecretKey() {
        return $this->secretKey;
    }

    public function getExpireDate() {
        return $this->expireDate;
    }

    public function getPreference() {
        return $this->preference;
    }

    public function getSuccessUrl() {
        return $this->successUrl;
    }

    public function getBackUrl() {
        return $this->backUrl;
    }

    public function getResultUrl() {
        return $this->resultUrl;
    }

    public function getTestMode() {
        return $this->isCheckboxTrue($this->testMode);
    }

    private function isCheckboxTrue($value) {
        return $value == "1" || $value === "true" || $value === "on";
    }

    public function getHoldMode() {
        return $this->isCheckboxTrue($this->holdMode);
    }

    public function getInn() {
        return $this->inn;
    }

    public function getHoldTime() {
        return $this->holdTime;
    }

    public function getGroup() {
        return $this->group;
    }

    public function getTax() {
        return $this->tax;
    }

    public function getDeliveryTax() {
        return $this->deliveryTax;
    }
    
    public function getPaymentSubjectType(){
        return $this->paymentSubjectType;
    }
    
    public function getPaymentMethodType(){
        return $this->paymentMethodType;
    }

    public function getAccountId() {
        return $this->accountId;
    }

    public function getFormId() {
        return $this->formId;
    }

    public function getFormType() {
        return $this->formType;
    }

    public function getStatusCreated() {
        return $this->statusCreated;
    }

    public function getStatusCancelled() {
        return $this->statusCancelled;
    }

    public function getStatusPaid() {
        return $this->statusPaid;
    }

    public function getStatusHolded() {
        return $this->statusHolded;
    }

    public function getStatusPartiallyPaid() {
        return $this->statusPartiallyPaid;
    }

    public function getStatusRefunded() {
        return $this->statusRefunded;
    }

    public function getIsUseMerchantReceiptEntities() {
        return $this->isUseMerchantReceiptEntities;
    }

    public function getMerchantReceiptType() {
        return $this->merchantReceiptType;
    }

    public function getMerchantUrl() {
        return $this->merchantURL;
    }

    public function getDefaultEshopIdForP2P() {
        return $this->defaultEshopIdForP2P;
    }

    public function getUserFields() {
        return empty($this->userFields) ? array() : $this->userFields;
    }

    public function getIntegrationMethod() {
        return $this->integrationMethod;
    }

    public function setEshopId($value) {
        return $this->validateAndSetValue("eshopId", $value);
    }

    public function setSecretKey($value) {
        return $this->validateAndSetValue("secretKey", $value);
    }

    public function setExpireDate($value) {
        return $this->validateAndSetValue("expireDate", $value);
    }

    public function setPreference($value) {
        return $this->validateAndSetValue("preference", $value);
    }

    public function setSuccessUrl($value) {
        return $this->validateAndSetValue("successUrl", $value);
    }

    public function setBackUrl($value) {
        return $this->validateAndSetValue("backUrl", $value);
    }

    public function setResultUrl($value) {
        return $this->validateAndSetValue("resultUrl", $value);
    }

    public function setTestMode($value) {
        return $this->validateAndSetValue("testMode", $value);
    }

    public function setHoldMode($value) {
        return $this->validateAndSetValue("holdMode", $value);
    }

    public function setInn($value) {
        return $this->validateAndSetValue("inn", $value);
    }

    public function setHoldTime($value) {
        return $this->validateAndSetValue("holdTime", $value);
    }

    public function setGroup($value) {
        return $this->validateAndSetValue("group", $value);
    }

    public function setTax($value) {
        return $this->validateAndSetValue("tax", $value);
    }

    public function setDeliveryTax($value) {
        return $this->validateAndSetValue("deliveryTax", $value);
    }

    public function setPaymentSubjectType($value){
        return $this->validateAndSetValue("paymentSubjectType", $value);
    }

    public function setPaymentMethodType($value){
        return $this->validateAndSetValue("paymentMethodType", $value);
    }
    
    public function setAccountId($value) {
        return $this->validateAndSetValue("accountId", $value);
    }

    public function setFormId($value) {
        return $this->validateAndSetValue("formId", $value);
    }

    public function setFormType($value) {
        $result = $this->validateAndSetValue("formType", $value);
        if ($result && $value == "PeerToPeer") {
            $this->setPreference('bankcard');
        }
        return $result;
    }

    public function setStatusCreated($value) {
        return $this->validateAndSetValue("statusCreated", $value);
    }

    public function setStatusCancelled($value) {
        return $this->validateAndSetValue("statusCancelled", $value);
    }

    public function setStatusPaid($value) {
        return $this->validateAndSetValue("statusPaid", $value);
    }

    public function setStatusHolded($value) {
        return $this->validateAndSetValue("statusHolded", $value);
    }

    public function setStatusPartiallyPaid($value) {
        return $this->validateAndSetValue("statusPartiallyPaid", $value);
    }

    public function setStatusRefunded($value) {
        return $this->validateAndSetValue("statusRefunded", $value);
    }

    public function setIsUseMerchantReceiptEntities($value = false) {
        return $this->validateAndSetValue("isUseMerchantReceiptEntities", $value);
    }

    public function setMerchantReceiptType($value = 1) {
        return $this->validateAndSetValue("merchantReceiptType", $value);
    }

    public function setMerchantUrl($newURL = 'https://merchant.intellectmoney.ru') {
        return $this->validateAndSetValue('merchantURL', $newURL);
    }

    public function setDefaultEshopIdForP2P() {
        $this->defaultEshopIdForP2P = UserSettings::DefaultEshopIdForP2P;
        return true;
    }

    public function setDefaultTestEshopIdForP2P() {
        $this->defaultEshopIdForP2P = UserSettings::DefaultTestEshopIdForP2P;
        return true;
    }

    public function setUserFields($value) {
        return $this->validateAndSetValue("userFields", $value);
    }

    public function setIntegrationMethod($value) {
        if (in_array($value, IntegrationMethod::values()))
        {
            if ($value === IntegrationMethod::P2P)
            {
                $this->setEshopId(UserSettings::DefaultEshopIdForP2P);
            }
            $this->integrationMethod = $value;
            return true;
        }
            
        $this->integrationMethod = 'Default';
        return true;
    }

    public function getIntegrationMethodsValues() {
        return IntegrationMethod::values();
    }

    public function getNamesForAllIntegrationMethodsToSave() {
        return array_unique(array_merge($this->getNamesOfP2PParamsToSave(), $this->getNamesOfOrganizationParamsToSave()));
    }

    public function getNamesForCurrentIntegrationMethodToSave() {
        if($this->integrationMethod == IntegrationMethod::P2P)
            return $this->getNamesOfP2PParamsToSave();
        return $this->getNamesOfOrganizationParamsToSave();
    }

    public function getNamesOfOrganizationParamsToSave() {
        return array(
            "eshopId",
            "secretKey",
            "testMode",
            "holdMode",
            "holdTime",
            "expireDate",
            "successUrl",
            "preference",
            "inn",
            "resultUrl",
            "tax",
            "deliveryTax",
            "paymentSubjectType",
            "paymentMethodType",
            "group",
            "statusCreated",
            "statusPaid",
            "statusHolded",
            "statusCancelled",
            "statusPartiallyPaid",
            "statusRefunded", 
            "integrationMethod",
        );
    }

    public function getNamesOfP2PParamsToSave() {
        return array(
            "secretKey",
            "expireDate",
            "preference",
            "successUrl",
            "backUrl",
            "resultUrl",
            "accountId",
            "formId",
            "formType",
            "statusCreated",
            "statusCancelled",
            "statusPaid",
            "statusHolded",
            "statusPartiallyPaid",
            "statusRefunded", 
            "integrationMethod"
        );
    }
    
    public function getInputTypeForOrganizationParamsToSave() {
        return array(
            "text" => array(
                "eshopId",
                "secretKey",
                "expireDate",
                "holdTime",
                "successUrl",
                "preference",
                "group",
            ),
            "checkbox" => array(
                "testMode",
                "holdMode",
            ),
            "select" => array(
                "tax",
                "deliveryTax",
                "statusCreated",
                "statusCancelled",
                "statusPaid",
                "statusHolded",
                "statusPartiallyPaid",
                "statusRefunded"
            )
        );
    }

}

?>