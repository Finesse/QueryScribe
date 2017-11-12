<?php

namespace Finesse\QueryScribe\Grammars;

use Finesse\QueryScribe\QueryBricks\Criteria\ValueCriterion;
use Finesse\QueryScribe\QueryBricks\Criterion;

/**
 * A grammar that compiles queries to the MySQL SQL dialect
 *
 * @author Surgie
 */
class MySQLGrammar extends CommonGrammar
{
    /**
     * {@inheritDoc}
     */
    public function quoteIdentifier(string $name): string
    {
        return '`'.str_replace('`', '``', $name).'`';
    }

    /**
     * {@inheritDoc}
     */
    protected function compileCriterion(Criterion $criterion, array &$bindings): string
    {
        if ($criterion instanceof ValueCriterion) {
            return sprintf(
                '%s %s %s',
                $this->compileIdentifier($criterion->column, $bindings),
                $criterion->rule,
                $this->compileValue($criterion->value, $bindings)
            );
        }

        return parent::compileCriterion($criterion, $bindings);
    }

    /**
     * {@inheritDoc}
     */
    protected function compileOneOrder($order, array &$bindings): string
    {
        if ($order === 'random') {
            return 'RAND()';
        }

        return parent::compileOneOrder($order, $bindings);
    }
}
