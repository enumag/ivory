<?php

/**
 * @package Ivory
 * @license MIT, LGPL, BSD
 * @copyright (c) 2011 Jáchym Toušek
 */

namespace Ivory\StyleSheets;

/**
 * Analyzér IvoryStyleSheets
 *
 * @author Jáchym Toušek
 */
class Analyzer extends Object {

	/**
	 * Zásobník souborů
	 *
	 * @var Stack
	 */
	protected $files;

	/**
	 * Všechny vložené soubory
	 *
	 * @var array
	 */
	protected $allFiles;

	/**
	 * Redukované bloky
	 *
	 * @var array
	 */
	protected $reduced;

	/**
	 * Proměnné
	 *
	 * @var array
	 */
	protected $variables;

	/**
	 * Mixiny
	 *
	 * @var array
	 */
	protected $mixins;

	/**
	 * Zpracování @media bloku
	 *
	 * @var bool
	 */
	protected $inMedia;

	/**
	 * Složky pro hledání souborů
	 *
	 * @var array
	 */
	protected $includePaths;

	/**
	 * Parser
	 *
	 * @var Ivory\StyleSheets\Parser
	 */
	protected $parser;

	/**
	 * Generátor
	 *
	 * @var Ivory\StyleSheets\Generator
	 */
	protected $generator;

	/**
	 * Funkce
	 *
	 * @var ArrayObject
	 */
	protected $functions;

	/**
	 * Konstruktor
	 *
	 * @param Ivory\StyleSheets\Parser
	 * @param Ivory\StyleSheets\Generator
	 * @param ArrayObject
	 * @return void
	 */
	public function __construct(Parser $parser, Generator $generator, \ArrayObject $functions) {
		$this->files = new Stack;
		$this->allFiles = array();
		$this->parser = $parser;
		$this->generator = $generator;
		$this->functions = $functions;
		
	}

	/**
	 * Zredukuje strom
	 *
	 * @param Main
	 * @return array
	 */
	public function &analyze($tree, array $includePaths, array $variables) {
		$this->includePaths = $includePaths;

		//prázdný zásobník
		$this->files->clear();
		$this->allFiles = array();

		//inicializace prázdných polí
		$this->mixins = array();
		$this->reduced = array();
		$this->variables = array();

		//Globální proměnné
		$this->variables[] = array();
		foreach ($variables as $name => $value) {
			$this->saveVariable($name, $value);
		}

		//globální blok
		$this->reduced[] = new Main;
		$this->inMedia = FALSE;

		$this->reduceBlock($tree);

		return $this->reduced;
	}

	/**
	 * Zjistí aktuální soubor
	 *
	 * @return string
	 */
	protected function getFile() {
		return /* TODO $this->files->isEmpty() ? 'unknown' : */$this->files->top();
	}

	/**
	 * Přidá soubor na zásobník
	 *
	 * @param string
	 * @return void
	 */
	protected function addFile($file) {
		$path = realpath($file);
		if ($this->files->contains($path)) {
			throw new Exception("Rekurzivní vkládání souboru '$file'");
		}
		$this->allFiles[] = $path;
		$this->files->push($path);
	}

	/**
	 * Odebere soubor ze zásobníku
	 *
	 * @return string
	 */
	protected function removeFile() {
		return $this->files->pop();
	}

	/**
	 * Nahradí proměnné v selektorech jejich hodnotou
	 *
	 * @param array
	 * @return array
	 */
	protected function replaceVariables(array $selectors) {
		foreach ($selectors as &$selector) {
			try {
				$selector = preg_replace_callback('/<\\$(-?[\w]+[\w-]*)>/', array($this, 'replaceVariableCallback'), $selector[0]);
			} catch (Exception $e) {
				throw $e->setLine($selector[1]);
			}
		}
		return $selectors;
	}

	/**
	 * Zkompiluje proměnnou pro použití v selektoru
	 *
	 * @param array
	 * @return string
	 */
	protected function replaceVariableCallback($matches) {
		return $this->generator->compileValue($this->findVariable($matches[1]));
	}

