<?php

namespace Zumba\Util\Test\SupportClasses;

class TraversableClass implements \Iterator
{
    /**
     * @var int
     */
    private $position = 0;
    /**
     * @var array
     */
    private $array = array(
        "firstelement",
        "secondelement",
        "lastelement",
    );

    /**
     *
     */
    public function __construct() {
        $this->position = 0;
    }

    /**
     *
     */
    public function rewind() {
        $this->position = 0;
    }

    /**
     * @return mixed
     */
    public function current() {
        return $this->array[$this->position];
    }

    /**
     * @return int
     */
    public function key() {
        return $this->position;
    }

    /**
     *
     */
    public function next() {
        ++$this->position;
    }

    /**
     * @return bool
     */
    public function valid() {
        return isset($this->array[$this->position]);
    }
}