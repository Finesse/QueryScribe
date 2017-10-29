<?php

namespace Finesse\QueryScribe\Grammars;

use Finesse\QueryScribe\QueryBricks\Aggregate;
use Finesse\QueryScribe\StatementInterface;
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
    public function compile(Query $query): StatementInterface
    {
        return $this->compileSelect($query);
    }

    /**
     * {@inheritDoc}
     */
    public function compileSelect(Query $query): StatementInterface
    {
        $bindings = [];
        $sql = [
            $this->compileSelectPart($query, $bindings),
            $this->compileFromPart($query, $bindings),
            $this->compileOffsetAndLimitPart($query, $bindings)
        ];

        return new Raw($this->implodeSQL($sql), $bindings);
    }

    /**
     * Compiles a SELECT part of a SQL query.
     *
     * @param Query $query Query data
     * @param array $bindings Bound values (array is filled by link)
     * @return string SQL text
     */
    protected function compileSelectPart(Query $query, array &$bindings): string
    {
        $columns = [];

        foreach (($query->select ?: ['*']) as $alias => $column) {
            if ($column instanceof Aggregate) {
                $column = $this->aggregateToSQL($column, $bindings);
            } else {
                $column = $this->symbolToSQL($column, $bindings);
            }

            $columns[] = $column.(is_string($alias) ? ' AS '.$this->wrapPlainSymbol($alias) : '');
        }

        return 'SELECT '.implode(', ', $columns);
    }

    /**
     * Compiles a FROM part of a SQL query.
     *
     * @param Query $query Query data
     * @param array $bindings Bound values (array is filled by link)
     * @return string SQL text
     * @throws InvalidQueryException
     */
    protected function compileFromPart(Query $query, array &$bindings): string
    {
        if ($query->from === null) {
            throw new InvalidQueryException('The FROM table is not set');
        }

        return 'FROM '.$this->symbolToSQL($query->from, $bindings)
            . ($query->fromAlias === null ? '' : ' AS '.$this->wrapPlainSymbol($query->fromAlias));
    }

    /**
     * Compiles a offset'n'limit SQL query part (if the query has it).
     *
     * @param Query $query Query data
     * @param array $bindings Bound values (array is filled by link)
     * @return string SQL text
     */
    protected function compileOffsetAndLimitPart(Query $query, array &$bindings): string
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
     * @param string|Query|StatementInterface $symbol Symbol
     * @param array $bindings Bound values (array is filled by link)
     * @return string SQL text
     */
    protected function symbolToSQL($symbol, array &$bindings): string
    {
        if ($symbol instanceof Query || $symbol instanceof StatementInterface) {
            return $this->subQueryToSQL($symbol, $bindings);
        }

        return $this->wrapSymbol($symbol);
    }

    /**
     * Converts a value to a part of a SQL query text. Actually it sends all the values to the bindings.
     *
     * @param mixed|Query|\Finesse\QueryScribe\StatementInterface $value Value (a scalar value or a subquery)
     * @param array $bindings Bound values (array is filled by link)
     * @return string SQL text
     */
    protected function valueToSQL($value, array &$bindings): string
    {
        if ($value instanceof Query || $value instanceof StatementInterface) {
            return $this->subQueryToSQL($value, $bindings);
        }

        $this->mergeBindings($bindings, [$value]);
        return '?';
    }

    /**
     * Converts a subquery to a SQL query text.
     *
     * @param Query|StatementInterface $subQuery Subquery
     * @param array $bindings Bound values (array is filled by link)
     * @return string SQL text
     */
    protected function subQueryToSQL($subQuery, array &$bindings): string
    {
        if ($subQuery instanceof Query) {
            $subQuery = $this->compile($subQuery);
        }

        $this->mergeBindings($bindings, $subQuery->getBindings());
        return '('.$subQuery->getSQL().')';
    }

    /**
     * Converts an aggregate object to a SQL query text.
     *
     * @param Aggregate $aggregate Aggregate
     * @param array $bindings Bound values (array is filled by link)
     * @return string SQL text
     */
    protected function aggregateToSQL(Aggregate $aggregate, array &$bindings): string
    {
        return $aggregate->function.'('.$this->symbolToSQL($aggregate->column, $bindings).')';
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
            if ($component === '*') {
                continue;
            }

            $components[$index] = $this->wrapPlainSymbol($component);
        }

        return implode('.', $components);
    }

    /**
     * Wraps a plain (without nesting by dots) symbol (table, column, database, etc.) name with quotes.
     *
     * @param string $name
     * @return string
     */
    protected function wrapPlainSymbol(string $name): string
    {
        return '`'.str_replace('`', '``', $name).'`';
    }

    /**
     * Merges two arrays of bound values.
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
