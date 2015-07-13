<?php

namespace Zumba\Util\Test\SupportClasses;

class MagicClass {

	public $show = true;
	public $hide = true;
	public $woke = false;

	public function __sleep() {
		return array('show');
	}

	public function __wakeup() {
		$this->woke = true;
	}

}
