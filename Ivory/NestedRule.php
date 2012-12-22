<?php

/**
 * @package Ivory
 * @license MIT, LGPL, BSD
 * @copyright (c) 2011 Jáchym Toušek
 */

namespace Ivory;

/**
 * @author Jáchym Toušek
 */
class NestedRule extends Rule {

	/**
	 * @var array
	 */
	public $statement;

	/**
	 * @param array
	 * @param array
	 * @param array
	 * @return void
	 */
	public function __construct(array $selectors = array(), array $statement = NULL) {
		parent::__construct($selectors);
		$this->statement = $statement;
	}

}