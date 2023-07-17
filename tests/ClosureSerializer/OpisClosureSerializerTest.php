<?php

namespace Zumba\JsonSerializer\Test\ClosureSerializer;

use PHPUnit\Framework\TestCase;
use Zumba\JsonSerializer\ClosureSerializer\OpisClosureSerializer;

class OpisClosureSerializerTest extends TestCase
{
    public function testSerialize() {
        $closure = function() {
            return 'foo';
        };
        $serializer = new OpisClosureSerializer();
        $serialized = $serializer->serialize($closure);
        $this->assertNotEmpty($serialized);
        $this->assertTrue(is_string($serialized));
        $this->assertNotEquals($closure, $serialized);
    }

    public function testUnserialize() {
        $closure = function() {
            return 'foo';
        };
        $serializer = new OpisClosureSerializer();
        $serialized = $serializer->serialize($closure);
        $unserialized = $serializer->unserialize($serialized);
        $this->assertNotEmpty($unserialized);
        $this->assertTrue($unserialized instanceof \Closure);
        $this->assertEquals($closure(), $unserialized());
    }
}
