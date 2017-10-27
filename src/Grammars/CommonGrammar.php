<?php

namespace Finesse\QueryScribe\Grammars;

use Finesse\QueryScribe\Common\StatementInterface;
use Finesse\QueryScribe\Exceptions\InvalidQueryException;
use Finesse\QueryScribe\GrammarInterface;
use Finesse\QueryScribe\Query;
use Finesse\QueryScribe\Raw;

/**
 * A grammar that covers most common DBMS syntax features.
 *
 * @author Surgie
 */
class CommonGrammar implements GrammarInterface
{
    /**
     * {@inheritDoc}
     */
    public function compileSelect(Query $query): StatementInterface
    {
        $text = [];
        $bindings = [];

        // Select
        $text[] = 'SELECT';
        $columns = [];
        foreach ($query->select as $alias => $column) {
            $columns[] = $this->symbolToSQL($column, $bindings).(is_string($alias) ? ' AS '.$alias : '');
        }
        $text[] = implode(', ', $columns);

        // From
        if ($query->from === null) {
            throw new InvalidQueryException('The FROM table is not set');
        }
        $text[] = 'FROM '.$this->symbolToSQL($query->from, $bindings);

        // Offset and limit
        $text[] = $this->compileOffsetAndLimit($query, $bindings);

        return new Raw($this->implodeSQL($text), $bindings);
    }

    /**
     * Compiles a offset'n'limit SQL query part (if the query has it).
     *
     * @param Query $query Query data
     * @param array $bindings Bound values (array is filled by link)
     * @return string SQL text
     */
    protected function compileOffsetAndLimit(Query $query, array &$bindings): string
    {
        $parts = [];

        if ($query->offset !== null) {
            $parts[] = 'OFFSET '.$this->valueToSQL($query->offset, $bindings);
        }

        if ($query->limit !== null) {
            $parts[] = 'LIMIT '.$this->valueToSQL($query->limit, $bindings);
        }

        return $this->implodeSQL($parts);
    }

    /**
     * Converts a symbol (table, column, database, etc.) to a part of a SQL query text. Screens all the stuff.
     *
     * @param string|StatementInterface $symbol Symbol
     * @param array $bindings Bound values (array is filled by link)
     * @return string SQL text
     */
    protected function symbolToSQL($symbol, array &$bindings): string
    {
        if ($symbol instanceof StatementInterface) {
            $this->mergeBindings($bindings, $symbol->getBindings());
            return '('.$symbol->getSQL().')';
        }

        return $this->wrapSymbol($symbol);
    }

    /**
     * Converts a value to a part of a SQL query text. Actually it sends all the values to the bindings.
     *
     * @param string|StatementInterface $value Value
     * @param array $bindings Bound values (array is filled by link)
     * @return string SQL text
     */
    protected function valueToSQL($value, array &$bindings): string
    {
        if ($value instanceof StatementInterface) {
            $this->mergeBindings($bindings, $value->getBindings());
            return '('.$value->getSQL().')';
        }

        $bindings[] = $value;
        return '?';
    }

    /**
     * Wraps a symbol (table, column, database, etc.) name with quotes.
     *
     * @param string $name
     * @return string
     */
    protected function wrapSymbol(string $name): string
    {
        $components = explode('.', $name);

        foreach ($components as $index => $component) {
            $components[$index] = $this->wrapPlainSymbol($component);
        }

        return implode('.', $components);
    }

    /**
     * Wraps a plain (without dots) symbol (table, column, database, etc.) name with quotes.
     *
     * @param string $name
     * @return string
     */
    protected function wrapPlainSymbol(string $name): string
    {
        if ($name === '*') {
            return $name;
        }

        return '`'.$name.'`';
    }

    /**
     * Merges two arrays of binding values.
     *
     * @param array $target Where to add values. The values are added by link.
     * @param array $source Values to add
     */
    protected function mergeBindings(array &$target, array $source)
    {
        $targetBindingsAmount = count($target);
        $sourceBindingsIndex = 0;

        foreach ($source as $name => $value) {
            $key = is_int($name) ? $targetBindingsAmount + $sourceBindingsIndex : $name;
            $target[$key] = $value;
            $sourceBindingsIndex += 1;
        }
    }

    /**
     * Implodes parts of SQL query to a single string.
     *
     * @param string[] $parts
     * @return string
     */
    protected function implodeSQL(array $parts): string
    {
        return implode("\n", $parts);
    }
}
