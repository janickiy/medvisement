<?php

namespace PaySystem;

require_once("Item.php");
require_once("Status.php");
require_once("StatusWeight.php");
require_once("Currency.php");

class Order extends IntellectMoneyBase {

    private static $instance;
    private $items;
    private $invoiceId;
    private $orderId;
    private $originalAmount;
    private $recipientAmount;
    private $paidAmount;
    private $deliveryAmount;
    private $recipientCurrency;
    private $discount;
    private $status;
    private $userFields;

    public static function getInstance($invoiceId = NULL, $orderId = NULL, $originalAmount = NULL, $recipientAmount = NULL, $paidAmount = NULL, $deliveryAmount = NULL, $recipientCurrency = NULL, $discount = NULL, $status = NULL) {
        if (empty(self::$instance)) {
            self::$instance = new self($invoiceId, $orderId, $originalAmount, $recipientAmount, $paidAmount, $deliveryAmount, $recipientCurrency, $discount, $status);
        }
        return self::$instance;
    }

    private function __construct($invoiceId, $orderId, $originalAmount, $recipientAmount, $paidAmount, $deliveryAmount, $recipientCurrency, $discount, $status) {
        $this->setParams($invoiceId, $orderId, $originalAmount, $recipientAmount, $paidAmount, $deliveryAmount, $recipientCurrency, $discount, $status);
        $this->userFields = [];
    }

    public function addItem($price, $quantity, $title, $tax = 0, $paymentSubjectType = null, $paymentMethodType = null) {
        $item = new Item($price, $quantity, $title, $tax, $paymentSubjectType, $paymentMethodType);
        $this->items[] = $item;
    }

    /**
     * Получить все элементы заказа. 
     * 
     * @return array
     * Возвращает массив типа `\PaySystem\Item`.
     */
    public function getItems() {
        return $this->items;
    }

    public function isAvailableChangeStatus($newStatus) {
        $weights = StatusWeight::getWeightArray();
        return empty($this->status) || $weights[$this->status] < $weights[$newStatus];
    }

    public function setParams($invoiceId = NULL, $orderId = NULL, $originalAmount = NULL, $recipientAmount = NULL, $paidAmount = NULL, $deliveryAmount = NULL, $recipientCurrency = NULL, $discount = NULL, $status = NULL) {
        $this->setInvoiceId($invoiceId);
        $this->setOrderId($orderId);
        $this->setOriginalAmount($originalAmount);
        $this->setRecipientAmount($recipientAmount);
        $this->setPaidAmount($paidAmount);
        $this->setDeliveryAmount($deliveryAmount);
        $this->setRecipientCurrency($recipientCurrency);
        $this->setDiscount($discount);
        $this->setStatus($status);

        return true;
    }

    public function getParams() {
        $allParams = array();
        foreach ($this as $name => $value) {
            if (!in_array($name, array('instance', 'items'))) {
                $allParams[$name] = $this->$name;
            }
        }
        return $allParams;
    }

    public function getInvoiceId() {
        return $this->invoiceId;
    }

    public function getOrderId() {
        return $this->orderId;
    }

    public function getOriginalAmount() {
        return $this->originalAmount;
    }

    public function getRecipientAmount() {
        return $this->recipientAmount;
    }

    public function getDeliveryAmount() {
        return $this->deliveryAmount;
    }

    public function getRecipientCurrency() {
        return $this->recipientCurrency;
    }

    public function getPaidAmount() {
        return $this->paidAmount;
    }

    public function getDiscount() {
        return $this->discount;
    }

    public function getStatus() {
        return $this->status;
    }

    public function getUserField($name) {
        if (array_key_exists($name, $this->userFields))
            return $this->userFields[$name];
        return null;
    }

    public function getUserFields($forP2p = false) {
        $result = [];
        $currentIndex = 0;
        foreach($this->userFields as $name => $value)
        {
            if (in_array($currentIndex, [0, 9]) && $forP2p)
                continue;

            $result['UserFieldName_' . $currentIndex] = $name;
            $result['UserField_' . $currentIndex] = $value;
            $currentIndex++;
        }
        return $result;
    }

    public function setInvoiceId($value) {
        $this->invoiceId = $value;
        return true;
    }

    public function setOrderId($value) {
        $this->orderId = $value;
        return true;
    }

    public function setOriginalAmount($value) {
        return $this->setToNumberFormatAndSave('originalAmount', $value);
    }

    public function setRecipientAmount($value) {
        return $this->setToNumberFormatAndSave('recipientAmount', $value);
    }

    public function setDeliveryAmount($value) {
        return $this->setToNumberFormatAndSave('deliveryAmount', $value);
    }

    public function setRecipientCurrency($value) {
        $currency = Currency::getCurrencyFromString($value);
        if ($currency) {
            $this->recipientCurrency = $currency;
            return true;
        }
        return false;
    }

    public function setPaidAmount($value = 0) {
        return $this->setToNumberFormatAndSave('paidAmount', $value);
    }

    public function setDiscount($value = 0) {
        return $this->setToNumberFormatAndSave('discount', $value);
    }

    public function setStatusCreated() {
        return $this->setStatus(Status::created);
    }

    public function setStatusCancelled() {
        return $this->setStatus(Status::cancelled);
    }

    public function setStatusPaid() {
        return $this->setStatus(Status::paid);
    }

    public function setStatusHolded() {
        return $this->setStatus(Status::holded);
    }

    public function setStatusPartiallyPaid() {
        return $this->setStatus(Status::partiallyPaid);
    }

    public function setStatusRefunded() {
        return $this->setStatus(Status::refunded);
    }

    public function setStatus($newStatus) {
        if ((is_null($newStatus) and $newStatus = Status::preCreated) || $this->isAvailableChangeStatus($newStatus)) {
            $this->status = $newStatus;
            return true;
        }
        return false;
    }

    public function setUserField($name, $value)
    {
        $this->userFields[$name] = $value;
        return true;
    }

    public function resetParams() {
        $this->setParams();
    }

    private function setToNumberFormatAndSave($name, $value) {
        if (empty($value)) {
            $value = 0;
        }
        if (is_numeric($value)) {
            $this->$name = $this->convertToPriceFormat($value);
            return true;
        }
        return false;
    }

}

?>