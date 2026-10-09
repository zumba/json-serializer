<?php

namespace Zumba\JsonSerializer\Test;

use PHPUnit\Framework\TestCase;
use stdClass;
use Zumba\JsonSerializer\JsonSerializer;

final class EnumSerializationTest extends TestCase
{
    /**
     * Serializer instance
     *
     * @var JsonSerializer
     */
    protected $serializer;

    /**
     * Test case setup
     *
     * @before
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Before]
    public function setUpSerializer()
    {
        $customObjectSerializerMap['Zumba\\JsonSerializer\\Test\\SupportClasses\\MyType'] = new \Zumba\JsonSerializer\Test\SupportClasses\MyTypeSerializer();
        $this->serializer = new JsonSerializer(null, $customObjectSerializerMap);
    }

    /**
     * Test serialization of Enums
     *
     * @return void
     */
    public function testSerializeEnums()
    {
        $unitEnum = SupportEnums\MyUnitEnum::Hearts;
        $expected = '{"@type":"Zumba\\\\JsonSerializer\\\\Test\\\\SupportEnums\\\\MyUnitEnum","name":"Hearts"}';
        $this->assertSame($expected, $this->serializer->serialize($unitEnum));

        $backedEnum = SupportEnums\MyBackedEnum::Hearts;
        $expected = '{"@type":"Zumba\\\\JsonSerializer\\\\Test\\\\SupportEnums\\\\MyBackedEnum","name":"Hearts","value":"H"}';
        $this->assertSame($expected, $this->serializer->serialize($backedEnum));

        $intBackedEnum = SupportEnums\MyIntBackedEnum::One;
        $expected = '{"@type":"Zumba\\\\JsonSerializer\\\\Test\\\\SupportEnums\\\\MyIntBackedEnum","name":"One","value":1}';
        $this->assertSame($expected, $this->serializer->serialize($intBackedEnum));
    }

    /**
     * Test serialization of multiple Enums
     *
     * @return void
     */
    public function testSerializeMultipleEnums()
    {
        $obj = new stdClass();
        $obj->enum1 = SupportEnums\MyUnitEnum::Hearts;
        $obj->enum2 = SupportEnums\MyBackedEnum::Hearts;
        $obj->enum3 = SupportEnums\MyIntBackedEnum::One;
        $obj->enum4 = SupportEnums\MyUnitEnum::Hearts;
        $obj->enum5 = SupportEnums\MyBackedEnum::Hearts;
        $obj->enum6 = SupportEnums\MyIntBackedEnum::One;

        $expected = '{"@type":"stdClass","enum1":{"@type":"Zumba\\\\JsonSerializer\\\\Test\\\\SupportEnums\\\\MyUnitEnum","name":"Hearts"},"enum2":{"@type":"Zumba\\\\JsonSerializer\\\\Test\\\\SupportEnums\\\\MyBackedEnum","name":"Hearts","value":"H"},"enum3":{"@type":"Zumba\\\\JsonSerializer\\\\Test\\\\SupportEnums\\\\MyIntBackedEnum","name":"One","value":1},"enum4":{"@type":"@1"},"enum5":{"@type":"@2"},"enum6":{"@type":"@3"}}';
        $this->assertSame($expected, $this->serializer->serialize($obj));
    }