	/**
	 * Kominuje selektory rodičovského bloku s aktuálním
	 *
	 * @param array
	 * @param array
	 * @param int
	 * @return array
	 */
	protected function combineSelectors(array $parent, array $child, $group = 0) {
		if (empty($parent) && empty($child)) {
			return array();
		}
		if (empty($parent)) {
			$parent[] = '';
		}
		if (empty($child)) {
			$child[] = '';
		}
		$selectors = array();
		foreach ($parent as $key => $outer) {
			foreach ($child as $inner) {
				if (preg_match('/^&([0-9]++)(.*+)$/', $inner, $matches)) {
					if ($group == 0 || $key % $group + 1 != $matches[1]) {
						continue;
					}
					$inner = '&' . $matches[2];
				}
				$selectors[] = $outer .
						($inner == '' ||
								in_array(substr($inner, 0, 1), array('+', '>', '~', Compiler::SELF_SELECTOR)) ||
								in_array(substr($outer, -1), array('+', '>', '~', Compiler::SELF_SELECTOR)) ||
								$outer == '' ? '' : ' ') .
						($inner != '' && substr($inner, 0, 1) == Compiler::SELF_SELECTOR ? substr($inner, 1) : $inner);
			}
		}
		return $selectors;
	}

	/**
	 * Zjistí kontext redukce
	 *
	 * @return array
	 */
	protected function & getReducedContext() {
		if ($this->inMedia) {
			return end($this->reduced)->properties;
		} else {
			return $this->reduced;
		}
	}

	/**
	 * Najde nebo vytvoří blok
	 *
	 * @param Block
	 * @param array
	 * @return Block
	 */
	protected function getReduced(Block $block, array $selectors) {
		$context = & $this->getReducedContext();
		if ($block instanceof NestedRule || $block instanceof Mixin) {
			array_shift($selectors);
			$selectors = array_unique($selectors);
			foreach ($context as $reduced) {
				if ($reduced instanceof Rule && $selectors == $reduced->selectors) {
					return $reduced;
				}
			}
			return $context[] = new Rule($selectors);
		} elseif ($block instanceof Media) {
			$this->inMedia = TRUE;
			return $context[] = new Media($block->media, $block->line);
		} elseif ($block instanceof FontFace || $block instanceof Main) {
			return $context[] = new $block;
		} else {
			throw new \Exception("Neimplementováno");
		}
	}

	/**
	 * Zjistí zda je pole selektorů prázdné
	 *
	 * @param array
	 * @return bool
	 */
	protected function emptySelectors(array $selectors) {
		return count($selectors) == 1;
	}

