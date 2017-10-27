<?php

namespace Finesse\QueryScribe\Exceptions;

use Finesse\QueryScribe\ExceptionInterface;

/**
 * The query object has incorrect content.
 *
 * @author Surgie
 */
class InvalidQueryException extends \RuntimeException implements ExceptionInterface {}
