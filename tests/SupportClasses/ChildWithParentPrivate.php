<?php

namespace Zumba\JsonSerializer\Test\SupportClasses;

class ParentWithPrivate
{
    private $parentPrivate = 'parentPrivateValue';

    public $parentPublic = 'parentPublicValue';

    public function getParentPrivate()
    {
        return $this->parentPrivate;
    }

    public function setParentPrivate($value)
    {
        $this->parentPrivate = $value;
    }
}

class ChildWithParentPrivate extends ParentWithPrivate
{
    public $childPublic = 'childPublicValue';

    private $childPrivate = 'childPrivateValue';
}
