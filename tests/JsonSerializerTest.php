<?php

namespace Zumba\JsonSerializer\Test;

use stdClass;
use SuperClosure\Serializer as ClosureSerializer;
use Zumba\JsonSerializer\JsonSerializer;
use Zumba\JsonSerializer\Test\SupportClasses\MyTypeSerializer;

class JsonSerializerTest extends \PHPUnit_Framework_TestCase
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
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $this->serializer = new JsonSerializer();
        $this->serializer->registerEntitySerializer(new MyTypeSerializer());
    }

    /**
     * Test serialization of scalar values
     *
     * @dataProvider scalarData
     * @param mixed $scalar
     * @param string $jsoned
     * @return void
     */
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
        $expected = '[1.0,1.1,1.0e-11,1.999999999999,223423.12345679,100000.0,100000000000.0]';
        $this->assertSame($expected, $this->serializer->serialize($data));

        setlocale(LC_NUMERIC, $originalLocale);
    }

    /**
     * Test unserialization of scalar values
     *
     * @dataProvider scalarData
     * @param mixed $scalar
     * @param string $jsoned
     * @return void
     */
    public function testUnserializeScalar($scalar, $jsoned)
    {
        $this->assertSame($scalar, $this->serializer->unserialize($jsoned));
    }

    /**
     * List of scalar data
     *
     * @return array
     */
    public function scalarData()
    {
        return [
            ['testing', '"testing"'],
            [123, '123'],
            [0, '0'],
            [0.0, '0.0'],
            [17.0, '17.0'],
            [17e1, '170.0'],
            [17.2, '17.2'],
            [true, 'true'],
            [false, 'false'],
            [null, 'null'],
            // Non UTF8
            ['ßåö', '"ßåö"']
        ];
    }

    /**
     * Test the serialization of resources
     *
     * @return void
     */
    public function testSerializeResource()
    {
        $this->setExpectedException('Zumba\JsonSerializer\Exception\JsonSerializerException');
        $this->serializer->serialize(fopen(__FILE__, 'r'));
    }

    /**
     * Test the serialization of closures when not providing closure serializer
     *
     * @return void
     */
    public function testSerializeClosureWithoutSerializer()
    {
        $this->setExpectedException('Zumba\JsonSerializer\Exception\JsonSerializerException');
        $this->serializer->serialize([
            'func' => function () {
                echo 'whoops';
            }
        ]);
    }

    /**
     * Test serialization of array without objects
     *
     * @dataProvider arrayNoObjectData
     * @param array $array
     * @param string $jsoned
     * @return void
     */
    public function testSerializeArrayNoObject($array, $jsoned)
    {
        $this->assertSame($jsoned, $this->serializer->serialize($array));
    }

    /**
     * Test unserialization of array without objects
     *
     * @dataProvider arrayNoObjectData
     * @param array $array
     * @param string $jsoned
     * @return void
     */
    public function testUnserializeArrayNoObject($array, $jsoned)
    {
        $this->assertSame($array, $this->serializer->unserialize($jsoned));
    }

    /**
     * List of array data
     *
     * @return array
     */
    public function arrayNoObjectData()
    {
        return [
            [[1, 2, 3], '[1,2,3]'],
            [[1, 'abc', false], '[1,"abc",false]'],
            [['a' => 1, 'b' => 2, 'c' => 3], '{"a":1,"b":2,"c":3}'],
            [['integer' => 1, 'string' => 'abc', 'bool' => false], '{"integer":1,"string":"abc","bool":false}'],
            [[1, ['nested']], '[1,["nested"]]'],
            [['integer' => 1, 'array' => ['nested']], '{"integer":1,"array":["nested"]}'],
            [['integer' => 1, 'array' => ['nested' => 'object']], '{"integer":1,"array":{"nested":"object"}}'],
            [[1.0, 2, 3e1], '[1.0,2,30.0]'],
        ];
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
        $this->assertSame('{"@type":"Zumba\\\\JsonSerializer\\\\Test\\\\SupportClasses\\\\EmptyClass"}',
            $this->serializer->serialize($obj));

        $obj = new SupportClasses\AllVisibilities();
        $expected = '{"@type":"Zumba\\\\JsonSerializer\\\\Test\\\\SupportClasses\\\\AllVisibilities","pub":"this is public","prot":"protected","priv":"dont tell anyone"}';
        $this->assertSame($expected, $this->serializer->serialize($obj));

        $obj->pub = 'new value';
        $expected = '{"@type":"Zumba\\\\JsonSerializer\\\\Test\\\\SupportClasses\\\\AllVisibilities","pub":"new value","prot":"protected","priv":"dont tell anyone"}';
        $this->assertSame($expected, $this->serializer->serialize($obj));

        $obj->pub = $empty;
        $expected = '{"@type":"Zumba\\\\JsonSerializer\\\\Test\\\\SupportClasses\\\\AllVisibilities","pub":{"@type":"Zumba\\\\JsonSerializer\\\\Test\\\\SupportClasses\\\\EmptyClass"},"prot":"protected","priv":"dont tell anyone"}';
        $this->assertSame($expected, $this->serializer->serialize($obj));

        $array = ['instance' => $empty];
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
        $this->assertAttributeSame('protected', 'prot', $obj);
        $this->assertAttributeSame('dont tell anyone', 'priv', $obj);

        $serialized = '{"instance":{"@type":"Zumba\\\\JsonSerializer\\\\Test\\\\SupportClasses\\\\EmptyClass"}}';
        $array = $this->serializer->unserialize($serialized);
        $this->assertTrue(is_array($array));
        $this->assertInstanceOf('Zumba\JsonSerializer\Test\SupportClasses\EmptyClass', $array['instance']);
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
        $this->assertSame('{"@type":"Zumba\\\\JsonSerializer\\\\Test\\\\SupportClasses\\\\MyType","field1":"x","field2":"y"}',
            $this->serializer->serialize($obj));
    }

    /**
     * Test serialization of objects using the custom serializers
     *
     * @return void
     */
    public function testCustomObjectInheritanceSerializer()
    {
        $obj = new SupportClasses\MySubType();
        $obj->field1 = 'x';
        $obj->field2 = 'y';
        $this->assertSame('{"@type":"Zumba\\\\JsonSerializer\\\\Test\\\\SupportClasses\\\\MySubType","field1":"x","field2":"y"}',
            $this->serializer->serialize($obj));
    }

    /**
     * Test unserialization of objects using the custom serializers
     *
     * @return void
     */
    public function testCustomObjectsUnserializer()
    {
        $serialized = '{"@type":"Zumba\\\\JsonSerializer\\\\Test\\\\SupportClasses\\\\MyType","field1":"x","field2":"y"}';
        $obj = $this->serializer->unserialize($serialized);
        $this->assertInstanceOf('Zumba\JsonSerializer\Test\SupportClasses\MyType', $obj);
        $this->assertAttributeSame('x', 'field1', $obj);
        $this->assertAttributeSame('y', 'field2', $obj);
    }

    /**
     * Test unserialization of objects using the custom serializers
     *
     * @return void
     */
    public function testCustomObjectsInheritanceUnserializer()
    {
        $serialized = '{"@type":"Zumba\\\\JsonSerializer\\\\Test\\\\SupportClasses\\\\MySubType","field1":"x","field2":"y"}';
        $obj = $this->serializer->unserialize($serialized);
        $this->assertInstanceOf('Zumba\JsonSerializer\Test\SupportClasses\MySubType', $obj);
        $this->assertAttributeSame('x', 'field1', $obj);
        $this->assertAttributeSame('y', 'field2', $obj);
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
    }

    /**
     * Test the serialization of closures providing closure serializer
     *
     * @return void
     */
    public function testSerializationOfClosure()
    {
        if (!class_exists('SuperClosure\Serializer')) {
            $this->markTestSkipped('SuperClosure is not installed.');
        }

        $closureSerializer = new ClosureSerializer();
        $serializer = new JsonSerializer($closureSerializer);
        $serialized = $serializer->serialize([
            'func' => function () {
                return 'it works';
            },
            'nice' => true
        ]);

        $unserialized = $serializer->unserialize($serialized);
        $this->assertTrue(is_array($unserialized));
        $this->assertTrue($unserialized['nice']);
        $this->assertInstanceOf('Closure', $unserialized['func']);
        $this->assertSame('it works', $unserialized['func']());
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

        $closureSerializer = new ClosureSerializer();
        $serializer = new JsonSerializer($closureSerializer);
        $serialized = $serializer->serialize([
            'func' => function () {
                return 'it works';
            },
            'nice' => true
        ]);

        $this->setExpectedException('Zumba\JsonSerializer\Exception\JsonSerializerException');
        $this->serializer->unserialize($serialized);
    }

    /**
     * Test unserialize of unknown class
     *
     * @return void
     */
    public function testUnserializeUnknownClass()
    {
        $this->setExpectedException('Zumba\JsonSerializer\Exception\JsonSerializerException');
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
        $this->setExpectedException('Zumba\Exception\JsonSerializerException');
        $this->serializer->unserialize('[this is not a valid json!}');
    }

    /**
     * The test attempts to serialize an array containing a NAN
     */
    public function testSerializeInvalidData()
    {
        if (PHP_VERSION_ID < 50500) {
            $this->markTestSkipped('PHP 5.4 raises a warning when encoding NAN, which fails the test.');
        }

        $this->setExpectedException('Zumba\Exception\JsonSerializerException');
        $this->serializer->serialize([NAN]);
    }

    /**
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

    /*
     * Test namespace change (backward compatibility)
     *
     * @return void
     * @deprecated
     */
    public function testNamespaceRename()
    {
        $serializer = new \Zumba\Util\JsonSerializer();

        $f = fopen(__FILE__, 'r');
        $this->setExpectedException('Zumba\Exception\JsonSerializerException');
        $this->serializer->serialize($f);
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

    /**
     * Test serialization of SplDoubleLinkedList
     *
     * @return void
     */
    public function testEntitySerializerRegistration()
    {
        $this->assertTrue($this->serializer->hasEntitySerializer('Zumba\JsonSerializer\Test\SupportClasses\MyType'));
        $this->assertFalse($this->serializer->hasEntitySerializer('Zumba\JsonSerializer\Test\SupportClasses\MySubType'));
    }
}
