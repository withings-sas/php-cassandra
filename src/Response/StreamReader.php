<?php
namespace Cassandra\Response;

use Cassandra\Type;

class StreamReader {

	/**
	 * @var string
	 */
	protected $data;
	
	/**
	 * @var int
	 */
	protected $offset = 0;

	/**
	 * Read data from stream.
	 *
	 * @param int $length
	 * @return string
	 */
	protected function read($length) {
		$output = substr($this->data, $this->offset, $length);
		$this->offset += $length;
		return $output;
	}
	
	public function reset(){
		$this->offset = 0;
	}

	/**
	 * Read single character.
	 *
	 * @return int
	 */
	public function readChar() {
		$data = unpack('C', $this->read(1));
		return $data[1];
	}

	/**
	 * Read unsigned short.
	 *
	 * @return int
	 */
	public function readShort() {
		$data = unpack('n', $this->read(2));
		return $data[1];
	}

	/**
	 * Read unsigned int.
	 *
	 * @return int
	 */
	public function readInt() {
		$data = unpack('N', $this->read(4));
		return $data[1];
	}

	/**
	 * Read string.
	 *
	 * @return string
	 */
	public function readString() {
		$data = unpack('n', $this->read(2));
		$length = $data[1];
		return $this->read($length);
	}

	/**
	 * Read long string.
	 *
	 * @return string
	 */
	public function readLongString() {
		$data = unpack('N', $this->read(4));
		$length = $data[1];
		return $this->read($length);
	}

	/**
	 * Read bytes.
	 *
	 * @return string
	 */
	public function readBytes() {
		$data = unpack('N', $this->read(4));
		$length = $data[1];
		if ($length == 4294967295)
			return null;
		return $this->read($length);
	}

	/**
	 * Read uuid.
	 *
	 * @return string
	 */
	public function readUuid() {
		$uuid = '';
		$data = unpack('n8', $this->read(16));

		for ($i = 1; $i <= 8; ++$i) {
			if ($i == 3 || $i == 4 || $i == 5 || $i == 6) {
				$uuid .= '-';
			}
			$uuid .= str_pad(dechex($data[$i]), 4, '0', STR_PAD_LEFT);
		}

		return $uuid;
	}

	/**
	 * Read list.
	 *
	 * @param $valueType
	 * @return array
	 */
	public function readList($valueType) {
		$list = array();
		$count = $this->readInt();
		for ($i = 0; $i < $count; ++$i) {
			$list[] = $this->readBytesAndConvertToType($valueType);
		}
		return $list;
	}

	/**
	 * Read map.
	 *
	 * @param $keyType
	 * @param $valueType
	 * @return array
	 */
	public function readMap($keyType, $valueType) {
		$map = array();
		$count = $this->readInt();
		for ($i = 0; $i < $count; ++$i) {
			$map[$this->readBytesAndConvertToType($keyType)] = $this->readBytesAndConvertToType($valueType);
		}
		return $map;
	}

	/**
	 * Read float.
	 *
	 * @return float
	 */
	public function readFloat() {
		$data = unpack('f', strrev($this->read(4)));
		return $data[1];
	}

	/**
	 * Read double.
	 *
	 * @return double
	 */
	public function readDouble() {
		$data = unpack('d', strrev($this->read(8)));
		return $data[1];
	}

	/**
	 * Read boolean.
	 *
	 * @return bool
	 */
	public function readBoolean() {
		return (bool)$this->readChar();
	}

	/**
	 * Read inet.
	 *
	 * @return string
	 */
	public function readInet() {
		return inet_ntop($this->data);
	}

	/**
	 * Read variable length integer.
	 *
	 * @return string
	 */
	public function readVarint() {
		list($higher, $lower) = array_values(unpack('N2', $this->data));
		return $higher << 32 | $lower;
	}

	/**
	 * Read variable length decimal.
	 *
	 * @return string
	 */
	public function readDecimal() {
		$scale = $this->readInt();
		$value = $this->readVarint();
		$len = strlen($value);
		return substr($value, 0, $len - $scale) . '.' . substr($value, $len - $scale);
	}
	
