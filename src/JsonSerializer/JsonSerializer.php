<?php

namespace Zumba\JsonSerializer;

use ReflectionClass;
use ReflectionException;
use SplObjectStorage;
use SuperClosure\SerializerInterface as ClosureSerializerInterface;
use Zumba\Contracts\EntitySerializer;
use Zumba\JsonSerializer\EntitySerializers\ClosureEntitySerializer;
use Zumba\JsonSerializer\EntitySerializers\DateTimeEntitySerializer;
use Zumba\JsonSerializer\EntitySerializers\SplDoublyLinkedListEntitySerializer;
use Zumba\JsonSerializer\Exception\JsonSerializerException;

class JsonSerializer
{
    const CLASS_IDENTIFIER_KEY = '@type';
    const CLOSURE_IDENTIFIER_KEY = '@closure';
    const UTF8ENCODED_IDENTIFIER_KEY = '@utf8encoded';
    const SCALAR_IDENTIFIER_KEY = '@scalar';
    const FLOAT_ADAPTER = 'JsonSerializerFloatAdapter';

    const KEY_UTF8ENCODED = 1;
    const VALUE_UTF8ENCODED = 2;

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
    protected $objectMapping = [];

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
     * Map of custom object serializers
     *
     * @var array
     */
    protected $customObjectSerializerMap;

    /**
     * JsonSerializer constructor.
     * @param ClosureSerializerInterface|null $closureSerializer
     */
    public function __construct(ClosureSerializerInterface $closureSerializer = null)
    {
        $this->preserveZeroFractionSupport = defined('JSON_PRESERVE_ZERO_FRACTION');
        $this->registerEntitySerializer(new DateTimeEntitySerializer());
        $this->registerEntitySerializer(new SplDoublyLinkedListEntitySerializer());
        if ($closureSerializer != null) {
            $this->registerEntitySerializer(new ClosureEntitySerializer($closureSerializer));
        }

    }

    /**
     * @param $serializer
     * @return JsonSerializer
     */
    public function registerEntitySerializer(EntitySerializer $serializer)
    {
        $this->customObjectSerializerMap[$serializer->getType()] = $serializer;

        return $this;
    }

    /**
     * @param $entity
     * @return EntitySerializer|null
     */
    protected function resolveEntitySerializer($entity)
    {
        if (($serialiser = $this->resolveEntitySerializerByDirectClassName($entity)) != null) {
            return $serialiser;
        }

        if (($serialiser = $this->resolveEntitySerializerByDirectClassName($entity)) != null) {
            return $serialiser;
        }
    }

    /**
     * @param $className
     * @return EntitySerializer|null
     */
    protected function resolveEntitySerializerByDirectClassName($className)
    {
        if (is_object($className)) {
            $className = get_class($className);
        }

        if (is_string($className) && class_exists($className)) {
            foreach ($this->customObjectSerializerMap as $type => $serialiser) {
                if ($type == $className) {
                    return $serialiser;
                }
            }
        }
    }

    /**
     * @param $object
     * @return EntitySerializer|null
     */
    protected function resolveEntitySerializerByInheritance($object)
    {
        if (is_string($object) && class_exists($object)) {
            list($ref, $object) = $this->getObjectInstance($object);
        }

        foreach ($this->customObjectSerializerMap as $type => $serialiser) {
            if ($object instanceof $type) {
                return $serialiser;
            }
        }
    }

