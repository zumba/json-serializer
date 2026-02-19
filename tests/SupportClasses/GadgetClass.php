<?php

namespace Zumba\JsonSerializer\Test\SupportClasses;

/**
 * Simulates a "gadget" class that an attacker could abuse via insecure
 * deserialization. The static flags let tests assert that the dangerous
 * magic methods were never triggered when the class is not in the allowlist.
 */
class GadgetClass
{
    public static bool $wakeupCalled = false;
    public static bool $destructCalled = false;

    public string $command = 'id';

    public function __wakeup(): void
    {
        self::$wakeupCalled = true;
    }

    public function __destruct()
    {
        self::$destructCalled = true;
    }
}
