<?php

namespace Zumba\Util;

use Exception;
use ReflectionClass;

class JsonSerializer {

	const CLASS_IDENTIFIER_KEY = '@type';

	/**
	 * Serialize the value in JSON
	 *
	 * @param mixed $value
	 * @return string JSON encoded
	 * @throws Exception
	 */
	public function serialize($value) {
		return json_encode($this->serializeData($value));
	}

	/**
	 * Unserialize the value from JSON
	 *
	 * @param string $value
	 * @return mixed
	 */
	public function unserialize($value) {
		return $this->unserializeData(json_decode($value, true));
	}

	/**
	 * Parse the data to be json encoded
	 *
	 * @param mixed $value
	 * @return mixed
	 * @throws Exception
	 */
	protected function serializeData($value) {
		if (is_scalar($value) || $value === null) {
			return $value;
		}
		if (is_resource($value)) {
			throw new Exception('Resource is not supported in JsonSerializer');
		}
		if (is_array($value)) {
			return array_map(array($this, __FUNCTION__), $value);
		}
		if (is_object($value)) {
			if ($value instanceof \Closure) {
				throw new Exception('Closures are not supported in JsonSerializer');
			}
			return $this->serializeObject($value);
		}
		throw new Exception('Not supported');
	}

	/**
	 * Extract the data from an object
	 *
	 * @param object $value
	 * @return array
	 */
	protected function serializeObject($value) {
		$ref = new ReflectionClass($value);

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
		if ($value instanceof Serializable) {
			return $value->__sleep();
		}

		$props = array();
		foreach ($ref->getProperties() as $prop) {
			$props[] = $prop->getName();
		}
		return $props;
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
			$propRef = $ref->getProperty($property);
			$propRef->setAccessible(true);
			$data[$property] = $propRef->getValue($value);
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
		if (is_array($value) && !isset($value[static::CLASS_IDENTIFIER_KEY])) {
			return array_map(array($this, __FUNCTION__), $value);
		}
		// @todo implement
		throw new Exception('Not implemented');
	}

}
