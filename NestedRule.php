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
class NestedRule extends Block {

    /**
     * @var array
     */
    public $prefixes;

    /**
     * @var array
     */
    public $statement;

    /**
     * @param array
     * @param array
     * @param array
     */
    public function __construct(array $selectors = array(''), array $prefixes = array(''), array $statement = NULL) {
        parent::__construct($selectors);
        $this->prefixes = $prefixes;
        $this->statement = $statement;
    }

}