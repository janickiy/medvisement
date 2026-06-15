<?php

namespace PaySystem;

class IntellectMoneyBase {

    protected function convertToPriceFormat($amount) {
        $amount = floatval($amount);
        return number_format($amount, 2, '.', '');
    }

}
