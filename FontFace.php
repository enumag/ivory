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
class FontFace extends Block {

    /**
     * @var Block
     */
    public $parent;

    /**
     * @param array
     * @param Block
     */
    public function __construct(Block $parent) {
        parent::__construct();
        $this->parent = $parent;
    }

}