<?php
namespace Cassandra\Response;
use Cassandra\Protocol\Frame;

class DataStream extends StreamReader {
	/**
	 * @param string $binary
	 */
	public function __construct($binary) {
		$this->data = $binary;
	}
}