    /**
     * Serialize the value in JSON
     *
     * @param mixed $value
     * @return string JSON encoded
     * @throws JsonSerializerException
     */
    public function serialize($value)
    {
        $this->reset();
        $serializedData = $this->serializeData($value);
        $encoded = json_encode($serializedData, $this->calculateEncodeOptions());
        if ($encoded === false || json_last_error() != JSON_ERROR_NONE) {
            if (json_last_error() != JSON_ERROR_UTF8) {
                throw new JsonSerializerException('Invalid data to encode to JSON. Error: ' . json_last_error());
            }

            $serializedData = $this->encodeNonUtf8ToUtf8($serializedData);
            $encoded = json_encode($serializedData, $this->calculateEncodeOptions());

            if ($encoded === false || json_last_error() != JSON_ERROR_NONE) {
                throw new JsonSerializerException('Invalid data to encode to JSON. Error: ' . json_last_error());
            }
        }
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
     * @param mixed $serializedData
     *
     * @return array
     */
    protected function encodeNonUtf8ToUtf8($serializedData)
    {
        if (is_string($serializedData)) {
            if (!mb_check_encoding($serializedData, 'UTF-8')) {
                $serializedData = [
                    static::SCALAR_IDENTIFIER_KEY      => mb_convert_encoding($serializedData, 'UTF-8', '8bit'),
                    static::UTF8ENCODED_IDENTIFIER_KEY => static::VALUE_UTF8ENCODED,
                ];
            }

            return $serializedData;
        }

        $encodedKeys = [];
        $encodedData = [];
        foreach ($serializedData as $key => $value) {
            if (is_array($value)) {
                $value = $this->encodeNonUtf8ToUtf8($value);
            }

            if (!mb_check_encoding($key, 'UTF-8')) {
                $key = mb_convert_encoding($key, 'UTF-8', '8bit');
                $encodedKeys[$key] = (isset($encodedKeys[$key]) ? $encodedKeys[$key] : 0) | static::KEY_UTF8ENCODED;
            }

            if (is_string($value)) {
                if (!mb_check_encoding($value, 'UTF-8')) {
                    $value = mb_convert_encoding($value, 'UTF-8', '8bit');
                    $encodedKeys[$key] = (isset($encodedKeys[$key]) ? $encodedKeys[$key] : 0) | static::VALUE_UTF8ENCODED;
                }
            }

            $encodedData[$key] = $value;
        }

        if ($encodedKeys) {
            $encodedData[self::UTF8ENCODED_IDENTIFIER_KEY] = $encodedKeys;
        }

        return $encodedData;
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
     * Parse the data to be json encoded
     *
     * @param mixed $value
     * @return mixed
     * @throws JsonSerializerException
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
        } elseif (is_resource($value)) {
            throw new JsonSerializerException('Resource is not supported in JsonSerializer');
        } elseif (is_array($value)) {
            return array_map([$this, __FUNCTION__], $value);
        } elseif ($value instanceof \Closure) {
            return $this->serializeClosure($value);
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
        if ($this->objectStorage->contains($value)) {
            return [static::CLASS_IDENTIFIER_KEY => '@' . $this->objectStorage[$value]];
        }

        $this->objectStorage->attach($value, $this->objectMappingIndex++);
        $ref = new ReflectionClass($value);
        $className = $ref->getName();

        if (($serializer = $this->resolveEntitySerializer($value)) != null) {
            $data = [static::CLASS_IDENTIFIER_KEY => $className];
            $data += $serializer->serialize($value);
            return $data;
        }

        $paramsToSerialize = $this->getObjectProperties($ref, $value);
        $data = [static::CLASS_IDENTIFIER_KEY => $className];
        $data += array_map([$this, 'serializeData'], $this->extractObjectData($value, $ref, $paramsToSerialize));
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

        $props = [];
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
        $data = [];
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

        if (mb_strpos($value, static::UTF8ENCODED_IDENTIFIER_KEY) !== false) {
            $data = $this->decodeNonUtf8FromUtf8($data);
        }

        return $this->unserializeData($data);
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
        } elseif (isset($value[static::CLASS_IDENTIFIER_KEY])) {
            return $this->unserializeObject($value);
        } elseif (!empty($value[self::CLOSURE_IDENTIFIER_KEY])) {
            return $this->unserializeClosure($value);
        }

        return array_map([$this, __FUNCTION__], $value);
    }

