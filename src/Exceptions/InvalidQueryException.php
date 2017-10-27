<?php

namespace Finesse\QueryScribe\Exceptions;

use Finesse\QueryScribe\IException;

/**
 * The query object has incorrect content.
 *
 * @author Surgie
 */
class InvalidQueryException extends \RuntimeException implements IException {}
