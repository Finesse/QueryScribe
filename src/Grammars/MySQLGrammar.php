<?php

namespace Finesse\QueryScribe\Grammars;

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
    public function quotePlainIdentifier(string $name): string
    {
        return '`'.str_replace('`', '``', $name).'`';
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