    /**
     * @param mixed $serializedData
     *
     * @return mixed
     */
    protected function decodeNonUtf8FromUtf8($serializedData)
    {
        if (is_array($serializedData) && isset($serializedData[static::SCALAR_IDENTIFIER_KEY])) {
            $serializedData = mb_convert_encoding($serializedData[static::SCALAR_IDENTIFIER_KEY], '8bit', 'UTF-8');
            return $serializedData;
        } elseif (is_scalar($serializedData) || $serializedData === null) {
            return $serializedData;
        }

        $encodedKeys = [];
        if (isset($serializedData[static::UTF8ENCODED_IDENTIFIER_KEY])) {
            $encodedKeys = $serializedData[static::UTF8ENCODED_IDENTIFIER_KEY];
            unset($serializedData[static::UTF8ENCODED_IDENTIFIER_KEY]);
        }

        $decodedData = [];
        foreach ($serializedData as $key => $value) {
            if (is_array($value)) {
                $value = $this->decodeNonUtf8FromUtf8($value);
            }

            if (isset($encodedKeys[$key])) {
                $originalKey = $key;
                if ($encodedKeys[$key] & static::KEY_UTF8ENCODED) {
                    $key = mb_convert_encoding($key, '8bit', 'UTF-8');
                }
                if ($encodedKeys[$originalKey] & static::VALUE_UTF8ENCODED) {
                    $value = mb_convert_encoding($value, '8bit', 'UTF-8');
                }
            }

            $decodedData[$key] = $value;
        }

        return $decodedData;
    }

    /**
     * Convert the serialized array into an object
     *
     * @param array $value
     * @return object
     * @throws JsonSerializerException
     */
    protected function unserializeObject($value)
    {
        $className = $value[static::CLASS_IDENTIFIER_KEY];
        unset($value[static::CLASS_IDENTIFIER_KEY]);

        if ($className[0] === '@') {
            $index = substr($className, 1);
            return $this->objectMapping[$index];
        } elseif (!class_exists($className)) {
            throw new JsonSerializerException('Unable to find class ' . $className);
        } elseif (($serialier = $this->resolveEntitySerializer($className)) != null) {
            $obj = $serialier->unserialize($value);
            $this->objectMapping[$this->objectMappingIndex++] = $obj;
            return $obj;
        }

        list($ref, $object) = $this->getObjectInstance($className);
        $this->objectMapping[$this->objectMappingIndex++] = $object;
        foreach ($value as $property => $propertyValue) {
            try {
                $propRef = $ref->getProperty($property);
                $propRef->setAccessible(true);
                $propRef->setValue($object, $this->unserializeData($propertyValue));
            } catch (ReflectionException $e) {
                $object->$property = $this->unserializeData($propertyValue);
            }
        }

        if (method_exists($object, '__wakeup')) {
            $object->__wakeup();
        }
        return $object;
    }

    /**
     * @param $className
     * @return object
     */
    protected function getObjectInstance($className)
    {
        $ref = new ReflectionClass($className);
        return [$ref, $ref->newInstanceWithoutConstructor()];
    }

    /**
     * @param $value
     * @return array
     */
    protected function serializeClosure($value)
    {
        if (($serializer = $this->resolveEntitySerializer(\Closure::class)) == null) {
            throw new JsonSerializerException('Closure serializer not provided to unserialize closure');
        }
        return $serializer->serialize($value);
    }

    /**
     * @param $value
     * @return mixed
     */
    protected function unserializeClosure($value)
    {
        if (($serializer = $this->resolveEntitySerializer(\Closure::class)) == null) {
            throw new JsonSerializerException('Closure serializer not provided to unserialize closure');
        }
        return $serializer->unserialize($value);
    }

    /**
     * Reset variables
     *
     * @return void
     */
    protected function reset()
    {
        $this->objectStorage = new SplObjectStorage();
        $this->objectMapping = [];
        $this->objectMappingIndex = 0;
    }
}
