<?php

namespace Zumba\JsonSerializer\EntitySerializers;

use Zumba\Contracts\EntitySerializer;

class DateTimeEntitySerializer implements EntitySerializer
{
    const FORMAT = \DateTime::ATOM;

    /**
     * @return string
     */
    public function getType()
    {
        return 'DateTime';
    }

    /**
     * @param $object
     * @return array
     */
    public function serialize($object)
    {
        return [
            'time'     => $object->format(self::FORMAT),
            'timezone' => $object->getTimezone()->getName()
        ];
    }

    /**
     * @param array $data
     * @return \DateTime
     */
    public function unserialize($data)
    {
        return \DateTime::createFromFormat(self::FORMAT, $data['time'], new \DateTimeZone($data['timezone']));
    }
}
