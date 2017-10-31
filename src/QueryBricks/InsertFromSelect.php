<?php

namespace Finesse\QueryScribe\QueryBricks;

use Finesse\QueryScribe\Query;
use Finesse\QueryScribe\StatementInterface;

/**
 * Represents an insert statement with takes rows from a select query.
 *
 * @author Surgie
 */
class InsertFromSelect
{
    /**
     * @var string[]|null Columns of a target table. If null, the list of columns is omitted.
     */
    public $columns;

    /**
     * @var Query|StatementInterface The select query
     */
    public $selectQuery;

    /**
     * @param string[]|null $columns Columns of a target table. If null, the list of columns is omitted.
     * @param Query|StatementInterface $selectQuery The select query
     */
    public function __construct(array $columns = null, $selectQuery)
    {
        $this->columns = $columns;
        $this->selectQuery = $selectQuery;
    }
}