	/**
	 * Redukce stromu
	 *
	 * @param Block
	 * @param array
	 * @param array
	 * @return void
	 */
	protected function reduceBlock(Block $block, array $selectors = array(), array $variables = array()) {
		//inicializace nové vrstvy proměnných
		if (!$block instanceof Main) {
			$this->variables[] = array();
			foreach ($variables as $variable) {
				try {
					$this->saveVariable($variable[0], $this->reduceValue($variable[1]), $block instanceof Mixin);
				} catch (Exception $e) {
					throw $e->setLine(end($variable));
				}
			}
		}

		if ($block instanceof NestedRule) {
			$group = array_shift($selectors);
			$selectors = $this->combineSelectors($this->replaceVariables($block->prefixes), $selectors);
			$selectors = $this->combineSelectors($selectors, $this->replaceVariables($block->selectors), $group);
			array_unshift($selectors, count($block->selectors));
		}

		$reduced = $this->getReduced($block, $selectors);

		foreach ($block->properties as $property) {
			if (is_array($property)) {
				try {
					if ($property[0] == Compiler::$prefixes['variable']) {
						if (count($property) == 5) { //zápis do pole
							$this->saveToMap($property[1], $this->valueToIndex($property[4]), $this->reduceValue($property[2]));
						} else {
							$this->saveVariable($property[1], $this->reduceValue($property[2]));
						}
					} elseif ($property[0] == Compiler::$prefixes['mixin']) {
						//TODO: volat $this->reduceValue($property[2]) ?
						$this->callMixin($property[1], $property[2], $selectors);
					} elseif ($property[0] == Compiler::$prefixes['none'] ||
							$property[0] == Compiler::$prefixes['important'] ||
							$property[0] == Compiler::$prefixes['raw']) {
						if ($reduced instanceof Main ||
								$reduced instanceof Media ||
								($reduced instanceof Rule && $this->emptySelectors($selectors))) {
							throw new Exception("Vlastnost nemůže být v globálním bloku ani v @media bloku");
						}
						$reduced->properties[] = array($property[0], $property[1], $this->reduceValue($property[2]));
					} elseif ($property[0] == Compiler::$prefixes['special'] && $property[1] == 'include') {
						if ($this->inMedia || ($reduced instanceof Rule && !$this->emptySelectors($selectors))) {
							throw new Exception("Include může být jen v globálním bloku");
						}
						$this->callInclude($property);
					} elseif ($property[0] == Compiler::$prefixes['special'] && $property[1] == 'import') {
						if ($this->inMedia || ($reduced instanceof Rule && !$this->emptySelectors($selectors))) {
							throw new Exception("Import může být jen v globálním bloku");
						}
						$path = $this->reduceValue($property[2]);
						if ($path[0] !== 'string') {
							throw new Exception("Název vkládaného souboru musí být řetězec");
						}
						$media = $this->convertMedia($this->reduceValue($property[3]));
						$context = & $this->getReducedContext();
						$context[] = array('import', $path, $media);
					} elseif ($property[0] == Compiler::$prefixes['special'] && $property[1] == 'charset') {
						if (!$reduced instanceof Main) {
							throw new Exception("Charset může být jen v globálním bloku");
						}
						$value = $this->reduceValue($property[2]);
						if ($value[0] !== 'string') {
							throw new Exception("Název kódování musí být řetězec");
						}
						$context = & $this->getReducedContext();
						$context[] = array('charset', $value);
					} else {
						throw new \Exception("Neimplementováno");
					}
				} catch (Exception $e) {
					throw $e->setLine(end($property));
				}
			} elseif ($property instanceof NestedRule) {
				if ($reduced instanceof AtRule && !$reduced instanceof Media) {
					throw new Exception("S výjimkou @media at-rules nesmí obsahovat pravidlo");
				}
				$this->callBlock($property, $selectors);
			} elseif ($property instanceof Mixin) {
				if ($reduced instanceof AtRule) {
					throw new Exception("At-rules nesmí obsahovat mixin");
				}
				if (array_key_exists($property->name, $this->mixins)) {
					$e = new Exception("Mixin '$property->name' již existuje");
					throw $e->setLine($property->line);
				}
				$property->file = $this->getFile();
				$this->mixins[$property->name] = $property;
			} elseif ($property instanceof Media) {
				try {
					$property->media = $this->convertMedia($this->reduceValue($property->media));
				} catch (Exception $e) {
					throw $e->setLine($property->line);
				}
				$this->reduceBlock($property);
			} elseif ($property instanceof FontFace) {
				$this->reduceBlock($property);
			} else {
				throw new \Exception("Neimplementováno");
			}
		}

		if ($block instanceof Media) {
			$this->inMedia = FALSE;
		}

		//zrušení nejvyšší vrstvy proměnných
		if (!$block instanceof Main) {
			array_pop($this->variables);
		}
	}

	/**
	 * Převede media na typ raw
	 *
	 * @param array
	 * @return array
	 */
	protected function convertMedia(array $media) {
		if ($media[0] == 'string') {
			return array('raw', Compiler::stringDecode($media[1]));
		} elseif ($media[0] == 'raw') {
			return $media;
		} else {
			throw new Exception("Media musí být řetězec");
		}
	}

