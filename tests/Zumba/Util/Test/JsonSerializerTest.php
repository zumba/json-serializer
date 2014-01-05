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

}
