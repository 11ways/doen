<?php

use React\Promise\Promise;
use React\Promise\Deferred;

class Test_Doen extends DoenTestCase {

	public function test_creating_instance() {
		$doen = $this->getDoenInstance();
		$this->assertNotNull($doen);
	}

	public function test_evaluate() {

		$doen = $this->getDoenInstance();

		$promise = $doen->evaluate('1 + 1');
		$result = $this->waitForPromise($promise);

		$this->assertSame(2, $result);
	}

	public function test_evaluateFunction() {
		$doen = $this->getDoenInstance();

		$fnc = 'function(a,b){return a * b}';

		$promise = $doen->evaluateFunction($fnc, [2, 3]);
		$result = $this->waitForPromise($promise);

		$this->assertSame(6, $result);
	}

	public function test_evaluateToRef() {

		$doen = $this->getDoenInstance();

		$libpath = $doen->evaluateToRef('require("path")');

		$this->assertTrue(is_int($libpath->__getRefId()));

		$str = $libpath->join('a', 'b', 'c');

		$str = $this->waitForPromise($str->getValue());

		$this->assertSame('a/b/c', $str);
	}

	public function test_require() {

		$doen = $this->getDoenInstance();

		$libpath = $doen->require('path');

		$this->assertTrue(is_int($libpath->__getRefId()));

		$str = $libpath->join('a', 'b', 'c');

		$str = $this->waitForPromise($str->getValue());

		$this->assertSame('a/b/c', $str);
	}

	public function test_reference_methods() {

		$doen = $this->getDoenInstance();

		$type = $doen->evaluateToRef('typeof ""');
		$upper = $type->toUpperCase();
		$lower = $upper->toLowerCase();

		$value = $this->waitForPromise($upper->getValue());
		$this->assertSame('STRING', $value);

		$value = $this->waitForPromise($lower->getValue());
		$this->assertSame('string', $value);
	}

	public function test_reference_properties() {

		$doen = $this->getDoenInstance();

		$obj = $doen->evaluateToRef('({a: {b: 1}})');
		$a = $obj->a;
		$b = $a->b;

		$value = $this->waitForPromise($b->getValue());
		$this->assertSame(1, $value);
	}

	public function test_reference_types() {

		$doen = $this->getDoenInstance();

		$bool = $doen->evaluateToRef('false');
		$number = $doen->evaluateToRef('1');

		$bool_type = $this->waitForPromise($bool);
		$number_type = $this->waitForPromise($number);

		$this->assertSame('boolean', $bool_type);
		$this->assertSame('number', $number_type);

		$object = $doen->evaluateToRef('({a: 1})');
		$object_type = $this->waitForPromise($object);
		$this->assertSame('Object', $object_type);

		$array = $doen->evaluateToRef('[1, 2]');
		$array_type = $this->waitForPromise($array);
		$this->assertSame('Array', $array_type);

		$deferred = new Deferred();

		$bool->getValue(function($val) use ($deferred) {
			$deferred->resolve($val);
		});

		$result = $this->waitForPromise($deferred->promise());

		$this->assertSame(false, $result);
	}

	public function test_references_as_arguments() {

		$doen = $this->getDoenInstance();

		$number = $doen->evaluateToRef('1');
		$str = $doen->evaluateToRef('"my string"');

		$nr_type = $doen->evaluateFunction('arg => typeof arg', [$number]);
		$result = $this->waitForPromise($nr_type);
		$this->assertSame('number', $result);

		$upper = $doen->evaluateFunction('arg => arg.toUpperCase()', [$str]);
		$result = $this->waitForPromise($upper);
		$this->assertSame('MY STRING', $result);
	}

	public function test_function_variables() {

		$doen = $this->getDoenInstance();

		$args = [
			\Elevenways\Doen\JsFunction::create('val => 1'),
		];

		$result = $doen->evaluateFunction('function(fnc) {return typeof fnc}', $args);
		$result = $this->waitForPromise($result);

		$this->assertSame('function', $result);
	}

	public function test_long_output() {

		$doen = $this->getDoenInstance();

		$args = [
			'This is a string that should be repeated a lot!',
			100,
		];

		$fnc = 'function(str, times) {
			let result = "",
			    i;

			for (i = 0; i < times; i++) {
				result += str;
			}

			return result;
		}';

		$string = $doen->evaluateFunction($fnc, $args);
		$again = $doen->evaluateFunction($fnc, $args);
		$small = $doen->evaluateFunction($fnc, ['A', 5]);
		$small_again = $doen->evaluateFunction($fnc, ['A', 5]);

		$result = $this->waitForPromise($string);
		$this->assertSame(4700, strlen($result));

		$result = $this->waitForPromise($again);
		$this->assertSame(4700, strlen($result));

		$result = $this->waitForPromise($small);
		$this->assertSame('AAAAA', $result);

		$result = $this->waitForPromise($small_again);
		$this->assertSame('AAAAA', $result);

	}

	public function test_getNonsense() {

		$doen = $this->getDoenInstance();

		$this->assertSame(0, $doen->getNonsense());

		$promise = $doen->evaluate('console.log("this should be caught\n")');

		$this->waitForPromise($promise);

		$this->assertSame(0, $doen->getNonsense());
	}

	public function test_errors() {

		$doen = $this->getDoenInstance();

		// Evaluations should not be able to access `process`
		$promise = $doen->evaluate('process.stdout.write("whatever\n")');

		$this->assertPromiseRejects($promise);

		$doen->close();

		$error = null;

		try {
			$doen->evaluate('bla');
		} catch (\Exception $e) {
			$error = $e;
		}

		$this->assertTrue(!!$error, 'An exception should have been thrown');

		// Closing it again should not throw anything
		$doen->close();

		// Create a new instance
		$doen = $this->getDoenInstance(true);

		$string_error = $doen->evaluate('throw "not an error"');

		$deferred = new Deferred();

		$string_error->otherwise(function($exception) use ($deferred) {
			$deferred->resolve($exception);
		});

		$result = $this->waitForPromise($deferred->promise());

		$this->assertTrue($result instanceof \Exception);
		$this->assertSame('not an error', $result->getMessage());

		$ref_error = $doen->evaluateToRef('throw "referror"');

		$more_error = $ref_error->shouldThrow();

		$more_deferred = new Deferred();

		$more_error->then(function($val) use ($more_deferred) {
			$more_deferred->resolve($val);
		}, function($exception) use ($more_deferred) {
			$more_deferred->resolve($exception);
		});

		$result = $this->waitForPromise($more_deferred->promise());

		$this->assertSame('referror', $result->getMessage());

		$this->assertPromiseRejects($more_error->getValue());
	}
}