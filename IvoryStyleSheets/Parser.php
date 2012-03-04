<?php

/**
 * @package Ivory
 * @license MIT, LGPL, BSD
 * @copyright (c) 2011 Jáchym Toušek
 */

namespace Ivory\StyleSheets;

/**
 * Parser IvoryStyleSheets
 *
 * @author Jáchym Toušek
 */
class Parser extends Object {

	/**
	 * Regulární výraz pro řetězec
	 *
	 * @const string
	 */
	const RE_STRING = '(?:\'(?:\\\\.|[^\'\\\\])*\'|"(?:\\\\.|[^"\\\\])*")';

	/**
	 * Na co lze při parsování narazit (seřazeno dle priority)
	 *
	 * @var array
	 */
	static protected $pieces = array(
			'atInclude',
			'atFontFace',
			'atMedia',
			'atImport',
			//'atKeyFrames',
			'atCharset',
			'property',
			'assign',
			'mapAccess',
			'mixinCallSimple',
			'mixinCall',
			'mixinBegin',
			'ruleBegin',
			'blockEnd',
	);

	/**
	 * Buffer
	 *
	 * @var string
	 */
	protected $buffer;

	/**
	 * Aktuální místo v bufferu
	 *
	 * @var int
	 */
	private $offset;

	/**
	 * Zásobník bloků
	 *
	 * @var Stack
	 */
	protected $stack;

	/**
	 * Nejvyšší pozice v bufferu
	 *
	 * @var int
	 */
	private $maxOffset;

	/**
	 * Poslední blok měl if konstrukci
	 *
	 * @var bool;
	 */
	protected $condition;

	/**
	 * Nastaví pozici v bufferu
	 *
	 * @param int
	 * @return void
	 */
	protected function resetOffset() {
		$this->offset = $this->maxOffset = 0;
	}

	/**
	 * Nastaví pozici v bufferu
	 *
	 * @param int
	 * @return void
	 */
	protected function setOffset($offset) {
		$this->offset = $offset;
		if ($this->maxOffset < $offset) {
			$this->maxOffset = $offset;
		}
	}

	/**
	 * Vrátí pozici v bufferu
	 *
	 * @return int
	 */
	protected function getOffset() {
		return $this->offset;
	}

	/**
	 * Posune ukazatel
	 *
	 * @param int
	 * @return void
	 */
	protected function moveOffset($move) {
		$this->setOffset($this->getOffset() + $move);
	}

	/**
	 * Zjistí číslo řádky na základě offsetu
	 *
	 * @param int
	 * @return int
	 */
	protected function getLine($offset = NULL) {
		if ($offset === NULL) {
			$offset = $this->getOffset();
		}
		return substr_count(substr($this->buffer, 0, $offset), "\n") + 1;
	}

	/**
	 * Vyhození chyby
	 *
	 * @param string
	 * @param int
	 * @return void
	 */
	protected function throwError($message, $line) {
		$exception = new Exception($message);
		$exception->setLine($line);
		throw $exception;
	}

	/**
	 * Parsování řetězce
	 *
	 * @param string
	 * @return Main
	 */
	public function parse($input) {
		$this->buffer = $this->removeComments($input);
		$this->resetOffset();
		$this->condition = NULL;
		$this->stack = new Stack;
		$this->stack->push(new Main);
		$this->whitespace();

		while ($this->parseNext());

		if ($this->getOffset() <> strlen($this->buffer)) {
			$this->throwError("Chyba parsování", $this->getLine($this->maxOffset));
		}

		if (!$this->getActualBlock() instanceof Main) {
			$this->throwError("Neuzavřený blok", $this->getLine($this->maxOffset));
		}

		return $this->getActualBlock();
	}

	/**
	 * Vrátí aktuální blok
	 *
	 * @returns Block
	 */
	protected function getActualBlock() {
		return $this->stack->top();
	}

	/**
	 * Selektory na začátku bloku všeně prefixů
	 *
	 * @param NULL
	 * @param NULL
	 * @param NULL
	 * @return bool
	 */
	protected function extendedSelectors(&$statement, &$prefixes, &$selectors) {
		$return = FALSE;
		if ($this->controlFlowStatement($statement)) {
			$return = TRUE;
		}
		if ($this->selectors($prefixes)) {
			$return = TRUE;
			if ($this->char('>>')) {
				if (!$this->selectors($selectors)) {
					$selectors = array();
				}
			} else {
				$selectors = $prefixes;
				$prefixes = array();
			}
		} else {
			$selectors = array();
			$prefixes = array();
		}
		return $return;
	}