	/**
	 * Volání vnořeného bloku
	 *
	 * @param NestedRule
	 * @param array
	 * @return void
	 */
	protected function callBlock(NestedRule $block, array $selectors) {
		if ($block->statement !== NULL) {
			//je definována řídící struktura
			try {
				switch ($block->statement[0]) {
					case 'if':
						if ($this->evaluateCondition($this->evaluateExpression($block->statement['expression']))) {
							$block->statement['status'] = TRUE;
							$this->reduceBlock($block, $selectors);
						} else {
							$block->statement['status'] = FALSE;
						}
						break;
					case 'elseif':
						if ($block->statement['condition']->statement['status']) {
							$block->statement['status'] = TRUE;
						} elseif ($this->evaluateCondition($this->evaluateExpression($block->statement['expression']))) {
							$block->statement['status'] = TRUE;
							$this->reduceBlock($block, $selectors);
						} else {
							$block->statement['status'] = FALSE;
						}
						break;
					case 'else':
						if (!$block->statement['condition']->statement['status']) {
							$this->reduceBlock($block, $selectors);
						}
						break;
					case 'while':
						while ($this->evaluateCondition($this->evaluateExpression($block->statement['expression']))) {
							$this->reduceBlock($block, $selectors);
						}
						break;
					case 'for':
						$begin = $this->reduceValue($block->statement[2]);
						$end = $this->reduceValue($block->statement[3]);
						if (!$this->isInteger($begin)) {
							throw new Exception("Počáteční hodnota for cyklu není číslo");
						}
						if (!$this->isInteger($end)) {
							throw new Exception("Koncová hodnota for cyklu není číslo");
						}
						$begin = (int) $begin[1];
						$end = (int) $end[1];
						if ($begin < $end) {
							for ($i = $begin; $i <= $end; ++$i) {
								$variables = array();
								$variables[] = array($block->statement[1][1], array('unit', $i, ''), $block->statement[1]);
								$this->reduceBlock($block, $selectors, $variables);
							}
						} else {
							for ($i = $begin; $i >= $end; --$i) {
								$variables = array();
								$variables[] = array($block->statement[1][1], array('unit', $i, ''), $block->statement[1]);
								$this->reduceBlock($block, $selectors, $variables);
							}
						}
						break;
					case 'foreach':
						$map = $this->findVariable($block->statement[1][1]);
						foreach ($map[1] as $key => $value) {
							$variables = array();
							if ($block->statement[3] !== NULL) {
								$variables[] = array($block->statement[2][1], $this->indexToValue($key), $block->statement[1]);
							}
							$variables[] = array($block->statement[3][1], $value, $block->statement[1]);
							$this->reduceBlock($block, $selectors, $variables);
						}
						break;
					default:
						throw new \Exception("Neimplementováno");
						break;
				}
			} catch (Exception $e) {
				throw $e->setLine($block->statement['line']);
			}
		} else {
			//bez řídících struktur
			$this->reduceBlock($block, $selectors);
		}
	}

	/**
	 * Volání mixinu
	 *
	 * @param string
	 * @param array
	 * @param array
	 * @return void
	 */
	protected function callMixin($name, array $value, array $selectors) {
		if (!array_key_exists($name, $this->mixins)) {
			throw new Exception("Mixin '$name' není definován");
		}
		$list = $this->valueToArgs($value);
		$mixin = $this->mixins[$name];
		$args = array();
		$args[] = array('_argc', array('unit', count($list), ''));
		$args[] = array('_argv', $value);
		foreach ($mixin->args as $key => $default) {
			$args[] = array($key, count($list) > 0 ? array_shift($list) : ($default[0] === NULL ? array('bool', FALSE) : $default[0]), $default[1]);
		}
		try {
			$this->reduceBlock($mixin, $selectors, $args);
		} catch (Exception $e) {
			throw $e->setFile($mixin->file);
		}
	}

