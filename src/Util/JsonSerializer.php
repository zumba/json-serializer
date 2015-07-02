<?php

namespace Zumba\Util;

use ReflectionClass;
use ReflectionException;
use SplObjectStorage;
use Zumba\Exception\JsonSerializerException;

class JsonSerializer
{
    const CLASS_IDENTIFIER_KEY = '@type';
    const FLOAT_ADAPTER = 'JsonSerializerFloatAdapter';

    /**
     * Storage for object.
     *
     * Used for recursion
     *
     * @var SplObjectStorage
     */
    private $objectStorage;

    /**
     * Object mapping for recursion.
     *
     * @var array
     */
    private $objectMapping = array();

    /**
     * Object mapping index.
     *
     * @var int
     */
    private $objectMappingIndex = 0;

    /**
     * Support PRESERVE_ZERO_FRACTION json option.
     *
     * @var bool
     */
    private $preserveZeroFractionSupport;

    /**
     * @var array
     */
    private $dateTimeClassType = array(
        'DateTime',
        'DateTimeImmutable',
        'DateTimeZone',
        'DateInterval',
        'DatePeriod',
    );

    /**
     * @var array
     */
    private $serializationMap = [
        'object' => 'serializeObject',
        'array' => 'serializeArray',
        'integer' => 'serializeScalar',
        'double' => 'serializeScalar',
        'boolean' => 'serializeScalar',
        'string' => 'serializeScalar',
        'DatePeriod' => 'serializeDatePeriod',
    ];

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->preserveZeroFractionSupport = defined('JSON_PRESERVE_ZERO_FRACTION');
    }

    /**
     * Serialize the value in JSON.
     *
     * @param mixed $value
     *
     * @return string JSON encoded
     *
     * @throws \Zumba\Exception\JsonSerializerException
     */
    public function serialize($value)
    {
        $this->reset();

        $encoded = json_encode($this->serializeData($value), $this->calculateEncodeOptions());

        return $this->processEncodedValue($encoded);
    }

    /**
     * Reset variables.
     */
    protected function reset()
    {
        $this->objectStorage = new SplObjectStorage();
        $this->objectMapping = array();
        $this->objectMappingIndex = 0;
    }

    /**
     * Parse the data to be json encoded.
     *
     * @param mixed $value
     *
     * @return mixed
     *
     * @throws \Zumba\Exception\JsonSerializerException
     */
    protected function serializeData($value)
    {
        if (!(($value instanceof \DatePeriod) || ($value instanceof \Closure) || (is_resource($value)))) {
            $this->throwExceptionForUnsupportedValue($value);
        }

        $func = $this->getSerializer($value);

        return $this->$func($value);
    }

    /**
     * @param mixed $value
     *
     * @throws \Zumba\Exception\JsonSerializerException
     */
    protected function throwExceptionForUnsupportedValue($value)
    {
        if ($value instanceof \DatePeriod) {
            throw new JsonSerializerException(
                'DatePeriod is not supported in JsonSerializer. Loop through it and serialize the output.'
            );
        }

        if (is_resource($value)) {
            throw new JsonSerializerException('Resource is not supported in JsonSerializer');
        }

        if ($value instanceof \Closure) {
            throw new JsonSerializerException('Closures are not supported in JsonSerializer');
        }
    }

    /**
     * @param mixed $value
     *
     * @return string
     */
    protected function getSerializer($value)
    {
        $type = (gettype($value) && $value !== null) ? gettype($value) : 'string';
        $type = ($value instanceof \DatePeriod) ? 'DatePeriod' : $type;

        return $this->serializationMap[$type];
    }

    /**
     * Calculate encoding options.
     *
     * @return int
     */
    protected function calculateEncodeOptions()
    {
        $options = JSON_UNESCAPED_UNICODE;
        if ($this->preserveZeroFractionSupport) {
            $options |= JSON_PRESERVE_ZERO_FRACTION;
        }

        return $options;
    }

    /**
     * Execute post-encoding actions.
     *
     * @param string $encoded
     *
     * @return string
     */
    protected function processEncodedValue($encoded)
    {
        if (!$this->preserveZeroFractionSupport) {
            $encoded = preg_replace('/"'.static::FLOAT_ADAPTER.'\((.*?)\)"/', '\1', $encoded);
        }

        return $encoded;
    }

    /**
     * Unserialize the value from JSON.
     *
     * @param string $value
     *
     * @return mixed
     */
    public function unserialize($value)
    {
        $this->reset();

        return $this->unserializeData(json_decode($value, true));
    }

    /**
     * Parse the json decode to convert to objects again.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    protected function unserializeData($value)
    {
        if (is_scalar($value) || $value === null) {
            return $value;
        }

        return isset($value[static::CLASS_IDENTIFIER_KEY]) ?
            $this->unserializeObject($value) : array_map(array($this, __FUNCTION__), $value);
    }

    /**
     * Convert the serialized array into an object.
     *
     * @param array $value
     *
     * @return object
     *
     * @throws \Zumba\Exception\JsonSerializerException
     */
    protected function unserializeObject(array $value)
    {
        $className = $value[static::CLASS_IDENTIFIER_KEY];
        unset($value[static::CLASS_IDENTIFIER_KEY]);

        if ($className[0] === '@') {
            return $this->objectMapping[substr($className, 1)];
        }

        if (!class_exists($className)) {
            throw new JsonSerializerException('Unable to find class '.$className);
        }

        return (null === ($obj = $this->unserializeDateTimeFamilyObject($value, $className)))
            ? $this->unserializeUserDefinedObject($value, $className) : $obj;
    }

    /**
     * @param array  $value
     * @param string $className
     *
     * @return mixed
     */
    protected function unserializeDateTimeFamilyObject(array $value, $className)
    {
        $obj = null;

        if ($this->isDateTimeFamilyObject($className)) {
            $obj = $this->restoreUsingUnserialize($className, $value);
            $this->objectMapping[$this->objectMappingIndex++] = $obj;
        }

        return $obj;
    }

    /**
     * @param string $className
     *
     * @return bool
     */
    protected function isDateTimeFamilyObject($className)
    {
        $isDateTime = false;

        foreach ($this->dateTimeClassType as $class) {
            $isDateTime = $isDateTime || is_subclass_of($className, $class, true) || $class === $className;
        }

        return $isDateTime;
    }

    /**
     * @param string $className
     * @param array  $attributes
     *
     * @return mixed
     */
    protected function restoreUsingUnserialize($className, array $attributes)
    {
        $obj = (object) $attributes;
        $serialized = preg_replace(
            '|^O:\d+:"\w+":|',
            'O:'.strlen($className).':"'.$className.'":',
            serialize($obj)
        );

        return unserialize($serialized);
    }

    /**
     * @param array  $value
     * @param string $className
     *
     * @return object
     */
    protected function unserializeUserDefinedObject(array $value, $className)
    {
        $ref = new ReflectionClass($className);
        $obj = $ref->newInstanceWithoutConstructor();

        $this->objectMapping[$this->objectMappingIndex++] = $obj;
        $this->setUnserializedObjectProperties($value, $ref, $obj);

        if (method_exists($obj, '__wakeup')) {
            $obj->__wakeup();
        }

        return $obj;
    }

    /**
     * @param array           $value
     * @param ReflectionClass $ref
     * @param mixed           $obj
     *
     * @return mixed
     */
    protected function setUnserializedObjectProperties(array $value, ReflectionClass $ref, $obj)
    {
        foreach ($value as $property => $propertyValue) {
            try {
                $propRef = $ref->getProperty($property);
                $propRef->setAccessible(true);
                $propRef->setValue($obj, $this->unserializeData($propertyValue));
            } catch (ReflectionException $e) {
                $obj->$property = $this->unserializeData($propertyValue);
            }
        }

        return $obj;
    }

    /**
     * @param \DatePeriod $value
     *
     * @return mixed
     */
    protected function serializeDatePeriod(\DatePeriod $value)
    {
        $toArray = array(static::CLASS_IDENTIFIER_KEY => 'DatePeriod');
        foreach ($value as $field) {
            $toArray[] = $field;
        }

        return $this->serializeData($toArray);
    }

    /**
     * @param $value
     *
     * @return string
     */
    protected function serializeScalar($value)
    {
        if (!$this->preserveZeroFractionSupport && is_float($value) && strpos((string) $value, '.') === false) {
            // Because the PHP bug #50224, the float numbers with no
            // precision numbers are converted to integers when encoded
            $value = static::FLOAT_ADAPTER.'('.$value.'.0)';
        }

        return $value;
    }

    /**
     * @param array $value
     *
     * @return array
     */
    protected function serializeArray(array $value)
    {
        return array_map(array($this, 'serializeData'), $value);
    }

    /**
     * Extract the data from an object.
     *
     * @param string $value
     *
     * @return array
     */
    protected function serializeObject($value)
    {
        $ref = new ReflectionClass($value);
        if ($this->objectStorage->contains($value)) {
            return array(static::CLASS_IDENTIFIER_KEY => '@'.$this->objectStorage[$value]);
        }

        $this->objectStorage->attach($value, $this->objectMappingIndex++);
        $paramsToSerialize = $this->getObjectProperties($ref, $value);

        $data = array(static::CLASS_IDENTIFIER_KEY => $ref->getName());
        $data += array_map(array($this, 'serializeData'), $this->extractObjectData($value, $ref, $paramsToSerialize));

        return $data;
    }

    /**
     * Return the list of properties to be serialized.
     *
     * @param ReflectionClass $ref
     * @param object          $value
     *
     * @return array
     */
    protected function getObjectProperties(ReflectionClass $ref, $value)
    {
        if (method_exists($value, '__sleep')) {
            return $value->__sleep();
        }

        $props = array();
        foreach ($ref->getProperties() as $prop) {
            $props[] = $prop->getName();
        }

        return array_unique(array_merge($props, array_keys(get_object_vars($value))));
    }

    /**
     * Extract the object data.
     *
     * @param object          $value
     * @param ReflectionClass $ref
     * @param array           $properties
     *
     * @return array
     */
    protected function extractObjectData($value, $ref, $properties)
    {
        $data = array();
        foreach ($properties as $property) {
            try {
                $propRef = $ref->getProperty($property);
                $propRef->setAccessible(true);
                $data[$property] = $propRef->getValue($value);
            } catch (ReflectionException $e) {
                $data[$property] = $value->$property;
            }
        }

        return $data;
    }
}
