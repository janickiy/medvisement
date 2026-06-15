<?php

namespace PaySystem;

require_once("Status.php");

class StatusWeight {

    const preCreated = 0;
    const created = 1;
    const holded = 2;
    const partiallyPaid = 3;
    const paid = 4;
    const refunded = 5;
    const cancelled = 6;
    const undefined = 7;

    public static function getWeightArray() {
        return array(
            Status::preCreated => self::preCreated,
            Status::created => self::created,
            Status::holded => self::holded,
            Status::partiallyPaid => self::partiallyPaid,
            Status::paid => self::paid,
            Status::refunded => self::refunded,
            Status::cancelled => self::cancelled,
            Status::undefined => self::undefined,
        );
    }

}
