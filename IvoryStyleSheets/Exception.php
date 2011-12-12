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
     * Nastaví řádek
     *
     * @param int
     * @return Ivory\StyleSheets\Exception
     */
    public function setLine($line) {
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