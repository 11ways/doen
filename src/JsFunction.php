<?php

namespace Elevenways\Doen;

/**
 * Class used to represent JavaScript functions
 *
 * @author   Jelle De Loecker   <jelle@elevenways.be>
 * @since    0.1.1
 * @version  0.1.1
 */
class JsFunction implements \JsonSerializable {

	private $code;

	/**
	 * Quickly create a new instance
	 *
	 * @author   Jelle De Loecker   <jelle@elevenways.be>
	 * @since    0.1.1
	 * @version  0.1.1
	 *
	 * @param    string   $code
	 *
	 * @return   static
	 */
	public static function create($code) {
		return new JsFunction($code);
	}

	/**
	 * Construct the Function object
	 *
	 * @author   Jelle De Loecker   <jelle@elevenways.be>
	 * @since    0.1.1
	 * @version  0.1.1
	 *
	 * @param    string   $code
	 */
	public function __construct($code)
	{
		$this->code = $code;
	}

	/**
	 * Returns data to use when being serialized by `json_encode()`
	 *
	 * @author   Jelle De Loecker   <jelle@elevenways.be>
	 * @since    0.1.1
	 * @version  0.1.1
	 *
	 * @return   array
	 */
	public function jsonSerialize() {

		$result = [
			'#'     => 'Doen',
			'#type' => 'function',
			'#data' => $this->code,
		];

		return $result;
	}
}