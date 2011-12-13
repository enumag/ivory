<?php

/**
 * @package Ivory
 * @license MIT, LGPL, BSD
 * @copyright (c) 2011 Jáchym Toušek
 */

namespace Ivory\StyleSheets;

/**
 * @author Jáchym Toušek
 */
class Exception extends \Exception {

    /**
     * Konstruktor
     *
     * @return void
     */
    public function __construct() {
        call_user_func_array('parent::__construct', func_get_args());
        $this->setFile('unknown');
        $this->line = 'unknown';
    }

    /**
     * Nastaví řádek
     *
     * @param int
     * @return Ivory\StyleSheets\Exception
     */
    public function setLine($line) {
        if (!ctype_digit((string) $line)) {
            var_export($line);
            throw new \Exception();
        }
        $this->line = $line;
        return $this;
    }

    /**
     * Nastaví název souboru
     *
     * @param string
     * @return Ivory\StyleSheets\Exception
     */
    public function setFile($file) {
        $this->file = $file;
        return $this;
    }

}