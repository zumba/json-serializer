<?php

namespace Zumba\JsonSerializer\Test\SupportClasses;

class UninitializedTypedProperty
{

    public string $name = 'test';
    private \stdClass $expensiveObject;

    public function getExpensiveObject(): \stdClass
    {
        if (!isset($this->expensiveObject)) {
            $this->expensiveObject = new \stdClass();
        }
        return $this->expensiveObject;
    }
}