	/**
	 * Vložení souboru
	 *
	 * @param string
	 * @param string
	 * @return void
	 */
	protected function callInclude(array $include) {
		$value = $this->reduceValue($include[2]);
		if ($value[0] !== 'string') {
			throw new Exception("Název vkládaného souboru musí být řetězec");
		}
		$file = Compiler::stringDecode($value[1]);
		foreach ($this->includePaths as $path) {
			$path .= DIRECTORY_SEPARATOR . $file;
			$extension = pathinfo($file, PATHINFO_EXTENSION);
			if (!is_file($path) || !is_readable($path)) {
				continue;
			} elseif ($extension == 'iss') {
				$this->addFile($path);
				try {
					$tree = $this->parser->parse(file_get_contents($this->getFile()));
					if ($include[3] !== NULL) {
						$block = new Media($include[3], $include[4]);
						$block->properties = $tree->properties;
						$this->reduceBlock($block);
					} else {
						$this->reduceBlock($tree);
					}
				} catch (Exception $e) {
					if ($e->getFile() === NULL) {
						$e->setFile($this->getFile());
					}
					throw $e;
				}
				$this->removeFile($path);
				return;
			} elseif ($extension == 'css') {
				$this->addFile($path);
				$context = & $this->getReducedContext();
				$context[] = file_get_contents($path);
				$this->removeFile($path);
				return;
			}
		}
		throw new Exception("Soubor '$file' se nepodařilo vložit");
	}

	/**
	 * Zjistí existenci funkce
	 *
	 * @param string
	 * @return bool
	 */
	public function functionExists($name) {
		return $this->functions->offsetExists($name);
	}

	/**
	 * Volání funkce
	 *
	 * @param string
	 * @param array
	 * @return array
	 */
	protected function callFunction($name, array $args) {
		$value = call_user_func_array($this->functions[$name], $args);
		if (!is_array($value)) {
			throw new Exception("Funkce '$name' nevrátila pole");
		}
		return $value;
	}

	/**
	 * Převod volání mixinu na seznam hodnot
	 *
	 * @param array
	 * @return array
	 */
	protected function valueToArgs(array $value) {
		switch ($value[0]) {
			case 'args':
				return $this->valueToArgs($value[1]);
				break;
			case 'list':
				array_shift($value);
				return $value;
				break;
			default:
				return array($value);
				break;
		}
	}

	/**
	 * Uložení hodnoty proměnné
	 *
	 * @param string
	 * @param array
	 * @param bool
	 * @return void
	 */
	protected function saveVariable($name, $value, $force = FALSE) {
		if ($force) {
			$this->variables[count($this->variables) - 1][$name] = $value;
			return;
		}
		for ($i = count($this->variables) - 1; $i >= 0; --$i) {
			foreach ($this->variables[$i] as $key => $_) {
				if ($name == $key) {
					$this->variables[$i][$key] = $value;
					return;
				}
			}
		}
		$this->saveVariable($name, $value, TRUE);
	}

	/**
	 * Uložení hodnoty do pole
	 *
	 * @param string
	 * @param array
	 * @param array
	 * @return void
	 */
	protected function saveToMap($name, $index, $value) {
		foreach ($this->variables as $layer => &$variables) {
			foreach ($variables as $key => &$map) {
				if ($name == $key && $map[0] == 'map') {
					$map[1][$index] = $value;
					return;
				}
			}
		}
		throw new Exception("Pole '$name' nenalezeno");
	}

	/**
	 * Nalezení hodnoty proměnné
	 *
	 * @param string
	 * @return array
	 */
	protected function findVariable($name) {
		for ($i = count($this->variables) - 1; $i >= 0; --$i) {
			foreach ($this->variables[$i] as $key => $value) {
				if ($name == $key) {
					return $value;
				}
			}
		}
		throw new Exception("Proměnná '$name' neexistuje");
	}

