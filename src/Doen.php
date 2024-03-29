<?php

namespace Elevenways\Doen;

use Exception;
use React\ChildProcess\Process;
use React\Promise\Promise;
use React\Promise\Deferred;

class Doen {

	private $loop;
	private $process;

	private $options;
	private $requests = [];
	private $id_counter = 0;
	private $nonsense = 0;
	private $ended = false;

	/**
	 * Construct the Doen instance
	 *
	 * @author   Jelle De Loecker   <jelle@elevenways.be>
	 * @since    0.1.0
	 * @version  0.1.0
	 *
	 * @param    \React\EventLoop\LoopInterface   $loop       The event loop to use
	 * @param    array                            $options
	 */
	public function __construct($loop = null, $options = [])
	{
		$this->loop = $loop;
		$this->options = $options;
		$this->createProcess();
	}

	private function createProcess() {

		// Construct the path to the bridge.js file
		$bridge_js = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'node' . DIRECTORY_SEPARATOR . 'bridge.js';

		$node_path = $this->options['node_path'] ?? 'node';

		$this->process = new Process('exec ' . $node_path . ' ' . $bridge_js);
		$this->process->start($this->loop);

		$buffer = null;

		$this->process->stdout->on('data', function($chunk) use (&$buffer) {

			if ($buffer) {
				$chunk = $buffer . $chunk;
				$buffer = null;
			}

			$pieces = explode("\n", $chunk);
			$count = count($pieces);

			foreach ($pieces as $index => $value) {

				if ($index == $count - 1) {
					if ($value) {
						$buffer = $value;
					}

					return;
				}

				$value = @json_decode($value);
				$this->onResponsePacket($value);
			}
		});

		$this->process->stderr->on('data', function ($chunk) {
			//echo "ERROR: " . $chunk . "\n";
			// Ignore stderr stuff
		});

		$this->process->stdout->on('error', function (\Exception $e) {
			//echo 'error: ' . $e->getMessage();
			// Ignore errors?
		});

		$this->process->on('exit', function($exitCode, $termSignal) {
			$this->ended = true;
		});
	}

	/**
	 * Do a CommonJS require
	 *
	 * @author   Jelle De Loecker   <jelle@elevenways.be>
	 * @since    0.1.0
	 * @version  0.1.0
	 *
	 * @param    string   $name
	 *
	 * @return   \Elevenways\Doen\Reference
	 */
	public function require($name) {
		return $this->evaluateToRef('function(name) {return require(name)}', [$name]);
	}

	/**
	 * Evaluate a piece of code
	 *
	 * @author   Jelle De Loecker   <jelle@elevenways.be>
	 * @since    0.1.0
	 * @version  0.1.1
	 *
	 * @param    string   $code
	 *
	 * @return   Promise
	 */
	public function evaluate($code) {

		$this->assertOpen();

		$id = $this->id_counter++;

		$deferred = new Deferred();

		$this->requests[$id] = $deferred;

		$packet = [
			'code' => $code,
			'id'   => $id,
		];

		$this->sendPacket($packet);

		return $deferred->promise();
	}

	/**
	 * Evaluate a function
	 *
	 * @author   Jelle De Loecker   <jelle@elevenways.be>
	 * @since    0.1.0
	 * @version  0.1.1
	 *
	 * @param    string   $function
	 * @param    array    $args
	 *
	 * @return   Promise
	 */
	public function evaluateFunction($function, $args = []) {

		$this->assertOpen();

		$id = $this->id_counter++;

		$deferred = new Deferred();

		$this->requests[$id] = $deferred;

		$packet = [
			'function' => $function,
			'args'     => $args,
			'id'       => $id,
		];

		$this->sendPacket($packet);

		return $deferred->promise();
	}

	/**
	 * Evaluate some code and return a reference
	 *
	 * @author   Jelle De Loecker   <jelle@elevenways.be>
	 * @since    0.1.0
	 * @version  0.1.1
	 *
	 * @param    string   $code
	 * @param    array    $args
	 *
	 * @return   Reference
	 */
	public function evaluateToRef($code, $args = null) {

		$this->assertOpen();

		$new_id = $this->id_counter++;

		$deferred = new Deferred();

		$this->requests[$new_id] = $deferred;

		$type = 'function';

		if (strpos($code, 'function') !== 0) {
			$type = 'code';
		}

		$packet = [
			'return'    => 'reference',
			$type       => $code,
			'args'      => $args,
			'id'        => $new_id,
		];

		$this->sendPacket($packet);

		$ref = new Reference($this, $new_id);

		return $ref;
	}

	/**
	 * Get the value of a reference
	 *
	 * @author   Jelle De Loecker   <jelle@elevenways.be>
	 * @since    0.1.0
	 * @version  0.1.1
	 *
	 * @param    integer  $ref_id
	 *
	 * @return   Promise
	 */
	public function getRefValue($ref_id) {

		$new_id = $this->id_counter++;

		$deferred = new Deferred();
		$this->requests[$new_id] = $deferred;

		$this->afterResponse($ref_id, function($err, $val) use ($ref_id, $new_id, $deferred) {

			if ($err) {
				return $deferred->reject($err);
			}

			$packet = [
				'reference' => $ref_id,
				'return'    => 'value',
				'id'        => $new_id,
			];

			$this->sendPacket($packet);
		});

		return $deferred->promise();
	}

	/**
	 * Await a reference without getting its value
	 *
	 * @author   Jelle De Loecker   <jelle@elevenways.be>
	 * @since    0.1.0
	 * @version  0.1.0
	 *
	 * @param    integer  $ref_id
	 *
	 * @return   Promise
	 */
	public function awaitRef($ref_id) {

		$deferred = new Deferred();

		$this->afterResponse($ref_id, function($err, $val) use ($ref_id, $deferred) {

			if ($err) {
				return $deferred->reject($err);
			}

			$deferred->resolve($val);
		});

		return $deferred->promise();
	}

