<?php

/**
 * @package Ivory
 * @license MIT, LGPL, BSD
 * @copyright (c) 2011 Jáchym Toušek
 */

namespace Ivory\StyleSheets;

/**
 * Objekt IvoryStyleSheets
 *
 * @author Jáchym Toušek
 */
abstract class Object {

	/**
	 * Nedefinovaná metoda
	 *
	 * @param string
	 * @param array
	 * @return void
	 */
	public function __call($name, $args) {
		throw new \Exception("Nedefinovaná metoda" . get_class($this) . "::$name()");
	}

	/**
	 * Nedefinovaná statická  metoda
	 * @param string
	 * @param array
	 * @return void
	 */
	public static function __callStatic($name, $args) {
		throw new \Exception("Nedefinovaná statická metoda " . get_called_class() . "::$name()");
	}

	/**
	 * Čtení nedefinované vlastnosti
	 *
	 * @param string
	 * @return void
	 */
	public function &__get($name)
	{
		throw new \Exception("Čtení nedefinované vlastnosti " . get_class($this) . "::\$$name.");
	}

	/**
	 * Zápis nedefinované vlastnosti
	 *
	 * @param string
	 * @param mixed
	 * @return void
	 */
	public function __set($name, $value)
	{
		throw new \Exception("Zápis nedefinované vlastnosti " . get_class($this) . "::\$$name.");
	}

}
