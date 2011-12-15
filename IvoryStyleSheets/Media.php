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
class Media extends AtRule {

    /**
     * @var string
     */
    public $media;

    /**
     * @param string
     * @return void
     */
    public function __construct($media) {
        parent::__construct();
        $this->media = $media;
    }

}