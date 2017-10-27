<?php

namespace Finesse\QueryScribe\Exceptions;

use Finesse\QueryScribe\IException;

/**
 * {@inheritDoc}
 *
 * @author Surgie
 */
class InvalidArgumentException extends \InvalidArgumentException implements IException
{
    /**
     * Makes an exception instance.
     *
     * @param string $name Value name
     * @param mixed $value Actual value
     * @param string[] $expectedTypes Expected types names
     * @return static
     */
    public static function create(string $name, $value, array $expectedTypes): self
    {
        return new static(sprintf(
            '%s expected to be %s, %s given',
            ucfirst($name),
            implode(' or ', $expectedTypes),
            is_object($value) ? get_class($value) : gettype($value)
        ));
    }
}