	/**
	 * Nalezení hodnoty v poli
	 *
	 * @param string
	 * @param array
	 * @return void
	 */
	protected function findInMap($name, $index) {
		foreach ($this->variables as $variables) {
			foreach ($variables as $key => $map) {
				if ($name == $key && $map[0] == 'map') {
					if (array_key_exists($index, $map[1])) {
						return $map[1][$index];
					} else {
						throw new Exception("Nedefinovaný klíč '$index' v poli '$name'");
					}
				}
			}
		}
		throw new Exception("Pole $name nenalezeno");
	}

	/**
	 * Převede hodnotu na index v poli
	 *
	 * @param array
	 * @return int|float|string
	 */
	protected function valueToIndex(array $key) {
		if ($key[0] == 'unit') {
			if (preg_match('/^-?[0-9]+$/', $key[1], $_)) {
				return (int) $key[1];
			} else {
				return (float) $key[1];
			}
		} elseif ($key[0] == 'string') {
			return $key[1];
		}
		//nemělo by nastat
		throw new \Exception("Neplatný index do pole");
	}

	/**
	 * Převede index na hodnotu
	 *
	 * @param int|float|string
	 * @return array
	 */
	protected function indexToValue($key) {
		if (is_int($key) || is_float($key)) {
			return array('unit', $key, '');
		} elseif (is_string($key)) {
			return array('string', $key);
		}
		throw new \Exception("Neplatný index do pole");
	}

	/**
	 * Zjednodušení hodnoty
	 *
	 * @todo accessor
	 *
	 * @param array
	 * @return array
	 */
	protected function reduceValue(array $value) {
		while (in_array($value[0], array('args', 'list', 'expression', 'variable', 'function', 'rawmap'))) {
			switch ($value[0]) {
				case 'args':
				case 'list':
					$type = array_shift($value);
					foreach ($value as $key => $item) {
						$value[$key] = $this->reduceValue($item);
					}
					if ($type == 'args' && count($value) == 1) {
						$value = $value[0];
					} else {
						array_unshift($value, $type);
					}
					break 2;
				case 'expression':
					$value = $this->evaluateExpression($value);
					break;
				case 'variable':
					if (count($value) == 3) {
						$value = $this->findInMap($value[1], $this->valueToIndex($this->reduceValue($value[2])));
					} else {
						$value = $this->findVariable($value[1]);
					}
					break;
				case 'function':
					foreach ($value[2] as $key => $item) {
						$value[2][$key] = $this->reduceValue($item);
					}
					if ($this->functionExists($value[1])) {
						$result = $this->callFunction($value[1], $value[2]);
						if ($value == $result) {
							break 2;
						}
						$value = $result;
						break;
					}
					break 2;
				case 'rawmap':
					$value[0] = 'map';
					$elements = array();
					foreach ($value[1] as $item) {
						$key = $this->reduceValue($item[0]);
						$val = $this->reduceValue($item[1]);
						if (!in_array($key[0], array('unit', 'string', 'autokey'))) {
							throw new Exception("Typ $key[0] nemůže být použit jako index v poli.");
						}
						if ($key[0] == 'unit' && !$this->isInteger($key)) {
							throw new Exception("Jen číslo bez jednotky může být indexem v poli.");
						}
						switch ($key[0]) {
							case 'unit':
							case 'string':
								$elements[$this->valueToIndex($key)] = $val;
								break;
							case 'autokey':
								$elements[] = $val;
								break;
						}
					}
					$value[1] = $elements;
					break 2;
				default:
					throw new \Exception("Neimplementováno");
					break;
			}
		}
		return $value;
	}

	/**
	 * Zjistí zda hodnota je číslo bez jednotky
	 *
	 * @param array
	 * @return bool
	 */
	protected function isInteger(array $value) {
		return $value[0] == 'unit' && ($value[2] == '' || $value[2] == '#');
	}

