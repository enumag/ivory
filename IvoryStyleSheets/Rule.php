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
	public $selectors;

	/**
	 * @param array
	 * @return void
	 */
	public function __construct(array $selectors) {
		parent::__construct();
		$this->selectors = $selectors;
	}

}