<?php

namespace PaySystem;

require_once("LanguageHelper.php");

class VATs {

    const vatRateOf20 = array(
        "id" => 1,
        "textVar" => "vatRateOf",
        "ending" => " 20%",
    );
    const vatRateOf10 = array(
        "id" => 2,
        "textVar" => "vatRateOf",
        "ending" => " 10%",
    );
    const vatRateCalculated20_120 = array(
        "id" => 3,
        "textVar" => "vatRateCalculated",
        "ending" => " 20/120",
    );
    const vatRateCalculated10_110 = array(
        "id" => 4,
        "textVar" => "vatRateCalculated",
        "ending" => " 10/110",
    );
    const vatRateOf0 = array(
        "id" => 5,
        "textVar" => "vatRateOf",
        "ending" => " 0%",
    );
    const vatIsNotAppearing = array(
        "id" => 6,
        "textVar" => "vatIsNotAppearing",
        "ending" => "",
    );
    const vatRateOf5 = array(
        "id" => 7,
        "textVar" => "vatRateOf",
        "ending" => " 5%",
    );
    const vatRateOf7 = array(
        "id" => 8,
        "textVar" => "vatRateOf",
        "ending" => " 7%",
    );
    const vatRateCalculated5_105 = array(
        "id" => 9,
        "textVar" => "vatRateCalculated",
        "ending" => " 5/105",
    );
    const vatRateCalculated7_107 = array(
        "id" => 10,
        "textVar" => "vatRateCalculated",
        "ending" => " 7/107",
    );
    const vatRateOf22 = array(
        "id" => 11,
        "textVar" => "vatRateOf",
        "ending" => " 22%",
    );
    const vatRateCalculated22_122 = array(
        "id" => 12,
        "textVar" => "vatRateCalculated",
        "ending" => " 22/122",
    );

    private static function getText($textVar) {
        return LanguageHelper::getInstance()->validateAndGetTextValue($textVar, "vat");
    }

    public static function getList() {
        $result = array();
        $reflectionClass = new \ReflectionClass(__CLASS__);
        foreach ($reflectionClass->getConstants() as $vat) {
            $result[] = array(
                "id" => $vat['id'],
                "name" => self::getText($vat['textVar']) . $vat['ending'],
            );
        }
        return $result;
    }

}

?>
