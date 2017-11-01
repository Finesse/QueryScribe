<?php

namespace Finesse\QueryScribe\Tests\Exceptions;

use Finesse\QueryScribe\Exceptions\InvalidReturnValueException;
use Finesse\QueryScribe\GrammarInterface;
use Finesse\QueryScribe\Raw;
use Finesse\QueryScribe\Tests\TestCase;

/**
 * Tests the InvalidReturnValueException class
 *
 * @author Surgie
 */
class InvalidReturnValueExceptionTest extends TestCase
{
    /**
     * Tests the create method
     */
    public function testCreate()
    {
        $exception = InvalidReturnValueException::create('tested value', 123.456, ['string', GrammarInterface::class]);
        $this->assertEquals(
            'Tested value expected to be string or Finesse\QueryScribe\GrammarInterface, double returned',
            $exception->getMessage()
        );

        $exception = InvalidReturnValueException::create('Closure return', new Raw(''), ['foo']);
        $this->assertEquals(
            'Closure return expected to be foo, Finesse\QueryScribe\Raw returned',
            $exception->getMessage()
        );
    }
}
