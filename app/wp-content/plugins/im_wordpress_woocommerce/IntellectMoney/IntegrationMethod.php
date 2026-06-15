<?php 

namespace PaySystem;

/**
 * Способ интеграции с системой IntellectMoney.
 */
class IntegrationMethod {
    /**
     * Метод по умолчанию.
     */
    const P2P = 'P2P';

    /**
     * Метод вызова по умолчанию.
     */
    const DefaultMethod = 'Default'; 

    /**
     * Возвращает все возможные значения в виде массива.
     * @return array
     * Массив всех возможных заччений.
     */
    public static function values()
    {
        return [
            IntegrationMethod::DefaultMethod, 
            IntegrationMethod::P2P,
        ];
    }
}