	/**
	 * Vyhodnocení výrazu
	 *
	 * @param array
	 * @return array
	 */
	protected function evaluateExpression(array $expr) {
		if ($expr[0] != 'expression') {
			return $this->reduceValue($expr);
		}

		array_shift($expr);

		//převod výrazu do postfixové notace
		$postfix = array();
		$stack = new Stack();
		foreach ($expr as $symbol) {
			if ($symbol == '(') {
				$stack->push($symbol);
			} elseif ($symbol == ')') {
				while ($top = $stack->pop()) {
					if ($top == '(') {
						break;
					}
					$postfix[] = $top;
				}
			} elseif ($symbol[0] == 'binary' && array_key_exists($symbol[1], Compiler::$binaryOperators)) {
				if ($stack->count() == 0) {
					$stack->push($symbol);
					continue;
				}
				$top = $stack->top();
				while ($top != '(' && $stack->count() > 0 && Compiler::$binaryOperators[$symbol[1]] <= Compiler::$binaryOperators[$top[1]]) {
					$postfix[] = $stack->pop();
					$top = $stack->top();
				}
				$stack->push($symbol);
			} elseif ($symbol[0] == 'unary' && array_key_exists($symbol[1], Compiler::$unaryOperators)) {
				$stack->push($symbol);
			} else {
				$postfix[] = $this->reduceValue($symbol);
			}
		}
		while ($stack->count() > 0) {
			$postfix[] = $stack->pop();
		}

		//vyhodnocení výrazu
		$stack->clear();
		foreach ($postfix as $symbol) {
			if ($symbol[0] == 'unary' && array_key_exists($symbol[1], Compiler::$unaryOperators)) {
				if ($stack->count() < 1) {
					throw new Exception("Nedostatek operandů pro unární operátor '$symbol[1]'");
				}
				$symbol = $this->evaluateUnaryOperation($symbol[1], $stack->pop());
			} elseif ($symbol[0] == 'binary' && array_key_exists($symbol[1], Compiler::$binaryOperators)) {
				if ($stack->count() < 2) {
					throw new Exception("Nedostatek operandů pro binární operátor '$symbol[1]'");
				}
				$value2 = $stack->pop();
				$symbol = $this->evaluateBinaryOperation($symbol[1], $stack->pop(), $value2);
			}
			$stack->push($symbol);
		}
		if ($stack->count() <> 1) {
			throw new Exception("Výsledkem výrazu má být pouze 1 hodnota");
		}

		return $stack->pop();
	}

	/**
	 * Zjistí jednotku výsledku
	 *
	 * @param array
	 * @param array
	 * @return string
	 */
	protected function getUnit($value1, $value2) {
		if ($value1[2] == $value2[2]) {
			return $value1[2];
		}
		if (!$this->isInteger($value1) && !$this->isInteger($value2)) {
			throw new Exception("Nelze provádět operaci s jednotkami '$value1[2]' a '$value2[2]'");
		}
		return $value1[2] != '' ? $value1[2] : $value2[2];
	}

	/**
	 * Vyhodnocení podmínky
	 *
	 * @param array
	 * @return bool
	 */
	protected function evaluateCondition($value) {
		if ($value[0] == 'bool') {
			return $value[1];
		} elseif ($value[0] == 'unit') {
			return $value[1] != 0;
		}
		throw new Exception("Výsledkem podmínkového výrazu má být typ unit nebo bool");
	}

	/**
	 * Vyhodnocení unární operace
	 *
	 * @param string
	 * @param array
	 * @return array
	 */
	protected function evaluateUnaryOperation($operator, $value) {
		if ($operator == '!' && in_array($value[0], array('bool', 'unit'))) {
			return array('bool', !$value[1]);
		} elseif (in_array($operator, array('+', '-'))) {
			return $this->evaluateBinaryOperation($operator, array('unit', 0, ''), $value);
		}
		throw new Exception("Nepovolená operace ($operator $value[0])");
	}

