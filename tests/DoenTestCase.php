<?php

//use PHPUnit\Framework\TestCase;
use seregazhuk\React\PromiseTesting\TestCase;
use React\EventLoop\LoopInterface;
use React\EventLoop\Factory as LoopFactory;

$global_loop = null;
$global_doen = null;

class DoenTestCase extends TestCase {
	protected $defaultWaitTimeout = 2;

	/**
	 * Get a Doen instance
	 *
	 * @author   Jelle De Loecker   <jelle@elevenways.be>
	 * @since    0.1.0
	 * @version  0.1.0
	 *
	 * @param    boolean   $create_new   Set to true to force create a new instance
	 *
	 * @return   \Elevenways\Doen\Doen
	 */
	public function getDoenInstance($create_new = false) {
		global $global_doen;

		if (!$global_doen || $create_new) {
			$global_doen = new \Elevenways\Doen\Doen($this->eventLoop());
		}

		return $global_doen;
	}

	/**
	 * The original PromiseTesting\AssertsPromise trait creates a new eventloop
	 * for each executed test method.
	 * This is not what we want, because the ReactPHP server & Doen-Puppeteer
	 * process require a single loop.
	 * (And it's silly to start & close those on each function test)
	 *
	 * @author   Jelle De Loecker   <jelle@elevenways.be>
	 * @since    0.1.0
	 * @version  0.1.0
	 *
	 * @return   LoopInterface
	 */
	public function eventLoop(): LoopInterface
	{
		global $global_loop;

		if (!$global_loop) {
			$global_loop = LoopFactory::create();
		}

		return $global_loop;
	}

	/**
	 * Do a dummy assert so PHPUnit does not complain
	 *
	 * @author   Jelle De Loecker   <jelle@elevenways.be>
	 * @since    0.1.0
	 * @version  0.1.0
	 */
	protected function pass() {
		$this->assertTrue(true);
	}
}