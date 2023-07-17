<?php

namespace Zumba\JsonSerializer\ClosureSerializer;

use Closure;
use SuperClosure\SerializerInterface as SuperClosureSerializerInterface;

class SuperClosureSerializer implements ClosureSerializer {

    /**
     * Closure serializer instance
     *
     * @var SuperClosureSerializerInterface
     */
    protected $serializer;

    /**
     * Closure serializer instance
     *
     * @var SuperClosureSerializerInterface
     */
    public function __construct(SuperClosureSerializerInterface $serializer)
    {
        $this->serializer = $serializer;
    }

    /**
     * Serialize a closure
     *
     * @param Closure $closure
     * @return string
     */
    public function serialize(Closure $closure)
    {
        return $this->serializer->serialize($closure);
    }

    /**
     * Unserialize a closure
     *
     * @param string $serialized
     * @return Closure
     */
    public function unserialize($serialized)
    {
        return $this->serializer->unserialize($serialized);
    }

}
