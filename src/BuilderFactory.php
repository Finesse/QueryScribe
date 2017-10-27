<?php

namespace Finesse\QueryScribe;

use Finesse\QueryScribe\Common\TAddTablePrefix;
use Finesse\QueryScribe\Common\TMakeRaw;
use Finesse\QueryScribe\Grammars\CommonGrammar;

/**
 * Creates Query objects and keeps the Query dependencies.
 *
 * @author Surgie
 */
class BuilderFactory
{
    use TAddTablePrefix, TMakeRaw;

    /**
     * @var IGrammar Query to SQL converter
     */
    protected $grammar;

    /**
     * @param IGrammar|null $grammar Query to SQL converter. If null, the default converter is used.
     * @param string $tablePrefix Tables prefix
     */
    public function __construct(IGrammar $grammar = null, string $tablePrefix = '')
    {
        $this->grammar = $grammar ?? new CommonGrammar();
        $this->tablePrefix = $tablePrefix;
    }

    /**
     * Makes an empty query object.
     *
     * @return Query
     */
    public function builder(): Query
    {
        return new Query($this->grammar, $this->tablePrefix);
    }

    /**
     * Makes a query object with selected table.
     *
     * @param string $tableName The table name without quotes
     * @return Query
     */
    public function table(string $tableName): Query
    {
        return $this->builder()->from($tableName);
    }
}
