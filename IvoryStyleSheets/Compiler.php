<?php

/**
 * @package Ivory
 * @license MIT, LGPL, BSD
 * @copyright (c) 2011 Jáchym Toušek
 */

namespace Ivory\StyleSheets;

/**
 * Kompilátor IvoryStyleSheets
 *
 * @author Jáchym Toušek
 */
class Compiler extends Object {

	// nová řádka
	const NL = "\n";

	// brání přidání mezery mezi selektory
	const SELF_SELECTOR = '&';

	/**
	 * Binární operátory a jejich priority
	 *
	 * @var array
	 */
	static public $binaryOperators = array(
			// aritmetické
			'*' => 1000,
			'/' => 1000,
			'%' => 1000,
			'+' => 800,
			'-' => 800,
			// řetězcové
			'.' => 800,
			// porovnávací
			'=' => 600,
			'>' => 600,
			'<' => 600,
			'>=' => 600,
			'<=' => 600,
			'<>' => 600,
			'!=' => 600,
			// logické
			'&&' => 400,
			'||' => 300,
			'^^' => 200,
	);

	/**
	 * Unární operátory a vynucení mezery před alafabetickými znaky
	 * (kvůli css -moz-calc apod.)
	 *
	 * @var array
	 */
	static public $unaryOperators = array(
			// aritmetické
			'+' => FALSE,
			'-' => TRUE,
			// logické
			'!' => FALSE,
	);

	/**
	 * Jednotky
	 *
	 * @link http://www.w3.org/TR/css3-values/
	 * @var array
	 */
	static public $units = array(
			'em', 'ex', 'px', 'gd', 'rem', 'vw', 'vh', 'vm', 'ch', // relativní jednotky délky
			'in', 'cm', 'mm', 'pt', 'pc', // absolutní jednotky délky
			'%', // procenta
			'deg', 'grad', 'rad', 'turn', // úhel
			'ms', 's', // čas
			'Hz', 'kHz', //frekvence
			'#', // číslo
			'', // výchozí jednotka
	);

	/**
	 * Povolené prefixy
	 *
	 * @var array
	 */
	static public $prefixes = array(
			'special' => 'A', //interní použití pro pravidla s @
			'important' => '!', //důležitá vlastnost
			'variable' => '$', //přiřazení do proměnné
			'mixin' => '@', //volání mixinu
			'raw' => '%', //odstranění uvozovek
			//+
			'none' => '', //žádný prefix
	);

	/**
	 * Složka pro výstup
	 *
	 * @var string
	 */
	public $outputDirectory;

	/**
	 * Soubor pro uložení cache
	 *
	 * @var string
	 */
	//public $cacheFile;

	/**
	 * Výchozí jednotka
	 *
	 * @var string
	 */
	protected $defaultUnit;

	/**
	 * Složky pro hledání souborů
	 *
	 * @var array
	 */
	protected $includePaths;

	/**
	 * Globální proměnné
	 *
	 * @var array
	 */
	protected $variables;

	/**
	 * Funkce
	 *
	 * @var ArrayObject
	 */
	protected $functions;

	/**
	 * Parser
	 *
	 * @var Ivory\StyleSheets\Parser
	 */
	protected $parser;

	/**
	 * Analyzér
	 *
	 * @var Ivory\StyleSheets\Analyzer
	 */
	protected $analyzer;

	/**
	 * Generátor
	 *
	 * @var Ivory\StyleSheets\Generator
	 */
	protected $generator;

	/**
	 * Konstruktor
	 *
	 * @return void
	 */
	public function __construct() {
		$this->defaultUnit = '';
		$this->includePaths = array();
		$this->variables = array();
		$this->functions = new \ArrayObject();
		$this->prepareFunctions();
		$this->parser = new Parser;
		$this->generator = new Generator;
		$this->analyzer = new Analyzer($this->parser, $this->generator, $this->functions);
	}

	/**
	 * Výchozí funkce
	 *
	 * @return void
	 */
	protected function prepareFunctions() {
		$this->addFunction('iergba', function (array $value) {
				if (isset($value[0]) && $value[0] == 'color') {
					if ($value[4] == 1) {
						return array('raw', sprintf('#%02x%02x%02x', $value[1], $value[2], $value[3]));
					}
					return array('raw', sprintf('#%02x%02x%02x%02x', $value[4] * 255, $value[1], $value[2], $value[3]));
				}
			});
		$this->addFunction('raw', function (array $value) {
				if (isset($value[0]) && $value[0] == 'string') {
					return array('raw', Compiler::stringDecode($value[1]));
				}
			});
	}