	/**
	 * Do the callback once the given id's request resolves
	 *
	 * @author   Jelle De Loecker   <jelle@elevenways.be>
	 * @since    0.1.0
	 * @version  0.1.0
	 *
	 * @param    integer    $ref_id
	 * @param    function   $fnc
	 */
	public function afterResponse($ref_id, $fnc) {

		$this->assertOpen();

		if (!isset($this->requests[$ref_id])) {
			$fnc();
		} else {
			$this->requests[$ref_id]->promise()->then(function($result) use ($ref_id, $fnc) {
				$fnc(null, $result);
			}, function($err) use ($ref_id, $fnc) {
				$fnc($err, null);
			});
		}
	}

	/**
	 * Call a method on a reference
	 *
	 * @author   Jelle De Loecker   <jelle@elevenways.be>
	 * @since    0.1.0
	 * @version  0.1.1
	 *
	 * @param    integer  $ref_id
	 * @param    string   $method
	 * @param    array    $args
	 *
	 * @return   Elevenways\Doen\Reference
	 */
	public function callRefMethod($ref_id, $method, $args = []) {

		$new_id = $this->id_counter++;

		$deferred = new Deferred();

		$this->requests[$new_id] = $deferred;

		$packet = [
			'reference' => $ref_id,
			'method'    => $method,
			'args'      => $args,
			'id'        => $new_id,
		];

		$this->afterResponse($ref_id, function($err, $res) use ($packet, $deferred, $ref_id, $new_id, $method) {

			if ($err) {
				$deferred->reject($err);
				return;
			}

			$this->sendPacket($packet);
		});

		$ref = new Reference($this, $new_id);

		return $ref;
	}

	/**
	 * Get a property
	 *
	 * @author   Jelle De Loecker   <jelle@elevenways.be>
	 * @since    0.1.2
	 * @version  0.1.2
	 *
	 * @param    integer  $ref_id
	 * @param    string   $name
	 *
	 * @return   Elevenways\Doen\Reference
	 */
	public function getRefProperty($ref_id, $name) {

		$new_id = $this->id_counter++;

		$deferred = new Deferred();

		$this->requests[$new_id] = $deferred;

		$packet = [
			'reference' => $ref_id,
			'property'  => $name,
			'id'        => $new_id,
		];

		$this->afterResponse($ref_id, function($err, $res) use ($packet, $deferred, $ref_id, $new_id, $name) {

			if ($err) {
				$deferred->reject($err);
				return;
			}

			$this->sendPacket($packet);
		});

		$ref = new Reference($this, $new_id);

		return $ref;
	}

	

	/**
	 * Destroy a reference
	 *
	 * @author   Jelle De Loecker   <jelle@elevenways.be>
	 * @since    0.1.0
	 * @version  0.1.1
	 *
	 * @param    integer  $ref_id
	 */
	public function destroyRef($ref_id) {

		if ($this->ended) {
			return;
		}

		$this->afterResponse($ref_id, function($err, $res) use ($ref_id) {

			unset($this->requests[$ref_id]);

			if ($err) {
				return;
			}

			$packet = [
				'reference' => $ref_id,
				'destroy'   => true,
			];

			$this->sendPacket($packet);
		});
	}

	/**
	 * Close this process
	 *
	 * @author   Jelle De Loecker   <jelle@elevenways.be>
	 * @since    0.1.0
	 * @version  0.1.0
	 */
	public function close() {

		if ($this->ended) {
			return;
		}

		$this->process->terminate();
		$this->ended = true;
	}

	/**
	 * How much nonsense have we seen?
	 *
	 * @author   Jelle De Loecker   <jelle@elevenways.be>
	 * @since    0.1.0
	 * @version  0.1.0
	 *
	 * @return   integer
	 */
	public function getNonsense() {
		return $this->nonsense;
	}

	/**
	 * Send data to the node script
	 *
	 * @author   Jelle De Loecker   <jelle@elevenways.be>
	 * @since    0.1.1
	 * @version  0.1.1
	 *
	 * @param    object   $packet
	 */
	private function sendPacket($packet) {
		$str = json_encode($packet);
		$this->process->stdin->write($str . "\n");
	}

	/**
	 * Evaluate a piece of code
	 *
	 * @author   Jelle De Loecker   <jelle@elevenways.be>
	 * @since    0.1.0
	 * @version  0.1.3
	 *
	 * @param    object   $packet
	 *
	 * @return   Promise
	 */
	private function onResponsePacket($packet) {

		if ($this->ended) {
			return;
		}

		if (!$packet) {
			$this->nonsense++;
			return;
		}

		$deferred = $this->requests[$packet->id] ?? null;

		if (!$deferred) {
			$this->nonsense++;
			return;
		}

		$error = $packet->error ?? null;

		if ($error) {

			if (is_string($error)) {
				$error = new Exception($error);
			} else {

				if (isset($error->stack)) {
					$message = $error->stack;
				} else {
					$message = $error->message;
				}

				$error = new Exception($message);
			}

			$deferred->reject($error);
		} else {
			$deferred->resolve($packet->result ?? null);
		}
	}

	/**
	 * Throw an error if the process has closed
	 *
	 * @author   Jelle De Loecker   <jelle@elevenways.be>
	 * @since    0.1.0
	 * @version  0.1.0
	 */
	public function assertOpen() {

		if (!$this->ended) {
			return true;
		}

		throw new Exception('Doen\'s Node.js process has been terminated');
	}
}