<?php

namespace Zumba\JsonSerializer\EntitySerializers;

use Zumba\Contracts\EntitySerializer as Contract;

class EntitySerializer implements Contract
{
    /**
     * @var string
     */
    protected $type;

    /**
     * @var \Closure
     */
    protected $serializeClosure;

    /**
     * @var \Closure
     */
    protected $unserializeClosure;

    /**
     * EntitySerializer constructor.
     * @param string $type
     * @param \Closure $serializeClosure
     * @param \Closure $unserializeClosure
     */
    public function __construct($type, \Closure $serializeClosure, \Closure $unserializeClosure)
    {
        $this->type = $type;
        $this->serializeClosure = $serializeClosure;
        $this->unserializeClosure = $unserializeClosure;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param $object
     * @return mixed
     */
    public function serialize($object)
    {
        return $this->serializeClosure($object);
    }

    /**
     * @param array $data
     * @return mixed
     */
    public function unserialize($data)
    {
        return $this->unserializeClosure($data);
    }
}
