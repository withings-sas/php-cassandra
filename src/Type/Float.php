<?php
namespace Cassandra\Type;

class Float extends Base{
	public function __construct($value){
		if (!is_float($value)) throw new Exception('Incoming value must be of type double.');
	
		$this->_value = $value;
	}
	
	public function getBinary(){
		return strrev(pack('f', $this->_value));
	}
}