	/**
	 * Řídící struktura
	 *
	 * @param NULL
	 * @return bool
	 */
	protected function controlFlowStatement(&$statement) {
		$line = $this->getLine();
		if ($this->ifStatement($statement) ||
				$this->elseIfStatement($statement) ||
				$this->elseStatement($statement) ||
				$this->whileStatement($statement) ||
				$this->forStatement($statement) ||
				$this->forEachStatement($statement)) {
			$statement['line'] = $line;
			return TRUE;
		}
		return FALSE;
	}

	/**
	 * If podmínka
	 *
	 * @param NULL
	 * @return bool
	 */
	protected function ifStatement(&$statement) {
		$x = $this->getOffset();
		if ($this->char('if') && $this->char('(') && $this->expression($expr) && $this->char(')')) {
			$statement = array('if', 'expression' => $expr);
			return TRUE;
		}
		$this->setOffset($x);
		return FALSE;
	}

	/**
	 * Elseif podmínka
	 *
	 * @param NULL
	 * @return bool
	 */
	protected function elseIfStatement(&$statement) {
		$x = $this->getOffset();
		if ($this->findCondition() && $this->char('elseif') && $this->char('(') && $this->expression($expr) && $this->char(')')) {
			$statement = array('elseif', 'expression' => $expr);
			return TRUE;
		}
		$this->setOffset($x);
		return FALSE;
	}

	/**
	 * Else větev
	 *
	 * @param NULL
	 * @return bool
	 */
	protected function elseStatement(&$statement) {
		if ($this->findCondition() && $this->char('else')) {
			$statement = array('else');
			return TRUE;
		}
		return FALSE;
	}

	/**
	 * While cyklus
	 *
	 * @param NULL
	 * @return bool
	 */
	protected function whileStatement(&$statement) {
		$x = $this->getOffset();
		if ($this->char('while') && $this->char('(') && $this->expression($expr) && $this->char(')')) {
			$statement = array('while', 'expression' => $expr);
			return TRUE;
		}
		$this->setOffset($x);
		return FALSE;
	}

	/**
	 * For cyklus
	 *
	 * @param NULL
	 * @return bool
	 */
	protected function forStatement(&$statement) {
		$x = $this->getOffset();
		if ($this->char('for') &&
				$this->char('(') &&
				$this->variable($variable, FALSE) &&
				$this->char(':') &&
				$this->expression($expr1) &&
				$this->char('..') &&
				$this->expression($expr2) &&
				$this->char(')')) {
			$statement = array('for', $variable, $expr1, $expr2);
			return TRUE;
		}
		$this->setOffset($x);
		return FALSE;
	}

	/**
	 * ForEach cyklus
	 *
	 * @param NULL
	 * @return bool
	 */
	protected function forEachStatement(&$statement) {
		$x = $this->getOffset();
		if ($this->char('foreach') &&
				$this->char('(') &&
				$this->variable($map, FALSE) &&
				$this->char('as') &&
				$this->forEachKey($key) &&
				$this->variable($value, FALSE) &&
				$this->char(')')) {
			$statement = array('foreach', $map, $key, $value);
			return TRUE;
		}
		$this->setOffset($x);
		return FALSE;
	}

	/**
	 * Pomocná funkce pro foreach cyklus
	 *
	 * @param NULL
	 * @return bool
	 */
	protected function forEachKey(&$key) {
		$x = $this->getOffset();
		if ($this->variable($key, FALSE) && $this->char(',')) {
			;
		} else {
			$key = NULL;
			$this->setOffset($x);
		}
		return TRUE;
	}

	/**
	 * Najde podmínku
	 *
	 * @return NestedRule
	 */
	protected function findCondition() {
		$filter = function ($item) {
				return $item instanceof NestedRule;
			};
		$blocks = array_filter($this->getActualBlock()->properties, $filter);
		$last = end($blocks);
		if ($last && $last->statement !== NULL && ($last->statement[0] == 'if' || $last->statement[0] == 'elseif')) {
			return $last;
		}
	}

	/**
	 * Selektory oddělené čárkami
	 *
	 * @param NULL
	 * @return bool
	 */
	protected function selectors(&$selectors) {
		$selectors = array();
		while ($this->selector($selector)) {
			$selectors[] = $selector;
			if (!$this->char(',')) {
				break;
			}
		}
		return count($selectors) > 0;
	}

