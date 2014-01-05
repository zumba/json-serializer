<?php

namespace Zumba\Util\Test;

use Zumba\Util\JsonSerializer;

class JsonSerializerTest extends \PHPUnit_Framework_TestCase {

	public function testSerializeScalar() {
		$serializer = new JsonSerializer();

		$this->assertSame('"testing"', $serializer->serialize('testing'));
		$this->assertSame('123', $serializer->serialize(123));
		$this->assertSame('17.2', $serializer->serialize(17.2));
		$this->assertSame('true', $serializer->serialize(true));
		$this->assertSame('false', $serializer->serialize(false));
		$this->assertSame('null', $serializer->serialize(null));
	}

}
