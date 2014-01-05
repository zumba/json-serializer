<?php

namespace Zumba\Util\Test;

use Zumba\Util\JsonSerializer;

class JsonSerializerTest extends \PHPUnit_Framework_TestCase {

	/**
	 * Serializer instance
	 *
	 * @var Zumba\Util\JsonSerializer
	 */
	protected $serializer;

	/**
	 * Test case setup
	 *
	 * @return void
	 */
	public function setUp() {
		parent::setUp();
		$this->serializer = new JsonSerializer();
	}

	/**
	 * Test serialization of scalar values
	 *
	 * @dataProvider scalarData
	 * @param mixed $scalar
	 * @param strign $jsoned
	 * @return void
	 */
	public function testSerializeScalar($scalar, $jsoned) {
		$this->assertSame($jsoned, $this->serializer->serialize($scalar));
	}

	/**
	 * Test unserialization of scalar values
	 *
	 * @dataProvider scalarData
	 * @param mixed $scalar
	 * @param strign $jsoned
	 * @return void
	 */
	public function testUnserializeScalar($scalar, $jsoned) {
		$this->assertSame($scalar, $this->serializer->unserialize($jsoned));
	}

	/**
	 * List of scalar data
	 *
	 * @return array
	 */
	public function scalarData() {
		return array(
			array('testing', '"testing"'),
			array(123, '123'),
			array(17.2, '17.2'),
			array(true, 'true'),
			array(false, 'false'),
			array(null, 'null')
		);
	}

	/**
	 * Test the serialization of resources
	 *
	 * @return void
	 */
	public function testSerializeResource() {
		$this->setExpectedException('Exception');
		$this->serializer->serialize(fopen(__FILE__));
	}

	/**
	 * Test serialization of array without objects
	 *
	 * @dataProvider arrayNoObjectData
	 * @param array $array
	 * @param strign $jsoned
	 * @return void
	 */
	public function testSerializeArrayNoObject($array, $jsoned) {
		$this->assertSame($jsoned, $this->serializer->serialize($array));
	}

	/**
	 * Test unserialization of array without objects
	 *
	 * @dataProvider arrayNoObjectData
	 * @param array $array
	 * @param strign $jsoned
	 * @return void
	 */
	public function testUnserializeArrayNoObject($array, $jsoned) {
		$this->assertSame($array, $this->serializer->unserialize($jsoned));
	}

	/**
	 * List of array data
	 *
	 * @return array
	 */
	public function arrayNoObjectData() {
		return array(
			array(array(1, 2, 3), '[1,2,3]'),
			array(array(1, 'abc', false), '[1,"abc",false]'),
			array(array('a' => 1, 'b' => 2, 'c' => 3), '{"a":1,"b":2,"c":3}'),
			array(array('integer' => 1, 'string' => 'abc', 'bool' => false), '{"integer":1,"string":"abc","bool":false}'),
			array(array(1, array('nested')), '[1,["nested"]]'),
			array(array('integer' => 1, 'array' => array('nested')), '{"integer":1,"array":["nested"]}'),
			array(array('integer' => 1, 'array' => array('nested' => 'object')), '{"integer":1,"array":{"nested":"object"}}'),
		);
	}

}
