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
abstract class Block extends Object {

	/**
	 * @var array
	 */
	public $properties;

	/**
	 * @return void
	 */
	public function __construct() {
		$this->properties = array();
	}

}