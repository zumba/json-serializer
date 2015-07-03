<?php

namespace Zumba\Util\Test;

use Zumba\Util\JsonSerializer;
use stdClass;
use Zumba\Util\Test\SupportClasses\ArrayAccessClass;
use Zumba\Util\Test\SupportClasses\TraversableClass;

class JsonSerializerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Serializer instance.
     *
     * @var Zumba\Util\JsonSerializer
     */
    protected $serializer;

    /**
     * Test case setup.
     */
    public function setUp()
    {
        parent::setUp();
        $this->serializer = new JsonSerializer();
    }

    /**
     * Test serialization of scalar values.
     *
     * @dataProvider scalarData
     *
     * @param mixed  $scalar
     * @param string $jsoned
     */
    public function testSerializeScalar($scalar, $jsoned)
    {
        $this->assertSame($jsoned, $this->serializer->serialize($scalar));
    }

    /**
     * Test unserialization of scalar values.
     *
     * @dataProvider scalarData
     *
     * @param mixed  $scalar
     * @param string $jsoned
     */
    public function testUnserializeScalar($scalar, $jsoned)
    {
        $this->assertSame($scalar, $this->serializer->unserialize($jsoned));
    }

    /**
     * List of scalar data.
     *
     * @return array
     */
    public function scalarData()
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
            array('ßåö', '"ßåö"'),
        );
    }

    /**
     * Test the serialization of resources.
     */
    public function testSerializeResource()
    {
        $this->setExpectedException('Zumba\Exception\JsonSerializerException');
        $this->serializer->serialize(fopen(__FILE__, 'r'));
    }

    /**
     * Test the serialization of closures.
     */
    public function testSerializeClosure()
    {
        $this->setExpectedException('Zumba\Exception\JsonSerializerException');
        $this->serializer->serialize(array('func' => function () {
            echo 'whoops';
        }));
    }

    /**
     * Test serialization of array without objects.
     *
     * @dataProvider arrayNoObjectData
     *
     * @param array  $array
     * @param string $jsoned
     */
    public function testSerializeArrayNoObject($array, $jsoned)
    {
        $this->assertSame($jsoned, $this->serializer->serialize($array));
    }

    /**
     * Test unserialization of array without objects.
     *
     * @dataProvider arrayNoObjectData
     *
     * @param array  $array
     * @param string $jsoned
     */
    public function testUnserializeArrayNoObject($array, $jsoned)
    {
        $this->assertSame($array, $this->serializer->unserialize($jsoned));
    }

    /**
     * List of array data.
     *
     * @return array
     */
    public function arrayNoObjectData()
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
     * Test serialization of objects.
     */
    public function testSerializeObject()
    {
        $obj = new stdClass();
        $this->assertSame('{"@type":"stdClass"}', $this->serializer->serialize($obj));

        $obj = $empty = new SupportClasses\EmptyClass();
        $this->assertSame('{"@type":"Zumba\\\\Util\\\\Test\\\\SupportClasses\\\\EmptyClass"}', $this->serializer->serialize($obj));

        $obj = new SupportClasses\AllVisibilities();
        $expected = '{"@type":"Zumba\\\\Util\\\\Test\\\\SupportClasses\\\\AllVisibilities","pub":"this is public","prot":"protected","priv":"dont tell anyone"}';
        $this->assertSame($expected, $this->serializer->serialize($obj));

        $obj->pub = 'new value';
        $expected = '{"@type":"Zumba\\\\Util\\\\Test\\\\SupportClasses\\\\AllVisibilities","pub":"new value","prot":"protected","priv":"dont tell anyone"}';
        $this->assertSame($expected, $this->serializer->serialize($obj));

        $obj->pub = $empty;
        $expected = '{"@type":"Zumba\\\\Util\\\\Test\\\\SupportClasses\\\\AllVisibilities","pub":{"@type":"Zumba\\\\Util\\\\Test\\\\SupportClasses\\\\EmptyClass"},"prot":"protected","priv":"dont tell anyone"}';
        $this->assertSame($expected, $this->serializer->serialize($obj));

        $array = array('instance' => $empty);
        $expected = '{"instance":{"@type":"Zumba\\\\Util\\\\Test\\\\SupportClasses\\\\EmptyClass"}}';
        $this->assertSame($expected, $this->serializer->serialize($array));

        $obj = new stdClass();
        $obj->total = 10.0;
        $obj->discount = 0.0;
        $expected = '{"@type":"stdClass","total":10.0,"discount":0.0}';
        $this->assertSame($expected, $this->serializer->serialize($obj));
    }

    /**
     * Test unserialization of objects.
     */
    public function testUnserializeObjects()
    {
        $serialized = '{"@type":"stdClass"}';
        $obj = $this->serializer->unserialize($serialized);
        $this->assertInstanceOf('stdClass', $obj);

        $serialized = '{"@type":"Zumba\\\\Util\\\\Test\\\\SupportClasses\\\\EmptyClass"}';
        $obj = $this->serializer->unserialize($serialized);
        $this->assertInstanceOf('Zumba\Util\Test\SupportClasses\EmptyClass', $obj);

        $serialized = '{"@type":"Zumba\\\\Util\\\\Test\\\\SupportClasses\\\\AllVisibilities","pub":{"@type":"Zumba\\\\Util\\\\Test\\\\SupportClasses\\\\EmptyClass"},"prot":"protected","priv":"dont tell anyone"}';
        $obj = $this->serializer->unserialize($serialized);
        $this->assertInstanceOf('Zumba\Util\Test\SupportClasses\AllVisibilities', $obj);
        $this->assertInstanceOf('Zumba\Util\Test\SupportClasses\EmptyClass', $obj->pub);
        $this->assertAttributeSame('protected', 'prot', $obj);
        $this->assertAttributeSame('dont tell anyone', 'priv', $obj);

        $serialized = '{"instance":{"@type":"Zumba\\\\Util\\\\Test\\\\SupportClasses\\\\EmptyClass"}}';
        $array = $this->serializer->unserialize($serialized);
        $this->assertTrue(is_array($array));
        $this->assertInstanceOf('Zumba\Util\Test\SupportClasses\EmptyClass', $array['instance']);
    }

    /**
     * Test magic serialization methods.
     */
    public function testSerializationMagicMethods()
    {
        $obj = new SupportClasses\MagicClass();
        $serialized = '{"@type":"Zumba\\\\Util\\\\Test\\\\SupportClasses\\\\MagicClass","show":true}';
        $this->assertSame($serialized, $this->serializer->serialize($obj));
        $this->assertFalse($obj->woke);

        $obj = $this->serializer->unserialize($serialized);
        $this->assertTrue($obj->woke);
    }

    /**
     * Traversable serialization
     */
    public function testSerializationOfTraversableClasses()
    {
        $traversable = new TraversableClass();
        $traversable->next();
        $unserializedCollection = $this->serializer->unserialize($this->serializer->serialize($traversable));

        $this->assertEquals($traversable, $unserializedCollection);
    }

    /**
     * ArrayAccess serialization
     */
    public function testSerializationOfArrayAccessClasses()
    {
        $arrayAccess = new ArrayAccessClass();
        $arrayAccess[] = 'Append 1';
        $arrayAccess[] = 'Append 2';
        $arrayAccess[] = 'Append 3';

        $unserializedCollection = $this->serializer->unserialize($this->serializer->serialize($arrayAccess));

        $this->assertEquals($arrayAccess, $unserializedCollection);
    }

    /**
     * Test serialization of DateTime classes.
     *
     * Some interal classes, such as DateTime, cannot be initialized with
     * ReflectionClass::newInstanceWithoutConstructor()
     */
    public function testSerializationOfDateTime()
    {
        $date = new \DateTime('2014-06-15 12:00:00', new \DateTimeZone('UTC'));
        $obj = $this->serializer->unserialize($this->serializer->serialize($date));
        $this->assertSame($date->getTimestamp(), $obj->getTimestamp());
        $this->assertEquals($date, $obj);
    }

    /**
     * Test serialization of DateTimeImmutable classes.
     *
     * Some interal classes, such as DateTimeImmutable, cannot be initialized with
     * ReflectionClass::newInstanceWithoutConstructor()
     */
    public function testSerializationOfDateTimeImmutable()
    {
        if (version_compare(PHP_VERSION, '5.5.0', '<')) {
            $this->markTestSkipped('Supported for PHP 5.5.0 and above');
        }

        $date = new \DateTimeImmutable('2014-06-15 12:00:00', new \DateTimeZone('UTC'));
        $obj = $this->serializer->unserialize($this->serializer->serialize($date));
        $this->assertSame($date->getTimestamp(), $obj->getTimestamp());
        $this->assertEquals($date, $obj);
    }

    /**
     * Test serialization of DateTimeZone classes.
     *
     * Some interal classes, such as DateTimeZone, cannot be initialized with
     * ReflectionClass::newInstanceWithoutConstructor()
     */
    public function testSerializationOfDateTimeZone()
    {
        $date = new \DateTimeZone('UTC');
        $obj = $this->serializer->unserialize($this->serializer->serialize($date));
        $this->assertEquals($date, $obj);
    }

    /**
     * Test serialization of DateInterval classes.
     *
     * Some interal classes, such as DateInterval, cannot be initialized with
     * ReflectionClass::newInstanceWithoutConstructor()
     */
    public function testSerializationOfDateInterval()
    {
        $date = new \DateInterval('P2Y4DT6H8M');
        $obj = $this->serializer->unserialize($this->serializer->serialize($date));
        $this->assertEquals($date, $obj);
        $this->assertSame($date->d, $obj->d);
    }

    /**
     * Test serialization of DatePeriod classes.
     *
     * Some interal classes, such as DatePeriod, cannot be initialized with
     * ReflectionClass::newInstanceWithoutConstructor()
     */
    public function testSerializationOfDatePeriodException()
    {
        $this->setExpectedException(
            'Zumba\Exception\JsonSerializerException',
            'DatePeriod is not supported in JsonSerializer. Loop through it and serialize the output.'
        );

        $period = new \DatePeriod(new \DateTime('2012-07-01'),  new \DateInterval('P7D'), 4);
        $this->serializer->serialize($period);
    }

    /**
     * Test serialization of DatePeriod output.
     */
    public function testSerializationOfDatePeriodOutputIsSerializable()
    {
        $period = new \DatePeriod(new \DateTime('2012-07-01'),  new \DateInterval('P7D'), 4);
        $dates = array();
        foreach ($period as $p) {
            $dates[] = $p;
        }
        $objs = $this->serializer->unserialize($this->serializer->serialize($dates));

        foreach ($objs as $key => $obj) {
            $this->assertSame($dates[$key]->getTimestamp(), $obj->getTimestamp());
            $this->assertEquals($dates[$key], $obj);
        }
    }

    /**
     * Test unserialize of unknown class.
     */
    public function testUnserializeUnknownClass()
    {
        $this->setExpectedException('Zumba\Exception\JsonSerializerException');
        $serialized = '{"@type":"UnknownClass"}';
        $this->serializer->unserialize($serialized);
    }

    /**
     * Test serialization of undeclared properties.
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
     * Test serialize with recursion.
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
     * Test unserialize with recursion.
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
}
