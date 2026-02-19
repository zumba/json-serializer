<?php

namespace Zumba\JsonSerializer\ClosureSerializer;

use Closure;
use Opis\Closure\SerializableClosure as OpisSerializableClosure;

class OpisClosureSerializer implements ClosureSerializer {

    /**
     * Serialize a closure
     *
     * @param Closure $closure
     * @return string
     */
    public function serialize(Closure $closure)
    {
        if (function_exists('Opis\Closure\serialize')) {
            return \Opis\Closure\serialize($closure);
        }
        return serialize(new OpisSerializableClosure($closure));
    }

    /**
     * Unserialize a closure
     *
     * @param string $serialized
     * @return Closure
     */
    public function unserialize($serialized)
    {
        if (function_exists('Opis\Closure\unserialize')) {
            return \Opis\Closure\unserialize($serialized);
        }
        return unserialize($serialized)->getClosure();
    }

}
