<?php

namespace Zumba\Contracts;

interface EntitySerializer
{
    /**
     * @return string
     */
    public function getType();

    /**
     * @return array
     */
    public function serialize($object);

    /**
     * @param array $data
     * @return mixed
     */
    public function unserialize($data);
}
