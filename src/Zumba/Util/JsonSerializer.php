<?php

namespace Zumba\Util;

use Exception;

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
		// @todo implement
		throw new Exception('Not implemented');
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