	/**
	 * Jeden selektor
	 *
	 * @param NULL
	 * @return bool
	 */
	protected function selector(&$selector) {
		/**
		 * Vysvětlení regulárního výrazu:
		 * (?:								  #začátek subvýrazu
		 *	 >?							   #na začátku může být jeden znak > (musí být tady, aby se nenamatchovala sekvence >>)
		 *	 (?:							  #začátek vnitřního subvýrazu
		 *		 [^][@$\/\\%<>,;{}\'"]++	  #libovolné opakování většiny znaků (posessed quantifier pro zrychlení)
		 *	 |								#nebo
		 *		 \\[[^]]++\\]				 #atributvý selektor [attr=value] (posessed quantifier pro zrychlení)
		 *	 |								#nebo
		 *		 <\\$-?[\w]++(?:[\w-]*[\w])?> #proměnná, např. <$var>
		 *	 )								#konec vnitřního subvýrazu
		 * )+								   #konec subvýrazu, alespoň 1 opakování
		 * (?:								  #začátek velmi obskurní konstrukce :-P
		 *	 >*+							  #na konci selektoru se může vyskytnout znak >, je-li jich více, sežere všechny (posessed quantifier je nutný!!)
		 *	 (?<!>>)						  #záporné tvrzení o předcházejícím, pokud znaků > bylo více, nenamatchuje se ani jeden
		 * )?								   #na konci selektoru samozřejmě žádný znak > být nemusí, takže celá konstrukce je nepovinná
		 */
		$line = $this->getLine();
		if ($this->match('(?:>?(?:[^][@$\/\\%<>,;{}\'"]++|\\[[^]]++\\]|<\\$-?[\w]++(?:[\w-]*[\w])?>))+(?:>*+(?<!>>))?', $matches)) {
			 $selector = array(preg_replace_callback('/\\[[^]]++\\]|\\s*+([+>~])\\s*+|\\s++/', array($this, 'compressSelectorCallback'), trim($matches[0])), $line);
			 return TRUE;
		}
		return FALSE;
	}

	/**
	 * Callback pro odstranění zbytečných znaků ze selektoru
	 *
	 * @param array
	 * @return string
	 */
	protected function compressSelectorCallback(array $matches) {
		if (count($matches) == 2) {
			return $matches[1];
		} elseif (preg_match('/^\\s++$/', $matches[0])) {
			return ' ';
		}
		return $matches[0];
	 }

	/**
	 * Sekvence znaků, volitelně bílé znaky
	 *
	 * @param string
	 * @return bool
	 */
	protected function char($string, $whitespace = TRUE) {
		if (substr($this->buffer, $this->getOffset(), strlen($string)) == $string) {
			$this->moveOffset(strlen($string));
			if ($whitespace)
				$this->whitespace();
			return TRUE;
		}
		return FALSE;
	}

	/**
	 * Bílé znaky
	 *
	 * @return bool
	 */
	protected function whitespace() {
		return $this->match('\\s+', $_, FALSE);
	}

	/**
	 * Podřetězec odpovídající danému regulárnímu výrazu
	 *
	 * @param string
	 * @param NULL
	 * @return bool
	 */
	protected function match($regex, &$matches, $whitespace = TRUE) {
		if (preg_match('/' . $regex . '/Ais', $this->buffer, $matches, NULL, $this->getOffset())) {
			$this->moveOffset(strlen($matches[0]));
			if ($whitespace) {
				$this->whitespace();
			}
			return TRUE;
		}
		return FALSE;
	}

	/**
	 * Začátek mixinu
	 *
	 * @return bool
	 */
	protected function mixinBegin() {
		$x = $this->getOffset();
		if ($this->getActualBlock() instanceof Main &&
				$this->char(Compiler::$prefixes['mixin']) &&
				$this->name($name) &&
				$this->char('(') &&
				$this->arguments($args) &&
				$this->char(')') &&
				$this->char('{')) {
			$mixin = new Mixin($name, $args, $this->getLine($x));
			$this->getActualBlock()->properties[] = $mixin;
			$this->stack->push($mixin);
			return TRUE;
		}
	}

