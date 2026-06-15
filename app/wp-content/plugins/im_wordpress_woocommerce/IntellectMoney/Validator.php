<?php

namespace PaySystem;

class Validator {

    protected static $validatedValues = array(
        "eshopId" => array(
            "regExp" => "/^[4]{1}[\d]{5}$/",
        ),
        "secretKey" => array(
            "regExp" => "/^.{1,}$/",
        ),
        "expireDate" => array(//в часах, допустимо < 4320 часов
            "regExp" => "/^((43[0-1]{1}[\d]{1})|(4[0-2]{1}[\d]{2})|([0-3]{1}[\d]{3})|([\d]{1,3}))$/",
            "defaultValue" => "4319"
        ),
        "preference" => array(
            "regExp" => "/(^(((\w+){1})\s*,{1}\s*)*(((\w+){1})\s*,?\s*){1}+$)|(^$)/",
        ),
        "successUrl" => array(
            "regExp" => "/(^(http|https|mailto):[\/]{2,}[-\w0-9+&@#\/%?=~_|!:,.;]*[-\w0-9+&@#\/%=~_|]$)|(^$)/",
        ),
        "backUrl" => array(
            "regExp" => "/(^(http|https|mailto):[\/]{2,}[-\w0-9+&@#\/%?=~_|!:,.;]*[-\w0-9+&@#\/%=~_|]$)|(^$)/",
        ),
        "resultUrl" => array(
            "regExp" => "/(^(http|https|mailto):[\/]{2,}[-\w0-9+&@#\/%?=~_|!:,.;]*[-\w0-9+&@#\/%=~_|]$)|(^$)/",
        ),
        "testMode" => array(
            "regExp" => "/^(([0-1]{1})|true|false|on|off)$|(^$)/",
            "defaultValue" => 0 //Преобразование false в string дает пустую строку
        ),
        "holdMode" => array(
            "regExp" => "/^(([0-1]{1})|true|false|on|off)$|(^$)/",
            "defaultValue" => 0
        ),
        "inn" => array(
            "regExp" => "/(^[\d]{10,12}$)|(^$)/",
        ),
        "holdTime" => array(//в часах, допустимо < 120 часов
            "regExp" => "/^((1[0-1]{1}[\d]{1})|([\d]{1,2}))$/",
            "defaultValue" => "119"
        ),
        "group" => array(
            "regExp" => "/(^.{0,}$)/",
        ),
        "tax" => array(
            "regExp" => "/^[1-6]{1}$/",
        ),
        "deliveryTax" => array(
            "regExp" => "/^[1-6]{1}$/",
        ),
        "paymentSubjectType" => array(
            "regExp" => "/^[1-9]|1[0-3]$|(^$)/",
        ),
        "paymentMethodType" => array(
            "regExp" => "/^[1-7]{1}$|(^$)/",
        ),
        "accountId" => array(
            "regExp" => "/^[\d]{10}$/",
        ),
        "formId" => array(
            "regExp" => "/^[\d]{1,14}$/",
        ),
        "formType" => array(
            "regExp" => "/^IMAccount|PeerToPeer|Dynamic$/",
        ),
        "statusCreated" => array(
            "regExp" => "/^[\w\s*-]{1,}$/",
        ),
        "statusCancelled" => array(
            "regExp" => "/^[\w\s*-]{1,}$/",
        ),
        "statusPaid" => array(
            "regExp" => "/^[\w\s*-]{1,}$/",
        ),
        "statusHolded" => array(
            "regExp" => "/^[\w\s*-]{1,}$/",
        ),
        "statusPartiallyPaid" => array(
            "regExp" => "/^[\w\s*-]{1,}$/",
        ),
        "statusRefunded" => array(
            "regExp" => "/^[\w\s*-]{1,}$/",
        ),
        "isUseMerchantReceiptEntities" => array(
            "regExp" => "/(^(([0-1]{1})|true|false)$)|(^$)/",
            "defaultValue" => 0
        ),
        "merchantReceiptType" => array(
            "regExp" => "/^[1-4]{1}$/",
            "defaultValue" => 1
        ),
        "merchantURL" => array(
            "regExp" => "/^https:[\/]{2}((merchant-test.\w*.dev.intellectmoney.ru)|(merchant.intellectmoney.ru))$/",
            "defaultValue" => "https://merchant.intellectmoney.ru"
        ),
        "userFields" => array(
            "regExpName" => "/^UserField(Name)?_\d{1}$/"
        )
    );

    public static function validateArray(&$params, &$invalidParams = array(), $isAllowChangeValue = true) {
        $invalid = array();
        if (!empty($params)) {
            foreach ($params as $name => $value) {
                if (!self::validate($name, $value, $isAllowChangeValue)) {
                    $invalid[] = $name;
                }
            }
        }
        $invalidParams = array_merge($invalidParams, $invalid);
        return empty($invalid) ? true : false;
    }

    public static function validate($name, &$value, $isAllowChangeValue = true) {
        if (!in_array($name, array_keys(self::$validatedValues))) {
            return false;
        }

        return is_array($value) ? self::validateArrayValue($name, $value) : self::validateStringValue($name, $value, $isAllowChangeValue);
    }

    private static function validateStringValue($name, &$value, $isAllowChangeValue = true) {
        $isSuccess = preg_match(self::$validatedValues[$name]["regExp"], (string) $value);

        if (!$isSuccess && isset(self::$validatedValues[$name]["defaultValue"]) && $isAllowChangeValue) {
            $value = self::$validatedValues[$name]["defaultValue"];
            $isSuccess = true;
        }

        return $isSuccess;
    }

    private static function validateArrayValue($name, &$value) {
        foreach (array_keys($value) as $key) {
            if (!preg_match(self::$validatedValues[$name]["regExpName"], (string) $key)) {
                return false;
            }
        }
        return true;
    }

}

?>