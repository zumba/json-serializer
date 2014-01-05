<?php

namespace Zumba\Util;

class JsonSerializer {

	const CLASS_IDENTIFIER_KEY = '@type';

	/**
	 * Serialize the value in JSON
	 *
	 * @param mixed $value
	 * @return string JSON encoded
	 */
	public function serialize($value) {
		return json_encode($this->_serializeData($value));
	}

	/**
	 * Unserialize the value from JSON
	 *
	 * @param string $value
	 * @return mixed
	 */
	public function unserialize($value) {
		return $this->_unserializeData(json_decode($value, true));
	}

	/**
	 * Parse the data to be json encoded
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	protected function _serializeData($value) {
		if (is_scalar($value) || $value === null) {
			return $value;
		}
		// @todo implement
		throw new \Exception('Not implemented');
	}

	/**
	 * Parse the json decode to convert to objects again
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	protected function _unserializeData($value) {
		if (is_scalar($value) || $value === null) {
			return $value;
		}
		// @todo implement
		throw new \Exception('Not implemented');
	}

}
