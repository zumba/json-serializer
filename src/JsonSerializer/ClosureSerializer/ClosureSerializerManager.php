<?php

namespace Zumba\JsonSerializer\ClosureSerializer;

class ClosureSerializerManager {

    /**
     * Closure serializer instances
     *
     * @var array
     */
    protected $closureSerializer = array();

    /**
     * Prefered closure serializer
     */
    protected $preferred = array(
        SuperClosureSerializer::class
    );

    /**
     * Set closure engine
     *
     * @param ClosureSerializer $closureSerializer
     * @return self
     */
    public function addSerializer(ClosureSerializer $closureSerializer)
    {
        $classname = $closureSerializer::class;
        $this->closureSerializer[$classname] = $closureSerializer;
        return $this;
    }

    /**
     * Get preferred closure serializer
     *
     * @return ClosureSerializer|null
     */
    public function getPreferredSerializer()
    {
        if (empty($this->closureSerializer)) {
            return null;
        }

        foreach ($this->preferred as $preferred) {
            if (isset($this->closureSerializer[$preferred])) {
                return $this->closureSerializer[$preferred];
            }
        }
        return current($this->closureSerializer);
    }

    public function getSerializer(string $classname)
    {
        if (isset($this->closureSerializer[$classname])) {
            return $this->closureSerializer[$classname];
        }
        return null;
    }
}
