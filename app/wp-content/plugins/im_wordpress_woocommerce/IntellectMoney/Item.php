<?php

namespace PaySystem;

require_once("IntellectMoneyBase.php");

class Item extends IntellectMoneyBase {
    
    public $price;
    public $quantity;
    public $text;
    public $tax;
    public $paymentSubjectType;
    public $paymentMethodType;

    public function __construct($basePrice, $quantity, $text, $tax, $paymentSubjectType = null, $paymentMethodType = null, $discount = 0) {
        $basePrice = $this->convertToPriceFormat($basePrice);
        $this->price = round(empty($discount) ? $basePrice : $basePrice - round($basePrice * $discount, 2), 2);
        $this->quantity = $quantity;
        $this->text = $text;
        $this->tax = $tax;
        $this->paymentSubjectType = $paymentSubjectType;
        $this->paymentMethodType = $paymentMethodType;
    }

}

?>