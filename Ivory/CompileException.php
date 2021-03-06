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
class CompileException extends \Exception {

	/**
	 * Konstruktor
	 *
	 * @return void
	 */
	public function __construct() {
		call_user_func_array('parent::__construct', func_get_args());
		$this->file = '';
		$this->line = 0;
	}

	/**
	 * Nastaví řádek
	 *
	 * @param int
	 * @return Ivory\CompileException
	 */
	public function setLine($line) {
		if (!ctype_digit((string) $line)) {
			var_export($line);
			throw new \Exception();
		}
		if ($this->line === 0) {
			$this->line = (int) $line;
		}
		return $this;
	}

	/**
	 * Nastaví název souboru
	 *
	 * @param string
	 * @return Ivory\CompileException
	 */
	public function setFile($file) {
		if ($this->file === '') {
			$this->file = (string) $file;
		}
		return $this;
	}

}