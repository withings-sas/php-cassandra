<?php
namespace Cassandra\Response;
use Cassandra\Protocol\Frame;


class Response extends StreamReader {
	const FLAG_COMPRESSION = 0x01;
	const FLAG_TRACING = 0x02;
	
	const OPCODE_ERROR = 0x00;
	const OPCODE_STARTUP = 0x01;
	const OPCODE_READY = 0x02;
	const OPCODE_AUTHENTICATE = 0x03;
	const OPCODE_CREDENTIALS = 0x04;
	const OPCODE_OPTIONS = 0x05;
	const OPCODE_SUPPORTED = 0x06;
	const OPCODE_QUERY = 0x07;
	const OPCODE_RESULT = 0x08;
	const OPCODE_PREPARE = 0x09;
	const OPCODE_EXECUTE = 0x0A;
	const OPCODE_REGISTER = 0x0B;
	const OPCODE_EVENT = 0x0C;
	const OPCODE_BATCH = 0x0D;
	const OPCODE_AUTH_CHALLENGE = 0x0E;
	const OPCODE_AUTH_RESPONSE = 0x0F;
	const OPCODE_AUTH_SUCCESS = 0x10;

	/**
	 * @var array
	 */
	protected $_header;
	
	/**
	 * 
	 * @param array $header
	 * @param $body
	 */
	public function __construct($header, $body){
		$this->_header = $header;
		
		$this->data = $body;
	}
	
	public function getVersion(){
		return $this->_header['version'];
	}
	
	public function getFlags(){
		return $this->_header['flags'];
	}
	
	public function getStream(){
		return $this->_header['stream'];
	}
	
	public function getOpcode(){
		return $this->_header['opcode'];
	}
	
	public function getBody(){
		return $this->data;
	}
}
