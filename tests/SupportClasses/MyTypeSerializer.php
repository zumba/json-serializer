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
        return 'Zumba\JsonSerializer\Test\SupportClasses\MyType';
    }

    /**
     * @param $obj
     * @return array
     */
    public function serialize($obj)
    {
        return ['field1' => $obj->field1 , 'field2' => $obj->field2];
    }

    /**
     * @param array $values
     * @return MyType
     */
    public function unserialize($values)
    {
        $obj = new MyType();
        $obj->field1 = $values['field1'];
        $obj->field2 = $values['field2'];
        return $obj;
    }
}
