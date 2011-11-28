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
class Mixin extends Block {

    /**
     * @var string
     */
    public $name;

    /**
     * @var array
     */
    public $args;

    /**
     * @param Block
     * @param string
     * @param array
     */
    public function __construct($name, array $args) {
        parent::__construct();
        $this->name = $name;
        $this->args = $args;
    }

}