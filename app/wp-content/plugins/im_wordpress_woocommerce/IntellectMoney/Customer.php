<?php

namespace PaySystem;

class Customer {

    private static $instance;
    private $email;
    private $name;
    private $phone;

    public static function getInstance($email = NULL, $name = NULL, $phone = NULL) {
        if (empty(self::$instance)) {
            self::$instance = new self($email, $name, $phone);
        }
        return self::$instance;
    }

    private function __construct($email, $name, $phone) {
        $this->setEmail($email);
        $this->setName($name);
        $this->setPhone($phone);
    }

    public function getContact() {
        return !empty($this->getEmail()) ? $this->getEmail() : $this->getPhone();
    }

    public function getEmail() {
        return $this->email;
    }

    public function getName() {
        return $this->name;
    }

    public function getPhone() {
        return $this->phone;
    }

    public function setEmail($value) {
        $this->email = $value;
        return true;
    }

    public function setName($value) {
        $this->name = $value;
        return true;
    }

    public function setPhone($value) {
        $this->phone = $value;
        return true;
    }

    public function resetParams() {
        $this->__construct(NULL, NULL, NULL);
    }

}

?>