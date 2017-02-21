<?php

namespace Zumba\JsonSerializer\EntitySerializers;

use Zumba\Contracts\EntitySerializer;

class SplDoublyLinkedListEntitySerializer implements EntitySerializer
{
    /**
     * @return string
     */
    public function getType()
    {
        return 'SplDoublyLinkedList';
    }

    /**
     * @param $object
     * @return array
     */
    public function serialize($object)
    {
        return ['value' => $object->serialize()];
    }

    /**
     * @param array $data
     * @return \SplDoublyLinkedList
     */
    public function unserialize($data)
    {
        $list = new \SplDoublyLinkedList();
        $list->unserialize($data['value']);
        return $list;
    }
}