	/**
	 * Začátek bloku
	 *
	 * @return bool
	 */
	protected function ruleBegin() {
		if (!$this->getActualBlock() instanceof FontFace &&
				$this->extendedSelectors($statement, $prefixes, $selectors) &&
				$this->char('{')) {
			if ($statement[0] == 'elseif' || $statement[0] == 'else') {
				$statement['condition'] = $this->findCondition();
				if ($statement['condition'] === NULL) {
					throw new \Exception("Podmínka nenalezena");
				}
			}
			$block = new NestedRule($selectors, $prefixes, $statement);
			$this->getActualBlock()->properties[] = $block;
			$this->stack->push($block);
			return TRUE;
		}
		return FALSE;
	}

	/**
	 * Konec bloku
	 *
	 * @return bool
	 */
	protected function blockEnd() {
		if (!$this->getActualBlock() instanceof Main && $this->char('}')) {
			$this->stack->pop();
			return TRUE;
		}
		return FALSE;
	}

	/**
	 * Volání dalšího souboru
	 *
	 * @return bool
	 */
	protected function atInclude() {
		$x = $this->getOffset();
		if (($this->getActualBlock() instanceof Main || $this->getActualBlock() instanceof Mixin || $this->getActualBlock() instanceof NestedRule) &&
				$this->char('@include') &&
				$this->expression($path) &&
				($this->expression($media) || TRUE) && //nepovinné
				$this->end()) {
			$this->getActualBlock()->properties[] = array(Compiler::$prefixes['special'], 'include', $path, $media, $this->getLine($x));
			return TRUE;
		}
		$this->setOffset($x);
		return FALSE;
	}

	/**
	 * CSS import
	 *
	 * @return bool
	 */
	protected function atImport() {
		$x = $this->getOffset();
		if (($this->getActualBlock() instanceof Main || $this->getActualBlock() instanceof Mixin) &&
				$this->char('@import') &&
				$this->expression($path) &&
				($this->expression($media) || TRUE) && //nepovinné
				$this->end()) {
			$this->getActualBlock()->properties[] = array(Compiler::$prefixes['special'], 'import', $path, $media, $this->getLine($x));
			return TRUE;
		}
		$this->setOffset($x);
		return FALSE;
	}

	/**
	 * Kódování
	 *
	 * @return bool
	 */
	protected function atCharset() {
		$x = $this->getOffset();
		if (($this->getActualBlock() instanceof Main) &&
				$this->char('@charset') &&
				$this->expression($charset) &&
				$this->end()) {
			$this->getActualBlock()->properties[] = array(Compiler::$prefixes['special'], 'charset', $charset, $this->getLine($x));
			return TRUE;
		}
		$this->setOffset($x);
		return FALSE;
	}

	/**
	 * Media blok
	 *
	 * @return bool
	 */
	protected function atMedia() {
		$x = $this->getOffset();
		if (($this->getActualBlock() instanceof Main || $this->getActualBlock() instanceof Mixin) &&
				$this->char('@media') &&
				$this->expression($media) &&
				$this->char('{')) {
			$block = new Media($media, $this->getLine($x));
			$this->getActualBlock()->properties[] = $block;
			$this->stack->push($block);
			return TRUE;
		}
		$this->setOffset($x);
		return FALSE;
	}

	/**
	 * Definice vlastního písma
	 *
	 * @return bool
	 */
	protected function atFontFace() {
		$x = $this->getOffset();
		if (($this->getActualBlock() instanceof Main ||
				$this->getActualBlock() instanceof Media ||
				$this->getActualBlock() instanceof Mixin) &&
				$this->char('@font-face') &&
				$this->char('{')) {
			$block = new FontFace;
			$this->getActualBlock()->properties[] = $block;
			$this->stack->push($block);
			return TRUE;
		}
		$this->setOffset($x);
		return FALSE;
	}

	/**
	 * Volání mixinu bez parametrů
	 *
	 * @return bool
	 */
	protected function mixinCallSimple() {
		$x = $this->getOffset();
		$prefix = Compiler::$prefixes['mixin'];
		if ($this->char($prefix) &&
				$this->name($name) &&
				$this->end()) {
			$this->getActualBlock()->properties[] = array($prefix, $name, array('list'), $this->getLine($x));
			return TRUE;
		}
		$this->setOffset($x);
		return FALSE;
	}

