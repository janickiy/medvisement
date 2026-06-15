<?php

namespace PaySystem;

require_once("Item.php");
require_once("Exceptions/MerchantReceiptHelperException.php");
require_once("IntellectMoneyBase.php");

class MerchantReceiptHelper extends IntellectMoneyBase {

    private $commonPrice = 0;
    public $items = array();
    private $recipientAmount;
    private $discount;
    private $group;
    private $inn;
    private $customerContact;
    private $type;

    public function __construct($recipientAmount, $inn, $customerContact, $group = null, $discountValue = 0, $orderPrice = 0, $type = 1) {
        $this->recipientAmount = $recipientAmount;
        $this->group = empty($group) ? null : $group;
        $this->inn = $inn;
        $this->customerContact = $customerContact;
        if (!is_null($discountValue) || !empty($orderPrice)) {
            $this->setDiscount($discountValue, $orderPrice);
        }
        $this->type = $type;
    }

    public function addItem($basePrice, $quantity, $title, $tax, $paymentSubjectType = null, $paymentMethodType = null) {
        $item = new Item($basePrice, $quantity, $title, $tax, $paymentSubjectType, $paymentMethodType, $this->discount);
        $this->commonPrice += $item->price * $item->quantity;
        array_push($this->items, $item);
    }

    private function setDiscount($discountValue, $orderPrice) {
        $discount = $discountValue;
        if ($this->recipientAmount != $orderPrice) {
            $discount += $orderPrice - $this->recipientAmount;
        }

        if ($discount && $orderPrice > 0) {
            $discount = $discount / $orderPrice;
            $this->discount = $discount;
        }

        if (empty($discount)) {
            $this->discount = 0;
        }
    }

    public function generateMerchantReceipt($useEntitites = true) {
        $merchantReceipt = array(
            "inn" => $this->inn,
            "group" => $this->group,
            "content" => array(
                "type" => $this->type,
                "positions" => $this->getItems(),
                "customerContact" => $this->customerContact
            )
        );

        if ($useEntitites) {
            return htmlentities(json_encode($merchantReceipt), ENT_QUOTES);
        } else {
            return json_encode($merchantReceipt);
        }
    }

    private function calculateDiscount($sumDifference, $currentDiscount, $inLoop = false) {
        $loopDetection = true;
        $iterationDiscount = $sumDifference - $currentDiscount;
        foreach ($this->items as $item) {
            if ($item->price > 0 && $currentDiscount != $sumDifference) {
                if ($inLoop) {
                    if ($item->price - $iterationDiscount < 0) {
                        continue;
                    }
                    $itemDiscount = round($item->price - $iterationDiscount, 2);
                    if ($item->quantity == 1) {
                        $item->price = $itemDiscount;
                    } elseif ($item->quantity > 1) {
                        $item->quantity--;
                        $item = new Item($itemDiscount, 1, $item->text, $item->tax, $item->paymentSubjectType, $item->paymentMethodType);
                        array_push($this->items, $item);
                    }
                    $currentDiscount += $iterationDiscount;
                } else {
                    $discount = $this->calculateDiscountAmount($item->price, $iterationDiscount);
                    $itemDiscount = round($item->price - $discount, 2);
                    if ($itemDiscount < 0) {
                        continue;
                    }
                    if ($discount > 0) {
                        $loopDetection = false;
                    }
                    $item->price = $itemDiscount;
                    $currentDiscount += $discount * $item->quantity;
                }
            }
        }
        return [$loopDetection, $currentDiscount];
    }

    private function calculateDiscountAmount($price, $iterationDiscount) {
        return round($iterationDiscount * $price / $this->commonPrice, 2);
    }

    private function getItems() {
        $sumDifference = abs(round($this->recipientAmount - $this->commonPrice, 2));

        $currentDiscount = 0;
        while ($currentDiscount != $sumDifference) {
            list($loopDetection, $currentDiscount) = $this->calculateDiscount($sumDifference, $currentDiscount);
            if ($loopDetection) {
                list($loopDetection, $currentDiscount) = $this->calculateDiscount($sumDifference, $currentDiscount, true);
                break;
            }
        }
		
		foreach ($this->items as $item) {
			$item->price = floor($item->price * 100.0) / 100.0;
        }
        
        return $this->items;
    }

}