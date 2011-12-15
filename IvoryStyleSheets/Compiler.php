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
class Compiler {
    
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
    public $cacheFile;

    /**
     * Výchozí jednotka
     *
     * @var string
     */
    protected $defaultUnit;

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
     * Složky pro hledání souborů
     *
     * @var array
     */
    protected $includePaths;

    /**
     * Funkce
     *
     * @var array
     */
    protected $functions;

    /**
     * Mixiny
     *
     * @var array
     */
    protected $mixins;

    /**
     * Konstruktor
     *
     * @return void
     */
    public function __construct() {
        $this->defaultUnit = '';
        $this->files = new Stack;
        $this->allFiles = array();
        $this->includePaths = array();
        $this->functions = array();
        $this->prepareFunctions();
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
                    //PHP 5.4: return array('raw', $this->stringDecode($value[1]));
                    return array('raw', strtr($value[1], array('\\\\' => '\\', '\\\'' => '\'')));
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
     * Přidá funkci
     *
     * @param string
     * @param callback
     * @return void
     */
    public function addFunction($name, $function) {
        $this->functions[$name] = $function;
    }

    /**
     * Zjistí existenci funkce
     *
     * @param string
     * @return bool
     */
    public function functionExists($name) {
        return array_key_exists($name, $this->functions);
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
     * Kompilace souboru
     *
     * @param string
     * @return string
     */
    public function compileFile($file) {
        $pathinfo = pathinfo($file);
        $this->addIncludePath($pathinfo['dirname']);
        $file = $this->stringEncode($pathinfo['basename']);
        $output = $this->compileString('@include ' . $file . ';');
        if ($this->outputDirectory) {
            $outputFile = $this->outputDirectory . DIRECTORY_SEPARATOR . $pathinfo['filename'] . '.css';
            if (!file_put_contents($outputFile, $output)) {
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

        $parser = new Parser;
        $tree = $parser->parse($input);
        
        //prázdný zásobník
        $this->files->clear();
        $this->allFiles = array();

        //inicializace prázdných polí
        $this->mixins = array();
        $this->reduced = array();
        $this->variables = array();

        //TODO: injektovat proměnné z PHP
        $this->variables[] = array();

        //globální blok
        $this->reduced[] = new Main;

        $this->reduceBlock($tree);

        ob_start();
        foreach ($this->reduced as $block) {
            if ($block instanceof Block) {
                if (count($block->properties) == 0) {
                    //zahoď prázdné bloky
                    continue;
                }
                if ($block instanceof Rule) {
                    echo implode(',' . static::NL, $block->selectors);
                } elseif ($block instanceof FontFace) {
                    echo '@font-face';
                }
                echo ' {' . static::NL;
                foreach ($block->properties as $property) {
                    $value = $this->compileValue($property[2]);
                    if ($property[0] == static::$prefixes['important']) {
                        $value .= ' !important';
                    } elseif ($property[0] == static::$prefixes['raw']) {
                        if ($property[2][0] == 'string') {
                            $value = $this->stringDecode($value);
                        }
                    }
                    echo $property[1] . ': ' . $value . ';' . static::NL;
                }
                echo '}' . static::NL;
            } elseif (is_string($block)) {
                //surové CSS
                echo $block;
            } else {
                throw new \Exception("Neimplementováno");
            }
        }
        setlocale(LC_NUMERIC, $locale);
        return ob_get_clean();
    }
    
    /**
     * Zjistí aktuální soubor
     *
     * @return string
     */
    protected function getFile() {
        return $this->files->isEmpty() ? 'unknown' : $this->files->top();
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
     * Zakóduje řetězec
     *
     * @param string
     * @return string
     */
    protected function stringEncode($string) {
        return '\'' . strtr($string, array('\'' => '\\\'', '\\' => '\\\\')) . '\'';
    }

    /**
     * Dekóduje řetězec
     *
     * @param string
     * @return string
     */
    protected function stringDecode($string) {
        return strtr(substr($string, 1, -1), array('\\\\' => '\\', '\\\'' => '\''));
    }

    /**
     * Zkompiluje jednotku na výstup
     *
     * @param array
     * @return string
     */
    protected function compileUnit(array $unit) {
        if ($unit[1] == 0 || $unit[2] == '#') {
            return;
        } elseif ($unit[2] == '') {
            return $this->defaultUnit;
        } else {
            return $unit[2];
        }
    }

    /**
     * Kompiluje hodnotu na výstup
     *
     * @param array
     * @return string
     */
    protected function compileValue(array $value) {
        switch ($value[0]) {
            case 'unit':
                $number = ltrim(round($value[1], 3), '0');
                return ($number == '' ? 0 : $number) . $this->compileUnit($value);
            case 'args':
                array_shift($value);
                return implode(', ', array_map(array($this, 'compileValue'), $value));
            case 'list':
                array_shift($value);
                return implode(' ', array_map(array($this, 'compileValue'), $value));
            case 'keyword':
                return $value[1];
            case 'color':
                if ($value[4] == 1) {
                    return sprintf("#%02x%02x%02x", $value[1], $value[2], $value[3]);
                }
                return 'rgba(' . $value[1] . ',' . $value[2] . ',' . $value[3] . ',' . $value[4] . ')';
            case 'function':
                return $value[1] . '(' . implode(',', array_map(array($this, 'compileValue'), $value[2])) . ')';
            case 'string':
            case 'raw':
                return $value[1];
            default:
                throw new \Exception("Neimplementováno");
        }
    }

    /**
     * Nahradí proměnné v selektorech jejich hodnotou
     *
     * @todo PHP 5.4
     *
     * @param array
     * @return array
     */
    protected function replaceVariables(array $selectors) {
        foreach ($selectors as &$selector) {
            $selector = preg_replace_callback('/<\\$(-?[\w]+[\w-]*)>/', array($this, 'replaceVariableCallback'), $selector);
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
        return $this->compileValue($this->findVariable($matches[1]));
    }

    /**
     * Kominuje selektory rodičovského bloku s aktuálním
     *
     * @param array
     * @param array
     * @return array
     */
    protected function combineSelectors(array $parent, array $child) {
        $selectors = array();
        foreach ($parent as $outer) {
            foreach ($child as $inner) {
                $selectors[] = $outer .
                        ($inner == '' || $inner[0] == static::SELF_SELECTOR || $outer == '' ? '' : ' ') .
                        ($inner != '' && $inner[0] == static::SELF_SELECTOR ? substr($inner, 1) : $inner);
            }
        }
        return $selectors;
    }

    /**
     * Hledání zkompilovaného bloku
     *
     * @param array
     * @return Rule
     */
    protected function findReduced(array $selectors) {
        foreach ($this->reduced as $block) {
            if ($block instanceof Rule && $selectors == $block->selectors) {
                return $block;
            }
        }
        return $this->reduced[] = new Rule($selectors);
    }

    /**
     * Redukce stromu
     *
     * @param Block
     * @param array
     * @param array
     * @return void
     */
    protected function reduceBlock(Block $block, array $selectors = array(''), array $variables = array()) {
        // kvůli + prefixu nejdříve vše jen ukládat, výpis až poté
        // každý selektor je vypsán tam, kde byl poprvé zmíněn bez ohledu na ostatní výskyty

        //inicializace nové vrstvy proměnných
        if (!$block instanceof Main) {
            $this->variables[] = array();
            foreach ($variables as $variable) {
                try {
                    $this->saveVariable($variable[0], $this->reduceValue($variable[1]));
                } catch (Exception $e) {
                    throw $e->setLine(end($variable));
                }
            }
        }

        if ($block instanceof NestedRule) {
            $selectors = $this->combineSelectors($this->replaceVariables($block->prefixes), $selectors);
            $selectors = $this->combineSelectors($selectors, $this->replaceVariables($block->selectors));
            $reduced = $this->findReduced($selectors);
        } elseif ($block instanceof Mixin) {
            $reduced = $this->findReduced($selectors);
        } else {
            $reduced = new $block;
            $this->reduced[] = $reduced;
        }

        foreach ($block->properties as $property) {
            if (is_array($property)) {
                try {
                    if ($property[0] == static::$prefixes['variable']) {
                        if (count($property) == 5) {//zápis do pole
                            $this->saveToMap($property[1], $this->valueToIndex($property[4]), $this->reduceValue($property[2]));
                        } else {
                            $this->saveVariable($property[1], $this->reduceValue($property[2]));
                        }
                    } elseif ($property[0] == static::$prefixes['mixin']) {
                        //TODO: volat $this->reduceValue($property[2]) ? kvůli možnému použití v calc asi ne
                        $this->callMixin($property[1], $property[2], $selectors);
                    } elseif ($property[0] == static::$prefixes['none'] ||
                            $property[0] == static::$prefixes['important'] ||
                            $property[0] == static::$prefixes['raw']) {
                        if ($reduced instanceof Main || $selectors == array('')) {
                            throw new Exception("Vlastnost nemůže být v globálním bloku");
                        }
                        $reduced->properties[] = array($property[0], $property[1], $this->reduceValue($property[2]));
                    } elseif ($property[0] == static::$prefixes['special'] && $property[1] == 'include') {
                        $value = $this->reduceValue($property[2]);
                        if ($value[0] !== 'string') {
                            throw new Exception("Název includovaného souboru musí být řetězec");
                        }
                        $path = $this->stringDecode($value[1]);
                        $this->callInclude($path, $property[3]);
                    } else {
                        throw new \Exception("Neimplementováno");
                    }
                } catch (Exception $e) {
                    throw $e->setLine((string) end($property));
                }
            } elseif (!$reduced instanceof AtRule && $property instanceof NestedRule) {
                $this->callBlock($property, $selectors);
            } elseif (!$reduced instanceof AtRule && $property instanceof Mixin) {
                if (array_key_exists($property->name, $this->mixins)) {
                    //PHP 5.4: throw (new Exception("Mixin '$property->name' již existuje"))->setLine($property->line);
                    $e = new Exception("Mixin '$property->name' již existuje");
                    $e->setLine($property->line);
                    throw $e;
                }
                $this->mixins[$property->name] = $property;
            } elseif ($property instanceof FontFace) {
                $this->reduceBlock($property);
            } else {
                throw new \Exception("Neimplementováno");
            }
        }
        
        //zrušení nejvyšší vrstvy proměnných
        if (!$block instanceof Main) {
            array_pop($this->variables);
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
                            for ($i = $begin; $i <= $end; $i++) {
                                $variables = array();
                                $variables[] = array($block->statement[1][1], array('unit', $i), $block->statement[1]);
                                $this->reduceBlock($block, $selectors, $variables);
                            }
                        } else {
                            for ($i = $begin; $i >= $end; $i--) {
                                $variables = array();
                                $variables[] = array($block->statement[1][1], array('unit', $i), $block->statement[1]);
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
                throw $e->seLine($block->statement['line']);
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
            //$default[0] - výchozí hodnota
            //$default[1] - číslo řádku
            $args[] = array($key, count($list) > 0 ? array_shift($list) : ($default[0] === NULL ? array('bool', FALSE) : $default[0]), $default[1]);
        }
        $this->reduceBlock($mixin, $selectors, $args);
    }

    /**
     * Vložení souboru
     *
     * @todo media
     *
     * @param string
     * @param string
     * @return void
     */
    protected function callInclude($file, $media) {
        foreach ($this->includePaths as $path) {
            $path .= DIRECTORY_SEPARATOR . $file;
            $extension = pathinfo($file, PATHINFO_EXTENSION);
            if (!is_file($path) || !is_readable($path)) {
                continue;
            } elseif ($extension == 'iss') {
                $this->addFile($path);
                $parser = new Parser();
                try {
                    $tree = $parser->parse(file_get_contents($this->getFile()));
                } catch (Exception $e) {
                    throw $e->setFile($this->getFile());
                }
                //media zatím zahodit, později vložit @media blok
                $this->reduceBlock($tree);
                return;
            } elseif ($extension == 'css') {
                $this->reduced[] = file_get_contents($path);
                return;
            }
        }
        throw new Exception("Soubor '$file' se nepodařilo vložit");
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
     * @return void
     */
    protected function saveVariable($name, $value) {
        foreach ($this->variables as $layer => $variables) {
            foreach ($variables as $key => $_) {
                if ($name == $key) {
                    $this->variables[$layer][$key] = $value;
                    return;
                }
            }
        }
        $this->variables[$layer][$name] = $value;
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
        //TODO: vytvořit pole pokud proměnná neexistuje?
        throw new Exception("Pole '$name' nenalezeno");
    }

    /**
     * Nalezení hodnoty proměnné
     *
     * @param string
     * @return array
     */
    protected function findVariable($name) {
        foreach ($this->variables as $variables) {
            foreach ($variables as $key => $value) {
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
            //řetězec sice kolem sebe má apostrofy, ale to nevadí
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
     * @todo accessors
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
                    if ($type == 'args' && count($type) == 1) {
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
                        $value = $this->findInMap($value[1], $this->valueToIndex($value[2]));
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
        array_shift($expr);

        //převod výrazu do postfixové notace
        $postfix = array();
        $stack = array();
        foreach ($expr as $symbol) {
            if ($symbol == '(') {
                array_push($stack, $symbol);
            } elseif ($symbol == ')') {
                while ($top = array_pop($stack)) {
                    if ($top == '(') {
                        break;
                    }
                    $postfix[] = $top;
                }
            } elseif ($symbol[0] == 'binary' && array_key_exists($symbol[1], self::$binaryOperators)) {
                if (count($stack) == 0) {
                    array_push($stack, $symbol);
                    continue;
                }
                $top = end($stack);
                if ($top == '(' || self::$binaryOperators[$symbol[1]] > self::$binaryOperators[$top[1]]) {
                    array_push($stack, $symbol);
                } else {
                    while ($top != '(' && count($stack) > 0 && self::$binaryOperators[$symbol[1]] <= self::$binaryOperators[$top[1]]) {
                        $postfix[] = array_pop($stack);
                        $top = end($stack);
                    }
                    array_push($stack, $symbol);
                }
            } elseif ($symbol[0] == 'unary' && array_key_exists($symbol[1], self::$unaryOperators)) {
                array_push($stack, $symbol);
            } else {
                $postfix[] = $this->reduceValue($symbol);
            }
        }
        while (count($stack) > 0) {
            $postfix[] = array_pop($stack);
        }

        //vyhodnocení výrazu
        $stack = array();
        foreach ($postfix as $symbol) {
            if ($symbol[0] == 'unary' && array_key_exists($symbol[1], self::$unaryOperators)) {
                if (count($stack) < 1) {
                    throw new Exception("Nedostatek operandů pro unární operátor '$symbol[1]'");
                }
                array_push($stack, $this->evaluateUnaryOperation($symbol[1], array_pop($stack)));
            } elseif ($symbol[0] == 'binary' && array_key_exists($symbol[1], self::$binaryOperators)) {
                if (count($stack) < 2) {
                    throw new Exception("Nedostatek operandů pro binární operátor '$symbol[1]'");
                }
                $value2 = array_pop($stack);
                array_push($stack, $this->evaluateBinaryOperation($symbol[1], array_pop($stack), $value2));
            } else {
                array_push($stack, $symbol);
            }
        }
        if (count($stack) <> 1) {
            throw new Exception("Výsledkem výrazu má být pouze 1 hodnota");
        }

        return array_pop($stack);
    }

    /**
     * Zjistí jednotku výsledku
     *
     * @param array
     * @param array
     * @return string
     */
    protected function getUnit($value1, $value2) {
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
            return $value[1] == 0;
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
            return $this->evaluateBinaryOperation($operator, array('unit', 0), $value);
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
        //unit, color, func, string, keyword, raw
        if (in_array($operator, array('&&', '||', '^^'))) {
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
            }
            return $answer;
        } elseif ($operator == '.' && $value1[0] == 'string' && $value2[0] == 'raw') {
            return array('string', '\'' . substr($value2[1], 1, -1) . substr($this->stringEncode($value2[1]), 1));
        } elseif ($operator == '.' && $value1[0] == 'raw' && $value2[0] == 'string') {
            return array('string', substr($this->stringEncode($value1[1]), 0, -1) . substr($value2[1], 1, -1) . '\'');
        } elseif ($operator == '.' && $value1[0] == 'string' && $value2[0] == 'unit') {
            return array('string', '\'' . substr($value1[1], 1, -1) . $value2[1] . $this->compileUnit($value2) . '\'');
        } elseif ($operator == '.' && $value1[0] == 'unit' && $value2[0] == 'string') {
            return array('string', '\'' . $value1[1] . $this->compileUnit($value1) . substr($value2[1], 1, -1) . '\'');
        } elseif ($operator == '.' && $value1[0] == 'string' && $value2[0] == 'string') {
            return array('string', '\'' . substr($value1[1], 1, -1) . substr($value2[1], 1, -1) . '\'');
        } elseif (array_key_exists($operator, self::$binaryOperators) && $value1[0] == 'unit' && $value2[0] == 'unit') {
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
                    $answer[] = '\'' . $value1[1] . $this->compileUnit($value1) . $value2[1] . $this->compileUnit($value2) . '\'';
                    break;
                case '=':
                    $answer[] = 'bool';
                    $answer[] = $value1[1] == $value2[1];
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