	/**
	 * Volání mixinu s parametry
	 *
	 * @return bool
	 */
	protected function mixinCall() {
		$x = $this->getOffset();
		$prefix = Compiler::$prefixes['mixin'];
		if ($this->char($prefix) &&
				$this->name($name) &&
				$this->char(':') &&
				$this->commaList($value) &&
				$this->end()) {
			$this->getActualBlock()->properties[] = array($prefix, $name, $value, $this->getLine($x));
			return TRUE;
		}
		$this->setOffset($x);
		return FALSE;
	}

	/**
	 * Vlastnost
	 *
	 * @return bool
	 */
	protected function property() {
		$x = $this->getOffset();
		if (!$this->getActualBlock() instanceof Main &&
				$this->prefix($prefix, array('important', 'raw', 'none')) &&
				$this->name($name) &&
				$this->char(':') &&
				$this->commaList($value) &&
				$this->end()) {
			$this->getActualBlock()->properties[] = array($prefix, $name, $value, $this->getLine($x));
			return TRUE;
		}
		$this->setOffset($x);
		return FALSE;
	}

	/**
	 * Přiřazení do proměnné
	 *
	 * @return bool
	 */
	protected function assign() {
		$x = $this->getOffset();
		$prefix = Compiler::$prefixes['variable'];
		if ($this->char($prefix) &&
				$this->name($name) &&
				$this->char(':') &&
				$this->commaList($value) &&
				$this->end()) {
			$this->getActualBlock()->properties[] = array($prefix, $name, $value, $this->getLine($x));
			return TRUE;
		}
		$this->setOffset($x);
		return FALSE;
	}

	/**
	 * Zápis do pole
	 *
	 * @return bool
	 */
	protected function mapAccess() {
		$x = $this->getOffset();
		$prefix = Compiler::$prefixes['variable'];
		if ($this->char($prefix) &&
				$this->name($name) &&
				$this->index($index) &&
				$this->char(':') &&
				$this->spaceList($value) &&
				$this->end()) {
			$this->getActualBlock()->properties[] = array($prefix, $name, $value, $this->getLine($x), $index);
			return TRUE;
		}
		$this->setOffset($x);
		return FALSE;
	}

	/**
	 * Prefix vlastnosti
	 *
	 * @param NULL
	 * @param array
	 * @return bool
	 */
	protected function prefix(&$prefix, array $allowed = NULL) {
		foreach (Compiler::$prefixes as $key => $char) {
			if ((empty($allowed) || in_array($key, $allowed)) && $this->char($char, FALSE)) {
				$prefix = $char;
				return TRUE;
			}
		}
		$prefix = Compiler::$prefixes['none'];
		return TRUE;
	}

	/**
	 * Název vlastnosti nebo funkce
	 *
	 * @param NULL
	 * @return bool
	 */
	protected function name(&$name) {
		//TODO: musí zde být '(?: ... )' ?
		if ($this->match('-?[\w]++(?:[\w-]*[\w])?', $matches, FALSE)) {
			$name = $matches[0];
			return TRUE;
		}
		return FALSE;
	}

	/**
	 * Parametry mixinu
	 *
	 * @param NULL
	 * @return bool
	 */
	protected function arguments(&$args) {
		$args = array();
		$x = $this->getOffset();

		do {
			$line = $this->getLine();
			if (!$this->argument($name, $value)) {
				if (count($args) == 0) {
					//žádné argumenty
					return TRUE;
				} else {
					//po oddělovači měl následovat další argument
					$this->setOffset($x);
					return FALSE;
				}
			}
			if (array_key_exists($name, $args)) {
				//throw new ParseException("Opakování názvu parametru '$name'");
				$this->throwError("Opakování názvu parametru '$name'", $line);
			}
			//číslo řádky musí probublat kvůli možné chybě ve výchozí hodnotě parametru, viz Compiler::callMixin()
			$args[$name] = array($value, $line);
		} while ($this->char(','));

		return TRUE;
	}

	/**
	 * Parametr s volitelnou výchozí hodnotou
	 *
	 * @param NULL
	 * @param NULL
	 * @return bool
	 */
	protected function argument(&$name, &$value) {
		if ($this->variable($variable, FALSE)) {
			$name = $variable[1];
			$x = $this->getOffset();
			if ($this->char(':') && $this->element($value)) {
				;
			} else {
				$this->setOffset($x);
			}
			return TRUE;
		}
		return FALSE;
	}

	/**
	 * Položka seznamu nebo výchozí hodnota parametru mixinu
	 *
	 * @param NULL
	 * @return array
	 */
	protected function element(&$value) {
		return $this->expression($value) || $this->keyword($value) || $this->color($value) || $this->map($value);
	}

