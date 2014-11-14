<?php
namespace Cassandra\Response;
use Cassandra\Protocol\Frame;

class Authenticate extends Response {
	public function getData(){
		$data = unpack('n', $this->data);
		return $data[1];
	}
}
