<?php

/**
 * @package Ivory
 * @license MIT, LGPL, BSD
 * @copyright (c) 2011 Jáchym Toušek
 */

namespace Ivory\StyleSheets;

/**
 * Generátor IvoryStyleSheets
 *
 * @author Jáchym Toušek
 */
class Generator extends Object {

    /**
     * Výchozí jednotka
     *
     * @var string
     */
    protected $defaultUnit;

    /**
     * Vygeneruje CSS
     *
     * @param array
     * @param string
     * @return string
     */
    public function generate(array &$reduced, $defaultUnit) {
        $this->defaultUnit = $defaultUnit;
        ob_start();
        try {
            foreach ($reduced as $block) {
                $this->compileBlock($block);
            }
            return ob_get_clean();
        } catch (\Exception $e) {
            ob_end_clean();
            throw $e;
        }
    }

    /**
     * Zkompiluje blok
     *
     * @param Block|string|array
     * @return void
     */
    protected function compileBlock($block) {
        if ($block instanceof Block) {
            if (count($block->properties) == 0) {
                //zahoď prázdné bloky
                return;
            }
            if ($block instanceof Rule) {
                echo implode(',' . Compiler::NL, $block->selectors);
            } elseif ($block instanceof FontFace) {
                echo '@font-face';
            } elseif ($block instanceof Media) {
                echo '@media ';
                if ($block->media[0] == 'string') {
                    echo Compiler::stringDecode($block->media[1]);
                } elseif ($block->media[0] == 'raw') {
                    echo $block->media[1];
                } else {
                    throw new \Exception("Neimplementováno");
                }
            } else {
                throw new \Exception("Neimplementováno");
            }
            echo ' {' . Compiler::NL;
            foreach ($block->properties as $property) {
                if ($block instanceof Media) {
                    $this->compileBlock($property);
                    continue;
                }
                $value = $this->compileValue($property[2], TRUE);
                if ($property[0] == Compiler::$prefixes['important']) {
                    $value .= ' !important';
                } elseif ($property[0] == Compiler::$prefixes['raw']) {
                    if ($property[2][0] == 'string') {
                        $value = Compiler::stringDecode($value);
                    }
                }
                echo $property[1] . ': ' . $value . ';' . Compiler::NL;
            }
            echo '}' . Compiler::NL;
        } elseif (is_string($block)) {
            //surové CSS
            echo $block;
        } elseif (is_array($block) && $block[0] == 'charset') {
            //@charset
            echo '@charset ';
            if ($block[1][0] == 'string') {
                echo Compiler::stringDecode($block[1][1]);
            } elseif ($block[1][0] == 'raw') {
                echo $block[1][1];
            } else {
                throw new \Exception("Neimplementováno");
            }
            echo ';' . Compiler::NL;
        } else {
            throw new \Exception("Neimplementováno");
        }
    }

    /**
     * Kompiluje hodnotu na výstup
     *
     * @param array
     * @param bool
     * @return string
     */
    public function compileValue(array $value, $useDefault = FALSE) {
        switch ($value[0]) {
            case 'unit':
                $number = ltrim(round($value[1], 3), '0');
                return ($number == '' ? 0 : $number) . $this->compileUnit($value, $useDefault);
            case 'args':
                array_shift($value);
                return implode(', ', array_map(array($this, 'compileValue'), $value, array(TRUE)));
            case 'list':
                array_shift($value);
                return implode(' ', array_map(array($this, 'compileValue'), $value, array(TRUE)));
            case 'keyword':
                return $value[1];
            case 'color':
                if ($value[4] == 1) {
                    return sprintf("#%02x%02x%02x", $value[1], $value[2], $value[3]);
                }
                return 'rgba(' . $value[1] . ',' . $value[2] . ',' . $value[3] . ',' . $value[4] . ')';
            case 'function':
                return $value[1] . '(' . implode(',', array_map(array($this, 'compileValue'), $value[2], array(TRUE))) . ')';
            case 'string':
            case 'raw':
                return $value[1];
            default:
                throw new \Exception("Neimplementováno");
        }
    }

    /**
     * Zkompiluje jednotku na výstup
     *
     * @param array
     * @param bool
     * @return string
     */
    public function compileUnit(array $unit, $useDefault = FALSE) {
        if ($unit[1] == 0 || $unit[2] == '#') {
            return;
        } elseif ($unit[2] == '' && $useDefault) {
            return $this->defaultUnit;
        } else {
            return $unit[2];
        }
    }

}