	/**
	 * Nastaví výchozí jednotku
	 *
	 * @param string
	 * @return void
	 */
	public function setDefaultUnit($unit) {
		if (!in_array($unit, self::$units) || $unit == '#') {
			throw new \Exception("Chybná výchozí jednotka '$unit'");
		}
		$this->defaultUnit = $unit;
	}

	/**
	 * Přidá cestu
	 *
	 * @param string
	 * @return void
	 */
	public function addIncludePath($path) {
		$path = realpath($path);
		if (!in_array($path, $this->includePaths)) {
			$this->includePaths[] = $path;
		}
	}

	/**
	 * Přidá proměnnou
	 *
	 * @param string
	 * @param int|float|bool|string|array
	 * @return void
	 */
	public function addVariable($name, $value) {
		if (!preg_match('/^-?[\w]++(?:[\w-]*[\w])?$/', $name)) {
			throw new \Exception("Chybný název proměnné");
		}
		$this->variables[$name] = $this->convertType($value);
	}

	/**
	 * Převede hodnotu na ISS typ
	 *
	 * @param int|float|bool|string|array
	 * @return array
	 */
	protected function convertType($value) {
		if (is_int($value) || is_float($value)) {
			return array('unit', $value, '');
		} elseif (is_bool($value)) {
			return array('bool', $value);
		} elseif (is_string($value)) {
			return array('string', self::stringEncode($value));
		} elseif (is_array($value)) {
			return array('map', array_map(array($this, 'convertType'), $value));
		} else {
			throw new \Exception("Hodnota musí být typu int, float, string, bool nebo array");
		}
	}

	/**
	 * Přidá funkci
	 *
	 * @param string
	 * @param callback
	 * @return void
	 */
	public function addFunction($name, $function) {
		if (!preg_match('/^-?[\w]++(?:[\w-]*[\w])?$/', $name)) {
			throw new \Exception("Chybný název funkce");
		}
		$this->functions[$name] = $function;
	}

	/**
	 * Kompilace souboru
	 *
	 * @param string
	 * @return string
	 */
	public function compileFile($file) {
		$pathinfo = pathinfo($file);
		$this->addIncludePath($pathinfo['dirname']);
		$file = self::stringEncode($pathinfo['basename']);
		$output = $this->compileString('@include ' . $file . ';');
		if ($this->outputDirectory) {
			$outputFile = $this->outputDirectory . DIRECTORY_SEPARATOR . $pathinfo['filename'] . '.css';
			if (@file_put_contents($outputFile, $output) === FALSE) {
				throw new \Exception("Nepodařilo se zapsat do souboru '$outputFile'");
			}
			//TODO: kešovat $this->allFiles, injectované proměnné
			//porovnat časy souborů z $allFiles s $outputFile (pokud existuje)
		}
		return $output;
	}

	/**
	 * Kompilace řetězce
	 *
	 * @param string
	 * @return string
	 */
	public function compileString($input) {
		return $this->compile($input);
	}

	/**
	 * Výsledný textový výstup
	 *
	 * @param string
	 * @return string
	 */
	protected function compile($input) {
		$locale = setlocale(LC_NUMERIC, 0);
		setlocale(LC_NUMERIC, "C");

		$tree = $this->parser->parse($input);
		$reduced = $this->analyzer->analyze($tree, $this->includePaths, $this->variables);
		$output = $this->generator->generate($reduced, $this->defaultUnit);
		
		setlocale(LC_NUMERIC, $locale);
		return $output;
	}

	/**
	 * Zakóduje řetězec
	 *
	 * @param string
	 * @return string
	 */
	public static function stringEncode($string) {
		return '\'' . strtr($string, array('\'' => '\\\'', '\\' => '\\\\')) . '\'';
	}

	/**
	 * Dekóduje řetězec
	 *
	 * @param string
	 * @return string
	 */
	public static function stringDecode($string) {
		return strtr(substr($string, 1, -1), array('\\\\' => '\\', '\\\'' => '\''));
	}

}