	/**
	 * Vyhodnocení binární operace
	 *
	 * @param string
	 * @param array
	 * @param array
	 * @return array
	 */
	protected function evaluateBinaryOperation($operator, $value1, $value2) {
		if (in_array($operator, array('&&', '||', '^^', '='))) {
			switch ($operator) {
				case '&&':
					$answer[] = 'bool';
					$answer[] = $value1[1] && $value2[1];
					break;
				case '||':
					$answer[] = 'bool';
					$answer[] = $value1[1] || $value2[1];
					break;
				case '^^':
					$answer[] = 'bool';
					$answer[] = $value1[1] xor $value2[1];
					break;
				case '=':
					$answer[] = 'bool';
					$answer[] = $value1[1] == $value2[1];
					break;
			}
			return $answer;
		} elseif ($operator == '.' && $value1[0] == 'string' && $value2[0] == 'raw') {
			return array('string', '\'' . substr($value2[1], 1, -1) . substr(Compiler::stringEncode($value2[1]), 1));
		} elseif ($operator == '.' && $value1[0] == 'raw' && $value2[0] == 'string') {
			return array('string', substr(Compiler::stringEncode($value1[1]), 0, -1) . substr($value2[1], 1, -1) . '\'');
		} elseif ($operator == '.' && $value1[0] == 'string' && $value2[0] == 'unit') {
			return array('string', '\'' . substr($value1[1], 1, -1) . $value2[1] . $this->generator->compileUnit($value2) . '\'');
		} elseif ($operator == '.' && $value1[0] == 'unit' && $value2[0] == 'string') {
			return array('string', '\'' . $value1[1] . $this->generator->compileUnit($value1) . substr($value2[1], 1, -1) . '\'');
		} elseif ($operator == '.' && $value1[0] == 'string' && $value2[0] == 'string') {
			return array('string', '\'' . substr($value1[1], 1, -1) . substr($value2[1], 1, -1) . '\'');
		} elseif (array_key_exists($operator, Compiler::$binaryOperators) && $value1[0] == 'unit' && $value2[0] == 'unit') {
			$answer = array();
			switch ($operator) {
				case '*':
					$answer[] = 'unit';
					$answer[] = $value1[1] * $value2[1];
					$answer[] = $this->getUnit($value1, $value2);
					break;
				case '/':
					if ($value2[1] == 0) {
						throw new Exception("Dělení nulou");
					}
					$answer[] = 'unit';
					$answer[] = $value1[1] / $value2[1];
					$answer[] = $this->getUnit($value1, $value2);
					break;
				case '%':
					$answer[] = 'unit';
					$answer[] = $value1[1] % $value2[1];
					$answer[] = $this->getUnit($value1, $value2);
					break;
				case '+':
					$answer[] = 'unit';
					$answer[] = $value1[1] + $value2[1];
					$answer[] = $this->getUnit($value1, $value2);
					break;
				case '-':
					$answer[] = 'unit';
					$answer[] = $value1[1] - $value2[1];
					$answer[] = $this->getUnit($value1, $value2);
					break;
				case '.':
					$answer[] = 'string';
					$answer[] = '\'' . $value1[1] . $this->generator->compileUnit($value1) . $value2[1] . $this->generator->compileUnit($value2) . '\'';
					break;
				case '>':
					$answer[] = 'bool';
					$answer[] = $value1[1] > $value2[1];
					break;
				case '<':
					$answer[] = 'bool';
					$answer[] = $value1[1] < $value2[1];
					break;
				case '>=':
					$answer[] = 'bool';
					$answer[] = $value1[1] >= $value2[1];
					break;
				case '<=':
					$answer[] = 'bool';
					$answer[] = $value1[1] <= $value2[1];
					break;
				case '<>':
				case '!=':
					$answer[] = 'bool';
					$answer[] = $value1[1] != $value2[1];
					break;
				default:
					throw new Exception("Neznámý operátor");
					break;
			}
			return $answer;
		}
		throw new Exception("Nepovolená operace ($value1[0] $operator $value2[0])");
	}

}