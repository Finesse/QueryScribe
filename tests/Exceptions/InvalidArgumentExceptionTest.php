<?php

namespace Finesse\QueryScribe\Tests\Exceptions;

use Finesse\QueryScribe\Exceptions\InvalidArgumentException;
use Finesse\QueryScribe\IGrammar;
use Finesse\QueryScribe\Raw;
use Finesse\QueryScribe\Tests\TestCase;

/**
 * Tests the InvalidArgumentException class
 *
 * @author Surgie
 */
class InvalidArgumentExceptionTest extends TestCase
{
    /**
     * Tests the create method
     */
    public function testCreate()
    {
        $exception = InvalidArgumentException::create('tested value', 123.456, ['string', IGrammar::class]);
        $this->assertEquals(
            'Tested value expected to be string or Finesse\QueryScribe\IGrammar, double given',
            $exception->getMessage()
        );

        $exception = InvalidArgumentException::create('Argument $foo', new Raw(''), ['foo']);
        $this->assertEquals(
            'Argument $foo expected to be foo, Finesse\QueryScribe\Raw given',
            $exception->getMessage()
        );
    }
}
