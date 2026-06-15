<?php

namespace PaySystem;

require_once("Exceptions/LanguageHelperException.php");

use PaySystem\Exceptions as Exceptions;

class LanguageHelper {

    private static $instance;
    private $currentLang;

    /**
     * Массив заголовков.
     * @var array
     */
    private $title = array();

    /**
     * Массив описаний.
     * @var array
     */
    private $description = array();
    private $error = array();
    private $vat = array();

    public static function getInstance($lang = 'ru', $isCp1251 = NULL) {
        if (empty(self::$instance)) {
            try {
                if (empty(self::$instance)) {
                    self::$instance = new self($lang, $isCp1251);
                }
            } catch (Exceptions\LanguageHelperException $e) {
                throw new Exceptions\LanguageHelperException($e->getMessage());
            }
        }
        return self::$instance;
    }

    private function __construct($lang, $isCp1251) {
        $this->setLanguage($lang, $isCp1251);
    }

    /**
     * Получить строку из списка `title`.
     * 
     * @param string $name Ключ искомой строки.
     * 
     * @return string
     * Возвращает перевод строки в текущем языке.
     */
    public function getTitle($name) {
        return $this->validateAndGetTextValue($name, 'title');
    }

    /**
     * Получить все элементы из списка `title`.
     * 
     * @return array
     * Возвращает ассоциативный массив ключ/значение.
     */
    public function getTitles() {
        return $this->title;
    }

    public function validateAndGetTextValue($name, $arrayName) {
        $array = $this->$arrayName;
        if (!in_array($name, array_keys($array[$this->currentLang]))) {
            throw new Exceptions\LanguageHelperException("LanguageHelper exception: there is no " . $arrayName . " for `" . $name . "`!");
        }
        return $array[$this->currentLang][$name];
    }

    /**
     * Получить строку из списка `description`.
     * 
     * @param string $name Ключ искомой строки.
     * 
     * @return string
     * Возвращает перевод строки в текущем языке.
     */
    public function getDesc($name) {
        return $this->validateAndGetTextValue($name, 'description');
    }

    /**
     * Получить все элементы из списка `description`.
     * 
     * @return array
     * Возвращает ассоциативный массив ключ/значение.
     */
    public function getDescs() {
        return $this->description;
    }

    /**
     * Получить строку из списка `error`.
     * 
     * @param string $name Ключ искомой строки.
     * 
     * @return string
     * Возвращает перевод строки в текущем языке.
     */
    public function getError($name) {
        return $this->validateAndGetTextValue($name, 'error');
    }

    /**
     * Получить все элементы из списка `error`.
     * 
     * @return array
     * Возвращает ассоциативный массив ключ/значение.
     */
    public function getErrors() {
        return $this->error;
    }

    public function setLanguage($lang, $isCp1251 = null) {
        $lang = strtolower($lang);

        if ($lang == $this->currentLang && $lang != 'ru') {
            return true;
        }

        if (in_array($lang, $this->existsLanguageFiles())) {
            if (!in_array($lang, array_keys($this->title))) {
                $this->loadLang($lang);
            }
            if (!is_null($isCp1251) && $lang == 'ru') {
                foreach (get_class_vars(__CLASS__) as $key => $value) {
                    if (!in_array($key, array('instance', 'currentLang'))) {
                        $textArray = $this->$key;
                        $this->convertArrayEncoding($textArray['ru'], $isCp1251);
                        $this->$key = $textArray;
                    }
                }
            }
            $this->currentLang = $lang;
            return true;
        } else {
            throw new Exceptions\LanguageHelperException("LanguageHelper exception: language `" . $lang . "` does not exist!");
        }
    }

    private function convertArrayEncoding(&$array, $isCp1251) {
        foreach ($array as $key => $str) {
            $array[$key] = $isCp1251 ? iconv("UTF-8", "cp1251//IGNORE", $str) : iconv("cp1251", "UTF-8//IGNORE", $str);
        }
    }

    private function existsLanguageFiles() {
        $explodedCurrentPath = array_filter(explode(DIRECTORY_SEPARATOR, __DIR__));
        $currentDirectory = end($explodedCurrentPath);
        $languagesPath = $currentDirectory == "Tests" ? "../IntellectMoneyCommon/Languages" : __DIR__ . "/Languages";
        if (!is_dir($languagesPath)) {
            throw new Exceptions\LanguageHelperException("Directory not found!");
        }
        $filesInLangsDir = scandir($languagesPath);
        array_walk($filesInLangsDir, array($this, "getLangName"));
        $issetLangs = array();
        foreach ($filesInLangsDir as $value) {
            if (!empty($value)) {
                $issetLangs[] = $value;
            }
        }
        return $issetLangs;
    }

    public function getLanguage() {
        return $this->currentLang;
    }

    private function loadLang($lang) {
        require_once("Languages/" . $lang . ".php");
        $this->title = array_merge($this->title, $title);
        $this->description = array_merge($this->description, $description);
        $this->error = array_merge($this->error, $error);
        $this->vat = array_merge($this->vat, $vat);
    }

    private function getLangName(&$value, $key) { //Вызывается в existsLanguageFiles()
        preg_match('/^[a-z]{2,3}/', $value, $res);
        if (isset($res[0])) {
            $value = $res[0];
        }
    }

}

?>