    /**
     * Test unserialization of Enums
     *
     * @return void
     */
    public function testUnserializeEnums()
    {
        $serialized = '{"@type":"Zumba\\\\JsonSerializer\\\\Test\\\\SupportEnums\\\\MyUnitEnum","name":"Hearts"}';
        $obj = $this->serializer->unserialize($serialized);
        $this->assertInstanceOf('Zumba\JsonSerializer\Test\SupportEnums\MyUnitEnum', $obj);
        $this->assertSame(SupportEnums\MyUnitEnum::Hearts, $obj);

        $serialized = '{"@type":"Zumba\\\\JsonSerializer\\\\Test\\\\SupportEnums\\\\MyBackedEnum","name":"Hearts","value":"H"}';
        $obj = $this->serializer->unserialize($serialized);
        $this->assertInstanceOf('Zumba\JsonSerializer\Test\SupportEnums\MyBackedEnum', $obj);
        $this->assertSame(SupportEnums\MyBackedEnum::Hearts, $obj);

        $serialized = '{"@type":"Zumba\\\\JsonSerializer\\\\Test\\\\SupportEnums\\\\MyIntBackedEnum","name":"Two","value":2}';
        $obj = $this->serializer->unserialize($serialized);
        $this->assertInstanceOf('Zumba\JsonSerializer\Test\SupportEnums\MyIntBackedEnum', $obj);
        $this->assertSame(SupportEnums\MyIntBackedEnum::Two, $obj);
        $this->assertSame(SupportEnums\MyIntBackedEnum::Two->value, $obj->value);

        // wrong value of BackedEnum is ignored
        $serialized = '{"@type":"Zumba\\\\JsonSerializer\\\\Test\\\\SupportEnums\\\\MyBackedEnum","name":"Hearts","value":"S"}';
        $obj = $this->serializer->unserialize($serialized);
        $this->assertInstanceOf('Zumba\JsonSerializer\Test\SupportEnums\MyBackedEnum', $obj);
        $this->assertSame(SupportEnums\MyBackedEnum::Hearts, $obj);
        $this->assertSame(SupportEnums\MyBackedEnum::Hearts->value, $obj->value);
    }

    /**
     * Test unserialization of multiple Enums
     *
     * @return void
     */
    public function testUnserializeMultipleEnums()
    {
        $obj = new stdClass();
        $obj->enum1 = SupportEnums\MyUnitEnum::Hearts;
        $obj->enum2 = SupportEnums\MyBackedEnum::Hearts;
        $obj->enum3 = SupportEnums\MyIntBackedEnum::One;
        $obj->enum4 = SupportEnums\MyUnitEnum::Hearts;
        $obj->enum5 = SupportEnums\MyBackedEnum::Hearts;
        $obj->enum6 = SupportEnums\MyIntBackedEnum::One;

        $serialized = '{"@type":"stdClass","enum1":{"@type":"Zumba\\\\JsonSerializer\\\\Test\\\\SupportEnums\\\\MyUnitEnum","name":"Hearts"},"enum2":{"@type":"Zumba\\\\JsonSerializer\\\\Test\\\\SupportEnums\\\\MyBackedEnum","name":"Hearts","value":"H"},"enum3":{"@type":"Zumba\\\\JsonSerializer\\\\Test\\\\SupportEnums\\\\MyIntBackedEnum","name":"One","value":1},"enum4":{"@type":"@1"},"enum5":{"@type":"@2"},"enum6":{"@type":"@3"}}';
        $actualObj = $this->serializer->unserialize($serialized);
        $this->assertInstanceOf('stdClass', $actualObj);
        $this->assertEquals($obj, $actualObj);
    }

    /**
     * Test unserialization of wrong UnitEnum
     *
     * @return void
     */
    public function testUnserializeWrongUnitEnum()  {
        // bad case generate Error
        $serialized = '{"@type":"Zumba\\\\JsonSerializer\\\\Test\\\\SupportEnums\\\\MyUnitEnum","name":"Circles"}';
        $this->expectException(\Error::class);
        $this->serializer->unserialize($serialized);
    }

    /**
     * Test unserialization of wrong BackedEnum
     *
     * @return void
     */
    public function testUnserializeWrongBackedEnum()  {
        // bad case generate Error
        $serialized = '{"@type":"Zumba\\\\JsonSerializer\\\\Test\\\\SupportEnums\\\\MyBackedEnum","name":"Circles","value":"C"}';
        $this->expectException(\Error::class);
        $this->serializer->unserialize($serialized);
    }
}
