<?php

namespace Finesse\QueryScribe;

use Finesse\QueryScribe\Common\AddTablePrefixTrait;
use Finesse\QueryScribe\Common\MakeRawTrait;
use Finesse\QueryScribe\Grammars\CommonGrammarInterface;

/**
 * Creates Query objects and keeps the Query dependencies.
 *
 * @author Surgie
 */
class BuilderFactory
{
    use AddTablePrefixTrait, MakeRawTrait;

    /**
     * @var GrammarInterface Query to SQL converter
     */
    protected $grammar;

    /**
     * @param GrammarInterface|null $grammar Query to SQL converter. If null, the default converter is used.
     * @param string $tablePrefix Tables prefix
     */
    public function __construct(GrammarInterface $grammar = null, string $tablePrefix = '')
    {
        $this->grammar = $grammar ?? new CommonGrammarInterface();
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
