<?php

namespace Zumba\JsonSerializer\Test;

use Zumba\JsonSerializer\ClosureSerializer;
use Zumba\JsonSerializer\JsonSerializer;
use Zumba\JsonSerializer\Exception\JsonSerializerException;
use stdClass;
use SuperClosure\Serializer as SuperClosureSerializer;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class JsonSerializerTest extends TestCase
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
     * Test serialization of scalar values
     *
     * @dataProvider scalarData
     * @param        mixed  $scalar
     * @param        string $jsoned
     * @return       void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('scalarData')]
    public function testSerializeScalar($scalar, $jsoned)
    {
        $this->assertSame($jsoned, $this->serializer->serialize($scalar));
    }

    /**
     * Test serialization of float values with locale
     *
     * @return void
     */
    public function testSerializeFloatLocalized()
    {
        $possibleLocales = ['fr_FR', 'fr_FR.utf8', 'fr', 'fra', 'French'];
        $originalLocale = setlocale(LC_NUMERIC, 0);
        if (!setlocale(LC_NUMERIC, $possibleLocales)) {
            $this->markTestSkipped('Unable to set an i18n locale.');
        }

        $data = [1.0, 1.1, 0.00000000001, 1.999999999999, 223423.123456789, 1e5, 1e11];
        $expected = version_compare(PHP_VERSION, '7.1', '>=') ?
            '[1.0,1.1,1.0e-11,1.999999999999,223423.123456789,100000.0,100000000000.0]' :
            '[1.0,1.1,1.0e-11,1.999999999999,223423.12345679,100000.0,100000000000.0]';
        $this->assertSame($expected, $this->serializer->serialize($data));

        setlocale(LC_NUMERIC, $originalLocale);
    }
    /**
     * Test unserialization of scalar values
     *
     * @dataProvider scalarData
     * @param        mixed  $scalar
     * @param        string $jsoned
     * @return       void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('scalarData')]
    public function testUnserializeScalar($scalar, $jsoned)
    {
        $this->assertSame($scalar, $this->serializer->unserialize($jsoned));
    }

    /**
     * List of scalar data
     */
    public static function scalarData(): array
    {
        return array(
            array('testing', '"testing"'),
            array(123, '123'),
            array(0, '0'),
            array(0.0, '0.0'),
            array(17.0, '17.0'),
            array(17e1, '170.0'),
            array(17.2, '17.2'),
            array(true, 'true'),
            array(false, 'false'),
            array(null, 'null'),
            // Non UTF8
            array('ßåö', '"ßåö"')
        );
    }

    /**
     * Test the serialization of resources
     *
     * @return void
     */
    public function testSerializeResource()
    {
        $this->expectException(JsonSerializerException::class);
        $this->serializer->serialize(fopen(__FILE__, 'r'));
    }

    /**
     * Test the serialization of closures when not providing closure serializer
     *
     * @return void
     */
    public function testSerializeClosureWithoutSerializer()
    {
        $this->expectException(JsonSerializerException::class);
        $this->serializer->serialize(
            array('func' => function () {
                echo 'whoops';
            })
        );
    }

    /**
     * Test serialization of array without objects
     *
     * @dataProvider arrayNoObjectData
     * @param        array  $array
     * @param        string $jsoned
     * @return       void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('arrayNoObjectData')]
    public function testSerializeArrayNoObject($array, $jsoned)
    {
        $this->assertSame($jsoned, $this->serializer->serialize($array));
    }

    /**
     * Test unserialization of array without objects
     *
     * @dataProvider arrayNoObjectData
     * @param        array  $array
     * @param        string $jsoned
     * @return       void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('arrayNoObjectData')]
    public function testUnserializeArrayNoObject($array, $jsoned)
    {
        $this->assertSame($array, $this->serializer->unserialize($jsoned));
    }

    /**
     * List of array data
     */
    public static function arrayNoObjectData(): array
    {
        return array(
            array(array(1, 2, 3), '[1,2,3]'),
            array(array(1, 'abc', false), '[1,"abc",false]'),
            array(array('a' => 1, 'b' => 2, 'c' => 3), '{"a":1,"b":2,"c":3}'),
            array(array('integer' => 1, 'string' => 'abc', 'bool' => false), '{"integer":1,"string":"abc","bool":false}'),
            array(array(1, array('nested')), '[1,["nested"]]'),
            array(array('integer' => 1, 'array' => array('nested')), '{"integer":1,"array":["nested"]}'),
            array(array('integer' => 1, 'array' => array('nested' => 'object')), '{"integer":1,"array":{"nested":"object"}}'),
            array(array(1.0, 2, 3e1), '[1.0,2,30.0]'),
        );
    }

    /**
     * Test serialization of objects
     *
     * @return void
     */
    public function testSerializeObject()
    {
        $obj = new stdClass();
        $this->assertSame('{"@type":"stdClass"}', $this->serializer->serialize($obj));

        $obj = $empty = new SupportClasses\EmptyClass();
        $this->assertSame('{"@type":"Zumba\\\\JsonSerializer\\\\Test\\\\SupportClasses\\\\EmptyClass"}', $this->serializer->serialize($obj));

        $obj = new SupportClasses\AllVisibilities();
        $expected = '{"@type":"Zumba\\\\JsonSerializer\\\\Test\\\\SupportClasses\\\\AllVisibilities","pub":"this is public","prot":"protected","priv":"dont tell anyone"}';
        $this->assertSame($expected, $this->serializer->serialize($obj));

        $obj->pub = 'new value';
        $expected = '{"@type":"Zumba\\\\JsonSerializer\\\\Test\\\\SupportClasses\\\\AllVisibilities","pub":"new value","prot":"protected","priv":"dont tell anyone"}';
        $this->assertSame($expected, $this->serializer->serialize($obj));

        $obj->pub = $empty;
        $expected = '{"@type":"Zumba\\\\JsonSerializer\\\\Test\\\\SupportClasses\\\\AllVisibilities","pub":{"@type":"Zumba\\\\JsonSerializer\\\\Test\\\\SupportClasses\\\\EmptyClass"},"prot":"protected","priv":"dont tell anyone"}';
        $this->assertSame($expected, $this->serializer->serialize($obj));

        $array = array('instance' => $empty);
        $expected = '{"instance":{"@type":"Zumba\\\\JsonSerializer\\\\Test\\\\SupportClasses\\\\EmptyClass"}}';
        $this->assertSame($expected, $this->serializer->serialize($array));

        $obj = new stdClass();
        $obj->total = 10.0;
        $obj->discount = 0.0;
        $expected = '{"@type":"stdClass","total":10.0,"discount":0.0}';
        $this->assertSame($expected, $this->serializer->serialize($obj));
    }

    /**
     * Test unserialization of objects
     *
     * @return void
     */
    public function testUnserializeObjects()
    {
        $serialized = '{"@type":"stdClass"}';
        $obj = $this->serializer->unserialize($serialized);
        $this->assertInstanceOf('stdClass', $obj);

        $serialized = '{"@type":"Zumba\\\\JsonSerializer\\\\Test\\\\SupportClasses\\\\EmptyClass"}';
        $obj = $this->serializer->unserialize($serialized);
        $this->assertInstanceOf('Zumba\JsonSerializer\Test\SupportClasses\EmptyClass', $obj);

        $serialized = '{"@type":"Zumba\\\\JsonSerializer\\\\Test\\\\SupportClasses\\\\AllVisibilities","pub":{"@type":"Zumba\\\\JsonSerializer\\\\Test\\\\SupportClasses\\\\EmptyClass"},"prot":"protected","priv":"dont tell anyone"}';
        $obj = $this->serializer->unserialize($serialized);
        $this->assertInstanceOf('Zumba\JsonSerializer\Test\SupportClasses\AllVisibilities', $obj);
        $this->assertInstanceOf('Zumba\JsonSerializer\Test\SupportClasses\EmptyClass', $obj->pub);
        $prop = new ReflectionProperty($obj, 'prot');
        $prop->setAccessible(true);
        $this->assertSame('protected', $prop->getValue($obj));
        $prop = new ReflectionProperty($obj, 'priv');
        $prop->setAccessible(true);
        $this->assertSame('dont tell anyone', $prop->getValue($obj));

        $serialized = '{"instance":{"@type":"Zumba\\\\JsonSerializer\\\\Test\\\\SupportClasses\\\\EmptyClass"}}';
        $array = $this->serializer->unserialize($serialized);
        $this->assertTrue(is_array($array));
        $this->assertInstanceOf('Zumba\JsonSerializer\Test\SupportClasses\EmptyClass', $array['instance']);
    }


    /**
     * Test serialization of Enums
     *
     * @return void
     */
    public function testSerializeEnums()
    {
        if (PHP_VERSION_ID < 80100) {
            $this->markTestSkipped("Enums are only available since PHP 8.1");
        }

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
        if (PHP_VERSION_ID < 80100) {
            $this->markTestSkipped("Enums are only available since PHP 8.1");
        }

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
        if (PHP_VERSION_ID < 80100) {
            $this->markTestSkipped("Enums are only available since PHP 8.1");
        }

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
        if (PHP_VERSION_ID < 80100) {
            $this->markTestSkipped("Enums are only available since PHP 8.1");
        }

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
        if (PHP_VERSION_ID < 80100) {
            $this->markTestSkipped("Enums are only available since PHP 8.1");
        }

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
        if (PHP_VERSION_ID < 80100) {
            $this->markTestSkipped("Enums are only available since PHP 8.1");
        }

        // bad case generate Error
        $serialized = '{"@type":"Zumba\\\\JsonSerializer\\\\Test\\\\SupportEnums\\\\MyBackedEnum","name":"Circles","value":"C"}';
        $this->expectException(\Error::class);
        $this->serializer->unserialize($serialized);
    }

    /**
     * Test serialization of objects using the custom serializers
     *
     * @return void
     */
    public function testCustomObjectSerializer()
    {
        $obj = new SupportClasses\MyType();
        $obj->field1 = 'x';
        $obj->field2 = 'y';
        $this->assertSame('{"@type":"Zumba\\\\JsonSerializer\\\\Test\\\\SupportClasses\\\\MyType","fields":"x y"}', $this->serializer->serialize($obj));
    }

    /**
     * Test unserialization of objects using the custom serializers
     *
     * @return void
     */
    public function testCustomObjectsUnserializer()
    {
        $serialized = '{"@type":"Zumba\\\\JsonSerializer\\\\Test\\\\SupportClasses\\\\MyType","fields":"x y"}';
        $obj = $this->serializer->unserialize($serialized);
        $this->assertInstanceOf('Zumba\JsonSerializer\Test\SupportClasses\MyType', $obj);
        $this->assertSame('x', $obj->field1);
        $this->assertSame('y', $obj->field2);
    }

    /**
     * Test magic serialization methods
     *
     * @return void
     */
    public function testSerializationMagicMethods()
    {
        $obj = new SupportClasses\MagicClass();
        $serialized = '{"@type":"Zumba\\\\JsonSerializer\\\\Test\\\\SupportClasses\\\\MagicClass","show":true}';
        $this->assertSame($serialized, $this->serializer->serialize($obj));
        $this->assertFalse($obj->woke);

        $obj = $this->serializer->unserialize($serialized);
        $this->assertTrue($obj->woke);
    }

    /**
     * Test serialization of DateTime classes
     *
     * Some interal classes, such as DateTime, cannot be initialized with
     * ReflectionClass::newInstanceWithoutConstructor()
     *
     * @return void
     */
    public function testSerializationOfDateTime()
    {
        $date = new \DateTime('2014-06-15 12:00:00', new \DateTimeZone('UTC'));
        $obj = $this->serializer->unserialize($this->serializer->serialize($date));
        $this->assertSame($date->getTimestamp(), $obj->getTimestamp());

        $date = new \DateTimeImmutable('2014-06-15 12:00:00', new \DateTimeZone('UTC'));
        $obj = $this->serializer->unserialize($this->serializer->serialize($date));
        $this->assertSame($date->getTimestamp(), $obj->getTimestamp());
    }

    /**
     * Test the serialization of closures providing closure serializer
     *
     * @return void
     */
    public function testSerializationOfClosureWithSuperClosureOnConstructor()
    {
        if (!class_exists('SuperClosure\Serializer')) {
            $this->markTestSkipped('SuperClosure is not installed.');
        }

        $closureSerializer = new SuperClosureSerializer();
        $serializer = new JsonSerializer($closureSerializer);
        $serialized = $serializer->serialize(
            array(
            'func' => function () {
                return 'it works';
            },
            'nice' => true
            )
        );

        $unserialized = $serializer->unserialize($serialized);
        $this->assertTrue(is_array($unserialized));
        $this->assertTrue($unserialized['nice']);
        $this->assertInstanceOf('Closure', $unserialized['func']);
        $this->assertSame('it works', $unserialized['func']());
    }

    /**
     * Test the serialization of closures providing closure serializer
     *
     * @return void
     */
    public function testSerializationOfClosureWithSuperClosureOnManager()
    {
        if (!class_exists('SuperClosure\Serializer')) {
            $this->markTestSkipped('SuperClosure is not installed.');
        }

        $closureSerializer = new SuperClosureSerializer();
        $serializer = new JsonSerializer();
        $serializer->addClosureSerializer(new ClosureSerializer\SuperClosureSerializer($closureSerializer));
        $serialized = $serializer->serialize(
            array(
            'func' => function () {
                return 'it works';
            },
            'nice' => true
            )
        );

        $unserialized = $serializer->unserialize($serialized);
        $this->assertTrue(is_array($unserialized));
        $this->assertTrue($unserialized['nice']);
        $this->assertInstanceOf('Closure', $unserialized['func']);
        $this->assertSame('it works', $unserialized['func']());
    }

    /**
     * Test the serialization of closures providing closure serializer
     *
     * @return void
     */
    public function testSerializationOfClosureWitOpisClosure()
    {
        if (!class_exists('Opis\Closure\SerializableClosure')) {
            $this->markTestSkipped('OpisClosure is not installed.');
        }

        $serializer = new JsonSerializer();
        $serializer->addClosureSerializer(new ClosureSerializer\OpisClosureSerializer());
        $serialized = $serializer->serialize(
            array(
            'func' => function () {
                return 'it works';
            },
            'nice' => true
            )
        );

        $unserialized = $serializer->unserialize($serialized);
        $this->assertTrue(is_array($unserialized));
        $this->assertTrue($unserialized['nice']);
        $this->assertInstanceOf('Closure', $unserialized['func']);
        $this->assertSame('it works', $unserialized['func']());
    }

    /**
     * Test the serialization of closures providing closure serializer
     *
     * @return void
     */
    public function testSerializationOfClosureWitMultipleClosures()
    {
        if (!class_exists('SuperClosure\Serializer')) {
            $this->markTestSkipped('SuperClosure is not installed.');
        }
        if (!class_exists('Opis\Closure\SerializableClosure')) {
            $this->markTestSkipped('OpisClosure is not installed.');
        }

        $closureSerializer = new SuperClosureSerializer();
        $serializer = new JsonSerializer();
        $serializer->addClosureSerializer(new ClosureSerializer\SuperClosureSerializer($closureSerializer));

        $serializeData = array(
            'func' => function () {
                return 'it works';
            },
            'nice' => true
        );

        // Make sure it was serialized with SuperClosure
        $serialized = $serializer->serialize($serializeData);
        echo $serialized;
        $this->assertGreaterThanOrEqual(0, strpos($serialized, 'SuperClosure'));
        $this->assertFalse(strpos($serialized, 'OpisClosure'));

        // Test adding a new preferred closure serializer
        $serializer->addClosureSerializer(new ClosureSerializer\OpisClosureSerializer());

        $unserialized = $serializer->unserialize($serialized);
        $this->assertTrue(is_array($unserialized));
        $this->assertTrue($unserialized['nice']);
        $this->assertInstanceOf('Closure', $unserialized['func']);
        $this->assertSame('it works', $unserialized['func']());

        // Serialize again with the new preferred closure serializer
        $serialized = $serializer->serialize($serializeData);
        $this->assertFalse(strpos($serialized, 'SuperClosure'));
        $this->assertGreaterThanOrEqual(0, strpos($serialized, 'OpisClosure'));
    }

    /**
     * Test the unserialization of closures without providing closure serializer
     *
     * @return void
     */
    public function testUnserializeOfClosureWithoutSerializer()
    {
        if (!class_exists('SuperClosure\Serializer')) {
            $this->markTestSkipped('SuperClosure is not installed.');
        }

        $closureSerializer = new SuperClosureSerializer();
        $serializer = new JsonSerializer($closureSerializer);
        $serialized = $serializer->serialize(
            array(
            'func' => function () {
                return 'it works';
            },
            'nice' => true
            )
        );

        $this->expectException(JsonSerializerException::class);
        $this->serializer->unserialize($serialized);
    }

    /**
     * Test unserialize of unknown class
     *
     * @return void
     */
    public function testUnserializeUnknownClass()
    {
        $this->expectException(JsonSerializerException::class);
        $serialized = '{"@type":"UnknownClass"}';
        $this->serializer->unserialize($serialized);
    }

    /**
     * Test serialization of undeclared properties
     *
     * @return void
     */
    public function testSerializationUndeclaredProperties()
    {
        $obj = new stdClass();
        $obj->param1 = true;
        $obj->param2 = 'store me, please';
        $serialized = '{"@type":"stdClass","param1":true,"param2":"store me, please"}';
        $this->assertSame($serialized, $this->serializer->serialize($obj));

        $obj2 = $this->serializer->unserialize($serialized);
        $this->assertInstanceOf('stdClass', $obj2);
        $this->assertTrue($obj2->param1);
        $this->assertSame('store me, please', $obj2->param2);

        $serialized = '{"@type":"stdClass","sub":{"@type":"stdClass","key":"value"}}';
        $obj = $this->serializer->unserialize($serialized);
        $this->assertInstanceOf('stdClass', $obj->sub);
        $this->assertSame('value', $obj->sub->key);
    }

    /**
     * Test undeclared properties setter (valid)
     *
     * @return void
     */
    public function testSetUnserializeUndeclaredPropertyModeValid()
    {
        $value = $this->serializer->setUnserializeUndeclaredPropertyMode(JsonSerializer::UNDECLARED_PROPERTY_MODE_SET);
        $this->assertSame($value, $this->serializer);
    }

    /**
     * Test undeclared properties setter (invalid)
     *
     * @return void
     */
    public function testSetUnserializeUndeclaredPropertyModeInvalid()
    {
        $this->expectException('InvalidArgumentException');
        $value = $this->serializer->setUnserializeUndeclaredPropertyMode('bad value');
    }

    /**
     * Test unserialization of undeclared properties in SET mode
     *
     * @return void
     */
    public function testUnserializeUndeclaredPropertySet()
    {
        $this->serializer->setUnserializeUndeclaredPropertyMode(JsonSerializer::UNDECLARED_PROPERTY_MODE_SET);

        $serialized = '{"@type":"stdClass","sub":{"@type":"stdClass","key":"value"}}';
        $obj = $this->serializer->unserialize($serialized);
        $this->assertInstanceOf('stdClass', $obj->sub);
        $this->assertSame('value', $obj->sub->key);
    }

    /**
     * Test unserialization of undeclared properties in IGNORE mode
     *
     * @return void
     */
    public function testUnserializeUndeclaredPropertyIgnore()
    {
        $this->serializer->setUnserializeUndeclaredPropertyMode(JsonSerializer::UNDECLARED_PROPERTY_MODE_IGNORE);

        $serialized = '{"@type":"stdClass","sub":{"@type":"stdClass","key":"value"}}';
        $obj = $this->serializer->unserialize($serialized);
        $this->assertFalse(isset($obj->sub));
    }

    /**
     * Test unserialization of undeclared properties in EXCEPTION mode
     *
     * @return void
     */
    public function testUnserializeUndeclaredPropertyException()
    {
        $this->serializer->setUnserializeUndeclaredPropertyMode(JsonSerializer::UNDECLARED_PROPERTY_MODE_EXCEPTION);

        $this->expectException(JsonSerializerException::class);
        $serialized = '{"@type":"stdClass","sub":{"@type":"stdClass","key":"value"}}';
        $obj = $this->serializer->unserialize($serialized);
    }

    /**
     * Test serialize with recursion
     *
     * @return void
     */
    public function testSerializeRecursion()
    {
        $c1 = new stdClass();
        $c1->c2 = new stdClass();
        $c1->c2->c3 = new stdClass();
        $c1->c2->c3->c1 = $c1;
        $c1->something = 'ok';
        $c1->c2->c3->ok = true;

        $expected = '{"@type":"stdClass","c2":{"@type":"stdClass","c3":{"@type":"stdClass","c1":{"@type":"@0"},"ok":true}},"something":"ok"}';
        $this->assertSame($expected, $this->serializer->serialize($c1));

        $c1 = new stdClass();
        $c1->mirror = $c1;
        $expected = '{"@type":"stdClass","mirror":{"@type":"@0"}}';
        $this->assertSame($expected, $this->serializer->serialize($c1));
    }

    /**
     * Test unserialize with recursion
     *
     * @return void
     */
    public function testUnserializeRecursion()
    {
        $serialized = '{"@type":"stdClass","c2":{"@type":"stdClass","c3":{"@type":"stdClass","c1":{"@type":"@0"},"ok":true}},"something":"ok"}';
        $obj = $this->serializer->unserialize($serialized);
        $this->assertTrue($obj->c2->c3->ok);
        $this->assertSame($obj, $obj->c2->c3->c1);
        $this->assertNotSame($obj, $obj->c2);

        $serialized = '{"@type":"stdClass","c2":{"@type":"stdClass","c3":{"@type":"stdClass","c1":{"@type":"@0"},"c2":{"@type":"@1"},"c3":{"@type":"@2"}},"c3_copy":{"@type":"@2"}}}';
        $obj = $this->serializer->unserialize($serialized);
        $this->assertSame($obj, $obj->c2->c3->c1);
        $this->assertSame($obj->c2, $obj->c2->c3->c2);
        $this->assertSame($obj->c2->c3, $obj->c2->c3->c3);
        $this->assertSame($obj->c2->c3_copy, $obj->c2->c3);
    }

    /**
     * Test unserialize with bad JSON
     *
     * @return void
     */
    public function testUnserializeBadJSON()
    {
        $this->expectException(JsonSerializerException::class);
        $this->serializer->unserialize('[this is not a valid json!}');
    }

    /**
     * The test attempts to serialize an array containing a NAN
     */
    public function testSerializeInvalidData()
    {
        $this->expectException(JsonSerializerException::class);
        $this->serializer->serialize(array(NAN));
    }

    /**
     *
     * @return void
     */
    public function testSerializeBinaryStringScalar()
    {
        $data = '';
        for ($i = 0; $i <= 255; $i++) {
            $data .= chr($i);
        }

        $unserialized = $this->serializer->unserialize($this->serializer->serialize($data));
        $this->assertSame($data, $unserialized);
    }

    /**
     *
     * @return void
     */
    public function testSerializeArrayWithBinaryStringsAsValues()
    {
        $data = '';
        for ($i = 0; $i <= 255; $i++) {
            $data .= chr($i);
        }

        $data = [$data, "$data 1", "$data 2"];
        $unserialized = $this->serializer->unserialize($this->serializer->serialize($data));
        $this->assertSame($data, $unserialized);
    }

    /**
     * Starting from 1 and not from 0 because php cannot handle the nil character (\u0000) in json keys as per:
     * https://github.com/remicollet/pecl-json-c/issues/7
     * https://github.com/json-c/json-c/issues/108
     *
     * @return void
     */
    public function testSerializeArrayWithBinaryStringsAsKeys()
    {
        $data = '';
        for ($i = 1; $i <= 255; $i++) {
            $data .= chr($i);
        }

        $data = [$data => $data, "$data 1" => 'something'];
        $unserialized = $this->serializer->unserialize($this->serializer->serialize($data));
        $this->assertSame($data, $unserialized);
    }

    /**
     *
     * @return void
     */
    public function testSerializeObjectWithBinaryStrings()
    {
        $data = '';
        for ($i = 0; $i <= 255; $i++) {
            $data .= chr($i);
        }

        $obj = new \stdClass();
        $obj->string = $data;
        $unserialized = $this->serializer->unserialize($this->serializer->serialize($obj));
        $this->assertInstanceOf('stdClass', $obj);
        $this->assertSame($obj->string, $unserialized->string);
    }

    /**
     * Test serialization of SplDoubleLinkedList
     *
     * @return void
     */
    public function testSerializationOfSplDoublyLinkedList()
    {
        $list = new \SplDoublyLinkedList();
        $list->push('fizz');
        $list->push(42);
        $unserialized = $this->serializer->unserialize($this->serializer->serialize($list));
        $this->assertTrue($list->serialize() === $unserialized->serialize());
    }
}
