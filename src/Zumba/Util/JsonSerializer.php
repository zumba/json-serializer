<?php

namespace Zumba\Util;

use ReflectionClass;
use ReflectionException;
use SplObjectStorage;
use Zumba\Exception\JsonSerializerException;

class JsonSerializer {

	const CLASS_IDENTIFIER_KEY = '@type';
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
	 * Constructor.
	 */
	public function __construct() {
		$this->preserveZeroFractionSupport = defined('JSON_PRESERVE_ZERO_FRACTION');
	}

	/**
	 * Serialize the value in JSON
	 *
	 * @param mixed $value
	 * @return string JSON encoded
	 * @throws Zumba\Exception\JsonSerializerException
	 */
	public function serialize($value) {
		$this->reset();
		$encoded = json_encode($this->serializeData($value), $this->calculateEncodeOptions());
		return $this->processEncodedValue($encoded);
	}

	/**
	 * Calculate encoding options
	 *
	 * @return integer
	 */
	protected function calculateEncodeOptions() {
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
	protected function processEncodedValue($encoded) {
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
	public function unserialize($value) {
		$this->reset();
		return $this->unserializeData(json_decode($value, true));
	}

	/**
	 * Parse the data to be json encoded
	 *
	 * @param mixed $value
	 * @return mixed
	 * @throws Zumba\Exception\JsonSerializerException
	 */
	protected function serializeData($value) {
		if (is_scalar($value) || $value === null) {
			if (!$this->preserveZeroFractionSupport && is_float($value) && strpos((string)$value, '.') === false) {
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
			throw new JsonSerializerException('Closures are not supported in JsonSerializer');
		}
		return $this->serializeObject($value);
	}

	/**
	 * Extract the data from an object
	 *
	 * @param object $value
	 * @return array
	 */
	protected function serializeObject($value) {
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
	protected function getObjectProperties($ref, $value) {
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
	protected function extractObjectData($value, $ref, $properties) {
		$data = array();
		foreach ($properties as $property) {
			try {
				$propRef = $ref->getProperty($property);
				$propRef->setAccessible(true);
				$data[$property] = $propRef->getValue($value);
			} catch (ReflectionException $e) {
				if(isset($value->$property)) {
					$data[$property] = $value->$property;
				}
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
	protected function unserializeData($value) {
		if (is_scalar($value) || $value === null) {
			return $value;
		}
		return isset($value[static::CLASS_IDENTIFIER_KEY]) ?
			$this->unserializeObject($value) :
			array_map(array($this, __FUNCTION__), $value);
	}

	/**
	 * Convert the serialized array into an object
	 *
	 * @param aray $value
	 * @return object
	 * @throws Zumba\Exception\JsonSerializerException
	 */
	protected function unserializeObject($value) {
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

	protected function restoreUsingUnserialize($className, $attributes) {
		$obj = (object)$attributes;
		$serialized = preg_replace('|^O:\d+:"\w+":|', 'O:' . strlen($className) . ':"' . $className . '":', serialize($obj));
		return unserialize($serialized);
	}

	/**
	 * Reset variables
	 *
	 * @return void
	 */
	protected function reset() {
		$this->objectStorage = new SplObjectStorage();
		$this->objectMapping = array();
		$this->objectMappingIndex = 0;
	}

}
