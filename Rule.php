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
class Rule extends Block {

    /**
     * @var array
     */
    public $prefixes;

    /**
     * @var array
     */
    public $selectors;

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
        parent::__construct();
        $this->prefixes = $prefixes;
        $this->selectors = $selectors;
        $this->statement = $statement;
    }

}