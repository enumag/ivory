<?php

namespace Tests;

use \PHPUnit_Framework_TestCase;

use \Ivory\Compiler;
use \Ivory\CompileException;

class IvoryTest extends PHPUnit_Framework_TestCase {

	protected $ivory;

	public function setUp() {
		parent::setUp();
		$this->ivory = new \Ivory\Compiler();
		$this->ivory->outputDirectory = __DIR__ . '/output';
		file_put_contents($this->ivory->outputDirectory . '/exceptions.txt', '');
	}

	protected function assertCompilerOutput($input, $expected = NULL) {
		if ($expected === NULL) {
			$expected = $input;
		}
		try {
			$output = $this->ivory->compileFile(__DIR__ . '/files/' . $input . '.iss');
			$this->assertSame(str_replace("\r\n", "\n", file_get_contents(__DIR__ . '/files/' . $expected . '.css')), str_replace("\r\n", "\n", $output));
		} catch (\Exception $e) {
			file_put_contents($this->ivory->outputDirectory . '/exceptions.txt', $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine(), FILE_APPEND);
			throw new \Exception($e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine());
		}
	}

	public function testComments() {
		$this->assertCompilerOutput('comments');
	}

	public function testSelectors() {
		$this->assertCompilerOutput('selectors');
	}

	public function testExpressions() {
		$this->assertCompilerOutput('expressions');
	}

	public function testMapAccessVariable() {
		$this->assertCompilerOutput('map-access-variable');
	}

	public function testPartialSelectors() {
		$this->assertCompilerOutput('partial-selectors');
	}

	public function testMixin() {
		$this->assertCompilerOutput('mixin');
	}

	public function testMixinUndefinedVariable() {
		try {
			$this->ivory->addVariable('include', array('mixin-undefined-variable', 'mixin-undefined-variable-call'));
			$this->ivory->compileFile(__DIR__ . '/files/include.iss');
		} catch (CompileException $e) {
			$this->assertInstanceOf('\Ivory\CompileException', $e);
			$this->assertSame('mixin-undefined-variable', pathinfo($e->getFile(), PATHINFO_FILENAME));
			$this->assertEquals($e->getLine(), 9);
			return;
		}
		$this->fail();
	}

	public function testExpressionWithUnits() {
		$this->assertCompilerOutput('expression-with-units');
	}

	public function testWhile() {
		$this->assertCompilerOutput('while');
	}

	public function testFor() {
		$this->assertCompilerOutput('for');
	}

}
