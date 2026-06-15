<?php

namespace PaySystem;

class ProcessingResponseResultStruct {

    public $changeStatusResult;
    public $statusCMS;

    public function __construct($changeStatusResult, $statusCMS) {
        $this->changeStatusResult = $changeStatusResult;
        $this->statusCMS = $statusCMS;
    }

}
?>