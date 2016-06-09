<?php

namespace Zumba\JsonSerializer\Test\SupportClasses;

class MyTypeSerializer
{
    public function serialize(MyType $obj)
    {
        return array('fields' => $obj->field1 . ' ' . $obj->field2);
    }

    public function unserialize($values)
    {
        list($field1, $field2) = explode(' ', $values['fields']);
        $obj = new MyType();
        $obj->field1 = $field1;
        $obj->field2 = $field2;
        return $obj;
    }
}
