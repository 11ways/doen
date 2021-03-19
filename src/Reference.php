<?php

namespace Elevenways\Doen;

class Reference implements \React\Promise\PromiseInterface {

	private $bridge;
	private $id;
	private $monkey_patches = [];

	/**
	 * Evaluate some code and return a reference
	 *
	 * @author   Jelle De Loecker   <jelle@elevenways.be>
	 * @since    0.1.0
	 * @version  0.1.0
	 *
	 * @param    Doen      $bridge
	 * @param    integer   $id
	 */
	public function __construct($bridge, $id) {
		$this->bridge = $bridge;
		$this->id = $id;
	}

	/**
	 * Magic method intercepter:
	 * automatically calls methods on the JavaScript side
	 * and returns a reference
	 *
	 * @author   Jelle De Loecker   <jelle@elevenways.be>
	 * @since    0.1.0
	 * @version  0.1.0
	 *
	 * @param    string   $name
	 * @param    array    $arguments
	 *
	 * @return   Elevenways\Doen\Reference
	 */
	public function __call($name, $arguments) {

		if (isset($this->monkey_patches[$name])) {
			return $this->monkey_patches[$name](...$arguments);
		}

		return $this->bridge->callRefMethod($this->id, $name, $arguments);
	}

	/**
	 * Execute the callables when this reference resolves
	 * WITH the value of the reference.
	 *
	 * @author   Jelle De Loecker   <jelle@elevenways.be>
	 * @since    0.1.0
	 * @version  0.1.0
	 *
	 * @param    callable|null   $on_fulfilled
	 * @param    callable|null   $on_rejected
	 *
	 * @return   React\Promise\Promise
	 */
	public function getValue(callable $on_fulfilled = null, callable $on_rejected = null) {
		$promise = $this->bridge->getRefValue($this->id);

		if ($on_fulfilled || $on_rejected) {
			$promise->done($on_fulfilled, $on_rejected);
		}

		return $promise;
	}

	/**
	 * Execute the callables when this reference resolves.
	 * This does NOT return the value of the reference!
	 *
	 * This is because most of the time, we only want to wait to act upon
	 * something in JavaScript.
	 *
	 * In the end, we can use `getValue()` to get the actual value
	 *
	 * @author   Jelle De Loecker   <jelle@elevenways.be>
	 * @since    0.1.0
	 * @version  0.1.0
	 *
	 * @param    callable|null   $on_fulfilled
	 * @param    callable|null   $on_rejected
	 *
	 * @return   React\Promise\Promise
	 */
	public function then(callable $on_fulfilled = null, callable $on_rejected = null, callable $onProgress = null) {
		return $this->whenResolved($on_fulfilled, $on_rejected);
	}

	/**
	 * Wait for this reference to resolve
	 *
	 * @author   Jelle De Loecker   <jelle@elevenways.be>
	 * @since    0.1.0
	 * @version  0.1.0
	 *
	 * @param    callable|null   $on_fulfilled
	 * @param    callable|null   $on_rejected
	 *
	 * @return   React\Promise\Promise
	 */
	public function whenResolved(callable $on_fulfilled = null, callable $on_rejected = null) {
		$promise = $this->bridge->awaitRef($this->id);

		if ($on_fulfilled || $on_rejected) {
			$promise->done($on_fulfilled, $on_rejected);
		}

		return $promise;
	}

	/**
	 * Attach a new method to this reference
	 *
	 * @author   Jelle De Loecker   <jelle@elevenways.be>
	 * @since    0.1.1
	 * @version  0.1.1
	 *
	 * @param    string     $name
	 * @param    \Closure   $fnc
	 */
	public function __monkeyPatch(string $name, \Closure $fnc) {

		if (!$fnc) {
			unset($this->monkey_patches[$name]);
			return;
		}

		// Bind the function to this instance (so $this will work)
		$fnc = $fnc->bindTo($this);

		// Register it
		$this->monkey_patches[$name] = $fnc;
	}

	/**
	 * Get the id of this reference
	 *
	 * @author   Jelle De Loecker   <jelle@elevenways.be>
	 * @since    0.1.0
	 * @version  0.1.0
	 *
	 * @return   integer
	 */
	public function __getRefId() {
		return $this->id;
	}

	/**
	 * Inform JavaScript it can remove the value this references
	 *
	 * @author   Jelle De Loecker   <jelle@elevenways.be>
	 * @since    0.1.0
	 * @version  0.1.0
	 */
	public function __destruct() {
		$this->bridge->destroyRef($this->id);
	}
}