	/**
	 * Seznam položek oddělených mezerou
	 *
	 * @param NULL
	 * @return array
	 */
	protected function spaceList(&$list) {
		$list = array('list');
		while ($this->element($value)) {
			$list[] = $value;
		}
		if (count($list) == 1) {
			return FALSE;
		}
		if (count($list) == 2) {
			$list = $list[1];
		}
		return TRUE;
	}

	/**
	 * Seznam položek oddělených čárkou
	 *
	 * @param NULL
	 * @return bool
	 */
	protected function commaList(&$list) {
		$x = $this->getOffset();
		$list = array('args');
		while ($this->spaceList($value)) {
			$list[] = $value;
			if (!$this->char(',')) {
				break;
			}
		}
		if (count($list) == 1) {
			$this->setOffset($x);
			return FALSE;
		}
		return TRUE;
	}

	/**
	 * Výraz
	 *
	 * @param NULL
	 * @return bool
	 */
	protected function expression(&$expr) {
		// úroveň závorek
		$parens = 0;
		// TRUE - operand, počáteční závorka
		// FALSE - binární operátor, koncová závorka
		$next = TRUE;
		$expr = array('expression');

		$x = $this->getOffset();

		while (TRUE) {
			$offset = $this->getOffset();
			if ($next && $this->unaryOperator($value)) {
				$expr[] = $value;
			}
			if ($next && $this->char('(')) {
				++$parens;
				$expr[] = '(';
				continue;
			} elseif ($next && $this->operand($value)) {
				$expr[] = $value;
				$next = FALSE;
				continue;
			} elseif (!$next && $parens > 0 && $this->char(')')) {
				--$parens;
				$expr[] = ')';
				continue;
			} elseif (!$next && $this->binaryOperator($value)) {
				$x = $offset;
				$expr[] = $value;
				$next = TRUE;
				continue;
			}
			break;
		}

		//pokud poslední je operátor, tak jej vypustíme, ten znak může znamenat něco jiného
		if ($next && count($expr) > 1) {
			array_pop($expr);
			$this->setOffset($x);
		}

		if ($parens > 0 || count($expr) == 1) {
			$expr = NULL;
			return FALSE;
		}
		if (count($expr) == 2) {
			$expr = $expr[1];
		}
		return TRUE;
	}

	/**
	 * Unární operátor ve výrazu
	 *
	 * @param NULL
	 * @return bool
	 */
	protected function unaryOperator(&$operator) {
		$x = $this->getOffset();
		foreach (Compiler::$unaryOperators as $op => $space) {
			//pořadí podmínek je důležité, nejdříve operátor, poté případné mezery bez ohledu na to, zda jsou vyžadovány
			if ($this->char($op) && ($this->whitespace() || !$space || preg_match('/[^a-z]/', $this->buffer[$this->getOffset()]))) {
				$operator = array('unary', $op);
				return TRUE;
			} else {
				$this->setOffset($x);
			}
		}
		return FALSE;
	}

	/**
	 * Binární operátor ve výrazu
	 *
	 * @param NULL
	 * @return bool
	 */
	protected function binaryOperator(&$operator) {
		$x = $this->getOffset();
		foreach (Compiler::$binaryOperators as $op => $_) {
			$space = preg_match('/\\s/', $this->buffer[$this->getOffset() - 1]);
			if ($this->char($op, FALSE) && $this->whitespace() == $space) {
				$operator = array('binary', $op);
				return TRUE;
			} else {
				$this->setOffset($x);
			}
		}
		return FALSE;
	}

	/**
	 * Operand ve výrazu
	 *
	 * @param NULL
	 * @return bool
	 */
	protected function operand(&$operand) {
		// číslo nebo jednotka
		if ($this->unit($operand)) return TRUE;
		// boolean
		if ($this->bool($operand)) return TRUE;
		// funkce
		if ($this->func($operand)) return TRUE;
		// proměnná
		if ($this->variable($operand)) return TRUE;
		// řetězec
		if ($this->string($operand)) return TRUE;
		// accessor
		//if ($this->accessor($operand)) return TRUE;

		return FALSE;
	}

