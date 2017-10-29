<?php

namespace Finesse\QueryScribe;

use Finesse\QueryScribe\StatementInterface;

/**
 * Represents a raw SQL subquery.
 *
 * @author Surgie
 */
class Raw implements StatementInterface
{
    /**
     * @var string SQL statement
     */
    protected $query;

    /**
     * @var array Values to bind
     */
    protected $bindings;

    /**
     * @param string $query SQL statement
     * @param array $bindings Values to bind to the statement
     */
    public function __construct(string $query, array $bindings = [])
    {
        $this->query = $query;
        $this->bindings = $bindings;
    }

    /**
     * {@inheritDoc}
     */
    public function getSQL(): string
    {
        return $this->query;
    }

    /**
     * {@inheritDoc}
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }
}
