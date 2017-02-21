<?php

namespace Zumba\JsonSerializer\EntitySerializers;

use SuperClosure\SerializerInterface as ClosureSerializerInterface;
use Zumba\Contracts\EntitySerializer;
use Zumba\JsonSerializer\JsonSerializer;

class ClosureEntitySerializer implements EntitySerializer
{
    /**
     * @var ClosureSerializerInterface
     */
    protected $closureSerializer;

    /**
     * ClosureEntitySerializer constructor.
     * @param $closureSerializer
     */
    public function __construct(ClosureSerializerInterface $closureSerializer)
    {
        $this->closureSerializer = $closureSerializer;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return \Closure::class;
    }

    /**
     * @param \Closure $object
     * @return array
     */
    public function serialize($object)
    {
        return [
            JsonSerializer::CLOSURE_IDENTIFIER_KEY => true,
            'value'                                => $this->closureSerializer->serialize($object)
        ];
    }

    /**
     * @param array $data
     * @return \Closure
     */
    public function unserialize($data)
    {
        return $this->closureSerializer->unserialize($data['value']);
    }

}