	/**
	 * Číslo s jednotkou nebo bez
	 *
	 * @param NULL
	 * @return bool
	 */
	protected function unit(&$unit) {
		if (!$this->match('[0-9]*\.?[0-9]+', $matches, FALSE)) {
			return FALSE;
		}
		$unit = array('unit', $matches[0]);
		$x = $this->getOffset();
		foreach (Compiler::$units as $value) {
			//pokud za jednotkou následuje písmeno, tak se nejedná o jednotku
			if ($this->char($value, FALSE) && preg_match('/[^a-zA-Z]/', $this->buffer[$this->getOffset()])) {
				$unit[] = $value;
				break;
			} else {
				$this->setOffset($x);
			}
		}
		$this->whitespace();
		return TRUE;
	}

	/**
	 * Boolean
	 *
	 * @param NULL
	 * @return bool
	 */
	protected function bool(&$bool) {
		$x = $this->getOffset();
		if ($this->keyword($keyword)) {
			$string = strtolower($keyword[1]);
			if ($string == 'true' || $string == 'false') {
				$bool = array('bool', $string == 'true');
				return TRUE;
			}
		}
		$this->setOffset($x);
		return FALSE;
	}

	/**
	 * Klíčové slovo
	 *
	 * @param NULL
	 * @return bool
	 */
	protected function keyword(&$keyword) {
		//TODO: musí zde být '(?: ... )' ?
		if ($this->match('-?[a-z]+[a-z-]*|!important', $matches)) {
			$keyword = array('keyword', $matches[0]);
			return TRUE;
		}
		return FALSE;
	}

	/**
	 * Barva
	 *
	 * @param NULL
	 * @return bool
	 */
	protected function color(&$color) {
		$color = array('color');
		if ($this->match('#(?:(?:(?:[0-9a-f]{3})?[0-9a-f]{3}-[0-9]{2})|(?:[0-9a-f]{8})|(?:[0-9a-f]{6})|(?:[0-9a-f]{2,4}))', $matches)) {
			$code = substr($matches[0], 1);
			$length = strlen($code);
			if ($length == 2) {
				$code = $code . $code . $code;
				$length = 6;
			}
			if ($length == 6 && !strpos($code, '-') || $length == 8 || $length == 9) {
				foreach (str_split(substr($code, 0, 6), 2) as $key => $value) {
					$color[$key + 1] = hexdec($value);
				}
			} else {
				foreach (str_split(substr($code, 0, 3)) as $key => $value) {
					$color[$key + 1] = hexdec($value . $value);
				}
			}
			if (strpos($code, '-')) {
				$color[4] = (float) ('0.' . substr($code, -2));
			} elseif ($length == 8) {
				$color[4] = round(hexdec(substr($code, -2)) / 256, 2);
			} elseif ($length == 4) {
				$code = substr($code, -1);
				$color[4] = round(hexdec($code . $code) / 256, 2);
			} else {
				$color[4] = 1;
			}
			return TRUE;
		}
		return FALSE;
	}

	/**
	 * Asociativní pole
	 *
	 * @param NULL
	 * @return bool
	 */
	protected function map(&$map) {
		$x = $this->getOffset();
		if ($this->char('[') && $this->mapElements($elements) && $this->char(']')) {
			$map = array('rawmap', $elements);
			return TRUE;
		}
		$this->setOffset($x);
		return FALSE;
	}

	/**
	 * Prvky asociativního pole
	 *
	 * @param NULL
	 * @return bool
	 */
	protected function mapElements(&$elements) {
		$elements = array();
		while ($this->mapElement($key, $value)) {
			$elements[] = array($key, $value);
			if (!$this->char(',')) {
				break;
			}
		}
		//prvků může být 0
		return TRUE;
	}

	/**
	 * Prvek asociativního pole
	 *
	 * @param NULL
	 * @param NULL
	 * @return bool
	 */
	protected function mapElement(&$key, &$value) {
		$x = $this->getOffset();
		if ($this->key($key) && $this->spaceList($value)) {
			return TRUE;
		}
		$this->setOffset($x);
		return FALSE;
	}

	/**
	 * Klíč prvku v asociativním poli
	 *
	 * @param NULL
	 * @return bool
	 */
	protected function key(&$key) {
		$x = $this->getOffset();
		if ($this->expression($key) && $this->char(':')) {
			return TRUE;
		}
		$this->setOffset($x);
		$key = array('autokey');
		return TRUE;
	}

