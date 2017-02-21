<?php

namespace Zumba\JsonSerializer\Test\SupportClasses;

use Zumba\Contracts\EntitySerializer;

class MyTypeSerializer implements EntitySerializer
{
    /**
     * @return string
     */
    public function getType()
    {
        return MyType::class;
    }

    /**
     * @param $obj
     * @return array
     */
    public function serialize($obj)
    {
        return ['fields' => $obj->field1 . ' ' . $obj->field2];
    }

    /**
     * @param array $values
     * @return MyType
     */
    public function unserialize($values)
    {
        list($field1, $field2) = explode(' ', $values['fields']);
        $obj = new MyType();
        $obj->field1 = $field1;
        $obj->field2 = $field2;
        return $obj;
    }
}