	public function readStringMultimap(){
		$map = array();
		$count = $this->readShort();
		for($i = 0; $i < $count; $i++){
			$key = $this->readString();
				
			$listLength = $this->readShort();
			$list = array();
			for($j = 0; $j < $listLength; $j++)
				$list[] = $this->readString();
					
			$map[$key] = $list;
		}
		return $map;
	}

	/**
	 * read a [bytes] and read by type
	 *
	 * @param int|array $type
	 * @return mixed
	 */
	public function readBytesAndConvertToType($type){
		$unpacked = unpack('N', substr($this->data, $this->offset, 4));
		$length = $unpacked[1];
		$this->offset += 4;

		if ($length === 0xffffffff)
			return null;

		// do not use $this->read() for performance
		$data = substr($this->data, $this->offset, $length);
		$this->offset += $length;

		switch ($type) {
			case Type\Base::ASCII:
			case Type\Base::VARCHAR:
			case Type\Base::TEXT:
				return $data;
			case Type\Base::VARINT:
				$value = 0;
				foreach (unpack('C*', $data) as $byte)
					$value = $value << 8 | $byte;
				$shift = (8 - $length) << 3;
				return $value << $shift >> $shift;
			case Type\Base::BIGINT:
			case Type\Base::COUNTER:
			case Type\Base::TIMESTAMP:	//	use big int to present microseconds timestamp
				$unpacked = unpack('N2', $data);
				return $unpacked[1] << 32 | $unpacked[2];
			case Type\Base::BLOB:
				$unpacked = unpack('N', substr($data, 0, 4));
				$length = $unpacked[1];
				return substr($data, 4, $length);
			case Type\Base::BOOLEAN:
				$unpacked = unpack('C', $data);
				return (bool) $unpacked[1];
			case Type\Base::DECIMAL:
				$unpacked = unpack('N1scale/C*', $data);
				$valueByteLen = $length - 4;
				$value = 0;
				for ($i = 1; $i <= $valueByteLen; ++$i)
					$value = $value << 8 | $unpacked[$i];
				$shift = (8 - $valueByteLen) << 3;
				$value = $value << $shift >> $shift;
				$valueIntLen = strlen($value) - $unpacked['scale'];
				return (double)(substr($value, 0, $valueIntLen) . '.' . substr($value, $valueIntLen));
			case Type\Base::DOUBLE:
				$unpacked = unpack('d', strrev($data));
				return $unpacked[1];
			case Type\Base::FLOAT:
				$unpacked = unpack('f', strrev($data));
				return $unpacked[1];
			case Type\Base::INT:
				$unpacked = unpack('N', $data);
				return $unpacked[1] << 32 >> 32;
			case Type\Base::UUID:
			case Type\Base::TIMEUUID:
				$uuid = '';
				$unpacked = unpack('n8', $data);

				for ($i = 1; $i <= 8; ++$i) {
					if ($i == 3 || $i == 4 || $i == 5 || $i == 6) {
						$uuid .= '-';
					}
					$uuid .= str_pad(dechex($unpacked[$i]), 4, '0', STR_PAD_LEFT);
				}
				return $uuid;
			case Type\Base::INET:
				return inet_ntop($data);
			default:
				if (is_array($type)){
					switch($type['type']){
						case Type\Base::COLLECTION_LIST:
						case Type\Base::COLLECTION_SET:
							$dataStream = new DataStream($data);
							return $dataStream->readList($type['value']);
						case Type\Base::COLLECTION_MAP:
							$dataStream = new DataStream($data);
							return $dataStream->readMap($type['key'], $type['value']);
						case Type\Base::UDT:
							throw new Exception('Unsupported Type UDT.');
						case Type\Base::TUPLE:
							throw new Exception('Unsupported Type Tuple.');
						case Type\Base::CUSTOM:
						default:
							$unpacked = unpack('N', substr($data, 0, 4));
							$length = $unpacked[1];
							return substr($data, 4, $length);
					}
				}

				trigger_error('Unknown type ' . var_export($type, true));
				return null;
		}
	}
}
