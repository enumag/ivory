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
	 * @var array
	 */
	public $media;

	/**
	 * @var int
	 */
	public $line;

	/**
	 * @param array
	 * @param int
	 * @return void
	 */
	public function __construct(array $media = NULL, $line) {
		parent::__construct();
		$this->media = $media;
		$this->line = $line;
	}

}