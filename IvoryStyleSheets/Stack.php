<?php

/**
 * @package Ivory
 * @license MIT, LGPL, BSD
 * @copyright (c) 2011 Jáchym Toušek
 */

namespace Ivory\StyleSheets;

/**
 * Zásobník
 *
 * @author Jáchym Toušek
 */
class Stack implements \Countable {

	/**
	 * @var array
	 */
	protected $elements;

	public function __construct() {
		$this->clear();
	}

	public function push($element) {
		$this->elements[] = $element;
	}

	public function pop() {
		return array_pop($this->elements);
	}

	public function top() {
		return end($this->elements);
	}

	public function count() {
		return count($this->elements);
	}

	public function isEmpty() {
		return empty($this->elements);
	}

	public function contains($element) {
		return in_array($element, $this->elements);
	}

	public function clear() {
		$this->elements = array();
	}

}