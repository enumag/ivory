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
	 * @var string
	 */
	public $file;

	/**
	 * @var int
	 */
	public $line;

	/**
	 * @param string
	 * @param array
	 * @param int
	 * @return void
	 */
	public function __construct($name, array $args, $line) {
		parent::__construct();
		$this->name = $name;
		$this->args = $args;
		$this->line = $line;
	}

}