	/**
	 * Funkce
	 *
	 * @param NULL
	 * @return bool
	 */
	protected function func(&$func) {
		$x = $this->getOffset();
		if ($this->name($name) && $this->char('(') && ($this->commaList($args) || TRUE) && $this->char(')')) {
			array_shift($args);
			$func = array('function', $name, $args);
			return TRUE;
		}
		$this->setOffset($x);
		return FALSE;
	}

	/**
	 * Proměnná
	 *
	 * @param NULL
	 * @param bool
	 * @return bool
	 */
	protected function variable(&$variable, $map = TRUE) {
		$x = $this->getOffset();
		if ($this->char(Compiler::$prefixes['variable']) && $this->name($name)) {
			//name nepolyká bílé znaky
			$this->whitespace();
			if ($map && $this->index($index)) {
				$variable = array('variable', $name, $index);
			} else {
				$variable = array('variable', $name);
			}
			return TRUE;
		}
		$this->setOffset($x);
		return FALSE;
	}

	/**
	 * Index v poli
	 *
	 * @param NULL
	 * @return bool
	 */
	protected function index(&$index) {
		$x = $this->getOffset();
		if ($this->char('[') && $this->expression($index) && $this->char(']')) {
			return TRUE;
		}
		$this->setOffset($x);
		return FALSE;
	}

	/**
	 * Řetězec
	 *
	 * @param NULL
	 * @return bool
	 */
	protected function string(&$string) {
		if ($this->match(static::RE_STRING, $matches)) {
			$match = $matches[0];
			if ($match[0] == '"') {
				$match = "'" . strtr(substr($match, 1, -1), array('\\"' => '"', '\'' => '\\\'', '\\' => '\\\\')) . "'";
				if (!preg_match('/^' . static::RE_STRING . '$/D', $match)) {
					throw new \Exception("Chyba při parsování double-quoted řetězce");
				}
			}
			$string = array('string', $match);
			return TRUE;
		}
		return FALSE;
	}

	/**
	 * Accessor
	 *
	 * @param NULL
	 * @return bool
	 */
	protected function accessor(&$accessor) {
		// TODO
		// nenalezený selektor by měl způsobit chybu - jinak se těžko odhalí např. 2 mezery místo 1
	}

	/**
	 * Konec deklarace přiřazení
	 *
	 * @return bool
	 */
	protected function end() {
		return $this->char(';');
	}

	/**
	 * Parsuje vždy aktuální část z bufferu, podle priority zkouší všechny možnosti
	 *
	 * @return bool
	 */
	protected function parseNext() {
		$x = $this->getOffset();
		foreach (self::$pieces as $piece) {
			if ($this->{$piece}()) {
				return TRUE;
			} else {
				$this->setOffset($x);
			}
		}
		return FALSE;
	}

	/**
	 * Ostranění komentářů
	 *
	 * @param string
	 * @return string
	 */
	protected function removeComments($text) {
		$look = array(
			'//', '/*', '"', "'"
		);

		$out = '';
		while (TRUE) {
			$min = NULL;
			foreach ($look as $token) {
				$pos = strpos($text, $token);
				if ($pos !== FALSE && (!isset($min) || $pos < $min[1])) {
					$min = array($token, $pos);
				}
			}

			if (is_null($min)) {
				break;
			}

			$count = $min[1];
			$skip = 0;
			$newlines = 0;
			switch ($min[0]) {
				case '"':
				case "'":
					if (preg_match('/' . static::RE_STRING . '/', $text, $m, 0, $count - 1)) {
						$count += strlen($m[0]) - 1;
					}
					break;
				case '//':
					$skip = strpos($text, "\n", $count);
					if ($skip === FALSE) {
						$skip = strlen($text) - $count;
					} else {
						$skip -= $count;
					}
					break;
				case '/*':
					if (preg_match('/\/\*.*?\*\//s', $text, $m, 0, $count)) {
						$skip = strlen($m[0]);
						$newlines = substr_count($m[0], "\n");
					}
					break;
			}

			if ($skip == 0) {
				$count += strlen($min[0]);
			}

			$out .= substr($text, 0, $count) . str_repeat("\n", $newlines);
			$text = substr($text, $count + $skip);
		}

		return $out . $text;
	}

	/**
	 * @internal
	 */
	private function _topBuffer() {
		echo '<pre>';
		echo substr($this->buffer, $this->maxOffset);
	}

	/**
	 * @internal
	 */
	private function _actualBuffer() {
		echo '<pre>';
		echo substr($this->buffer, $this->offset);
	}

}