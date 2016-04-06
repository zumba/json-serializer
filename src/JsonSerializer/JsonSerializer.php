<?php

namespace Zumba\JsonSerializer;

use ReflectionClass;
use ReflectionException;
use SplObjectStorage;
use Zumba\JsonSerializer\Exception\JsonSerializerException;
use SuperClosure\SerializerInterface as ClosureSerializerInterface;

class JsonSerializer
{

    const CLASS_IDENTIFIER_KEY = '@type';
    const CLOSURE_IDENTIFIER_KEY = '@closure';
    const FLOAT_ADAPTER = 'JsonSerializerFloatAdapter';

    /**
     * Storage for object
     *
     * Used for recursion
     *
     * @var SplObjectStorage
     */
    protected $objectStorage;

    /**
     * Object mapping for recursion
     *
     * @var array
     */
    protected $objectMapping = array();

    /**
     * Object mapping index
     *
     * @var integer
     */
    protected $objectMappingIndex = 0;

    /**
     * Support PRESERVE_ZERO_FRACTION json option
     *
     * @var boolean
     */
    protected $preserveZeroFractionSupport;

    /**
     * Closure serializer instance
     *
     * @var SuperClosure\SerializerInterface
     */
    protected $closureSerializer;

    /**
     * Constructor.
     *
     * @param SuperClosure\SerializerInterface $closureSerializer
     */
    public function __construct(ClosureSerializerInterface $closureSerializer = null)
    {
        $this->preserveZeroFractionSupport = defined('JSON_PRESERVE_ZERO_FRACTION');
        $this->closureSerializer = $closureSerializer;
    }

    /**
     * Serialize the value in JSON
     *
     * @param mixed $value
     * @return string JSON encoded
     * @throws Zumba\JsonSerializer\Exception\JsonSerializerException
     */
    public function serialize($value)
    {
        $this->reset();
        $encoded = json_encode($this->serializeData($value), $this->calculateEncodeOptions());
        return $this->processEncodedValue($encoded);
    }

    /**
     * Calculate encoding options
     *
     * @return integer
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
     * Execute post-encoding actions
     *
     * @param string $encoded
     * @return string
     */
    protected function processEncodedValue($encoded)
    {
        if (!$this->preserveZeroFractionSupport) {
            $encoded = preg_replace('/"' . static::FLOAT_ADAPTER . '\((.*?)\)"/', '\1', $encoded);
        }
        return $encoded;
    }

    /**
     * Unserialize the value from JSON
     *
     * @param string $value
     * @return mixed
     */
    public function unserialize($value)
    {
        $this->reset();
        $data = json_decode($value, true);
        if ($data === null && json_last_error() != JSON_ERROR_NONE) {
            throw new JsonSerializerException('Invalid JSON to unserialize.');
        }
        return $this->unserializeData($data);
    }

    /**
     * Parse the data to be json encoded
     *
     * @param mixed $value
     * @return mixed
     * @throws Zumba\JsonSerializer\Exception\JsonSerializerException
     */
    protected function serializeData($value)
    {
        if (is_scalar($value) || $value === null) {
            if (!$this->preserveZeroFractionSupport && is_float($value) && ctype_digit((string)$value)) {
                // Because the PHP bug #50224, the float numbers with no
                // precision numbers are converted to integers when encoded
                $value = static::FLOAT_ADAPTER . '(' . $value . '.0)';
            }
            return $value;
        }
        if (is_resource($value)) {
            throw new JsonSerializerException('Resource is not supported in JsonSerializer');
        }
        if (is_array($value)) {
            return array_map(array($this, __FUNCTION__), $value);
        }
        if ($value instanceof \Closure) {
            if (!$this->closureSerializer) {
                throw new JsonSerializerException('Closure serializer not given. Unable to serialize closure.');
            }
            return array(
                static::CLOSURE_IDENTIFIER_KEY => true,
                'value' => $this->closureSerializer->serialize($value)
            );
        }
        return $this->serializeObject($value);
    }

    /**
     * Extract the data from an object
     *
     * @param object $value
     * @return array
     */
    protected function serializeObject($value)
    {
        $ref = new ReflectionClass($value);

        if ($this->objectStorage->contains($value)) {
            return array(static::CLASS_IDENTIFIER_KEY => '@' . $this->objectStorage[$value]);
        }
        $this->objectStorage->attach($value, $this->objectMappingIndex++);

        $paramsToSerialize = $this->getObjectProperties($ref, $value);
        $data = array(static::CLASS_IDENTIFIER_KEY => $ref->getName());
        $data += array_map(array($this, 'serializeData'), $this->extractObjectData($value, $ref, $paramsToSerialize));
        return $data;
    }

    /**
     * Return the list of properties to be serialized
     *
     * @param ReflectionClass $ref
     * @param object $value
     * @return array
     */
    protected function getObjectProperties($ref, $value)
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
     * Extract the object data
     *
     * @param object $value
     * @param ReflectionClass $ref
     * @param array $properties
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

    /**
     * Parse the json decode to convert to objects again
     *
     * @param mixed $value
     * @return mixed
     */
    protected function unserializeData($value)
    {
        if (is_scalar($value) || $value === null) {
            return $value;
        }

        if (isset($value[static::CLASS_IDENTIFIER_KEY])) {
            return $this->unserializeObject($value);
        }

        if (!empty($value[static::CLOSURE_IDENTIFIER_KEY])) {
            if (!$this->closureSerializer) {
                throw new JsonSerializerException('Closure serializer not provided to unserialize closure');
            }
            return $this->closureSerializer->unserialize($value['value']);
        }

        return array_map(array($this, __FUNCTION__), $value);
    }

    /**
     * Convert the serialized array into an object
     *
     * @param aray $value
     * @return object
     * @throws Zumba\JsonSerializer\Exception\JsonSerializerException
     */
    protected function unserializeObject($value)
    {
        $className = $value[static::CLASS_IDENTIFIER_KEY];
        unset($value[static::CLASS_IDENTIFIER_KEY]);

        if ($className[0] === '@') {
            $index = substr($className, 1);
            return $this->objectMapping[$index];
        }

        if (!class_exists($className)) {
            throw new JsonSerializerException('Unable to find class ' . $className);
        }

        if ($className === 'DateTime') {
            $obj = $this->restoreUsingUnserialize($className, $value);
            $this->objectMapping[$this->objectMappingIndex++] = $obj;
            return $obj;
        }

        $ref = new ReflectionClass($className);
        $obj = $ref->newInstanceWithoutConstructor();
        $this->objectMapping[$this->objectMappingIndex++] = $obj;
        foreach ($value as $property => $propertyValue) {
            try {
                $propRef = $ref->getProperty($property);
                $propRef->setAccessible(true);
                $propRef->setValue($obj, $this->unserializeData($propertyValue));
            } catch (ReflectionException $e) {
                $obj->$property = $this->unserializeData($propertyValue);
            }
        }
        if (method_exists($obj, '__wakeup')) {
            $obj->__wakeup();
        }
        return $obj;
    }

    protected function restoreUsingUnserialize($className, $attributes)
    {
        $obj = (object)$attributes;
        $serialized = preg_replace('|^O:\d+:"\w+":|', 'O:' . strlen($className) . ':"' . $className . '":', serialize($obj));
        return unserialize($serialized);
    }

    /**
     * Reset variables
     *
     * @return void
     */
    protected function reset()
    {
        $this->objectStorage = new SplObjectStorage();
        $this->objectMapping = array();
        $this->objectMappingIndex = 0;
    }
}
