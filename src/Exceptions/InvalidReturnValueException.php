<?php

namespace Finesse\QueryScribe\Exceptions;

/**
 * Error: a callable has returned a wrong value.
 *
 * @author Surgie
 */
class InvalidReturnValueException extends \LogicException implements ExceptionInterface
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
            '%s expected to be %s, %s returned',
            ucfirst($name),
            implode(' or ', $expectedTypes),
            is_object($value) ? get_class($value) : gettype($value)
        ));
    }
}
