<?php

namespace Finesse\QueryScribe\Grammars;

use Finesse\QueryScribe\Exceptions\InvalidQueryException;
use Finesse\QueryScribe\GrammarInterface;
use Finesse\QueryScribe\QueryBricks\Aggregate;
use Finesse\QueryScribe\QueryBricks\Criteria\BetweenCriterion;
use Finesse\QueryScribe\QueryBricks\Criteria\ColumnsCriterion;
use Finesse\QueryScribe\QueryBricks\Criteria\CriteriaCriterion;
use Finesse\QueryScribe\QueryBricks\Criteria\ExistsCriterion;
use Finesse\QueryScribe\QueryBricks\Criteria\InCriterion;
use Finesse\QueryScribe\QueryBricks\Criteria\NullCriterion;
use Finesse\QueryScribe\QueryBricks\Criteria\RawCriterion;
use Finesse\QueryScribe\QueryBricks\Criteria\ValueCriterion;
use Finesse\QueryScribe\QueryBricks\Criterion;
use Finesse\QueryScribe\Query;
use Finesse\QueryScribe\QueryBricks\InsertFromSelect;
use Finesse\QueryScribe\QueryBricks\Order;
use Finesse\QueryScribe\Raw;
use Finesse\QueryScribe\StatementInterface;

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
        if ($query->insert) {
            return $this->compileInsert($query);
        }
        if ($query->update) {
            return $this->compileUpdate($query);
        }
        if ($query->delete) {
            return $this->compileDelete($query);
        }
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
            $this->compileWherePart($query, $bindings),
            $this->compileOrderPart($query, $bindings),
            $this->compileOffsetAndLimitPart($query, $bindings)
        ];

        return new Raw($this->implodeSQL($sql), $bindings);
    }

    /**
     * {@inheritDoc}
     */
    public function compileInsert(Query $query): StatementInterface
    {
        if ($query->table === null) {
            throw new InvalidQueryException('The INTO table is not set');
        }

        $insert = $query->insert;
        $bindings = [];
        $sqlLine1 = 'INSERT INTO '.$this->compileIdentifierWithAlias($query->table, $query->tableAlias, $bindings);

        if (is_array($insert)) {
            // Step 1. Fetch unique columns list.
            $columns = [];
            foreach ($insert as $row) {
                foreach ($row as $column => $value) {
                    if (!isset($columns[$column])) {
                        $columns[$column] = count($columns);
                    }
                }
            }

            // Step 2. Build the values matrix.
            $compiledRows = [];
            foreach ($insert as $row) {
                $compiledRow = [];
                foreach ($columns as $column => $columnIndex) {
                    if (array_key_exists($column, $row)) {
                        $compiledRow[$columnIndex] = $this->compileValue($row[$column], $bindings);
                    } else {
                        $compiledRow[$columnIndex] = 'DEFAULT';
                    }
                }
                $compiledRows[] = '('.implode(', ', $compiledRow).')';
            }

            // Step 3. Build the SQL
            $sqlLine1 .= ' ('.implode(', ', array_map([$this, 'quoteIdentifier'], array_keys($columns))).')';
            $sqlLine2 = 'VALUES '.implode(', ', $compiledRows);
        } elseif ($insert instanceof InsertFromSelect) {
            if ($insert->columns !== null) {
                $sqlLine1 .= ' ('.implode(', ', array_map([$this, 'quoteIdentifier'], $insert->columns)).')';
            }
            $sqlLine2 = $this->compileSubQuery($insert->selectQuery, $bindings);
        } else {
            throw new InvalidQueryException('Unknown insert instruction type: '.gettype($insert));
        }

        return new Raw($this->implodeSQL([$sqlLine1, $sqlLine2]), $bindings);
    }

    /**
     * {@inheritDoc}
     */
    public function compileUpdate(Query $query): StatementInterface
    {
        if ($query->table === null) {
            throw new InvalidQueryException('The updated table is not set');
        }
        if (!$query->update) {
            throw new InvalidQueryException('The updated values are not set');
        }

        $bindings = [];
        $sql = [
            'UPDATE '.$this->compileIdentifierWithAlias($query->table, $query->tableAlias, $bindings),
            $this->compileUpdateSetPart($query, $bindings),
            $this->compileWherePart($query, $bindings),
            $this->compileOrderPart($query, $bindings),
            $this->compileOffsetAndLimitPart($query, $bindings)
        ];

        return new Raw($this->implodeSQL($sql), $bindings);
    }

    /**
     * {@inheritDoc}
     */
    public function compileDelete(Query $query): StatementInterface
    {
        $bindings = [];
        $sql = [
            'DELETE',
            $this->compileFromPart($query, $bindings),
            $this->compileWherePart($query, $bindings),
            $this->compileOrderPart($query, $bindings),
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
                $column = $this->compileAggregate($column, $bindings);
            } else {
                $column = $this->compileIdentifier($column, $bindings);
            }

            $columns[] = $column.(is_string($alias) ? ' AS '.$this->quotePlainIdentifier($alias) : '');
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
        if ($query->table === null) {
            throw new InvalidQueryException('The FROM table is not set');
        }

        return 'FROM '.$this->compileIdentifierWithAlias($query->table, $query->tableAlias, $bindings);
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
            $parts[] = 'OFFSET '.$this->compileValue($query->offset, $bindings);
        }

        if ($query->limit !== null) {
            $parts[] = 'LIMIT '.$this->compileValue($query->limit, $bindings);
        }

        return $this->implodeSQL($parts);
    }

    /**
     * Compiles a WHERE part of a SQL query.
     *
     * @param Query $query Query data
     * @param array $bindings Bound values (array is filled by link)
     * @return string SQL text
     * @throws InvalidQueryException
     */
    protected function compileWherePart(Query $query, array &$bindings): string
    {
        $sql = $this->compileCriteria($query->where, $bindings);
        if ($sql !== '') {
            $sql = 'WHERE '.$sql;
        }

        return $sql;
    }

    /**
     * Compiles a ORDER part of a SQL query.
     *
     * @param Query $query Query data
     * @param array $bindings Bound values (array is filled by link)
     * @return string SQL text
     * @throws InvalidQueryException
     */
    protected function compileOrderPart(Query $query, array &$bindings): string
    {
        $ordersSQL = [];

        foreach ($query->order as $order) {
            $orderSQL = $this->compileOneOrder($order, $bindings);

            if ($orderSQL !== '') {
                $ordersSQL[] = $orderSQL;
            }
        }

        return $ordersSQL ? 'ORDER BY '.implode(', ', $ordersSQL) : '';
    }

    /**
     * Compiles a SET part of a update SQL query.
     *
     * @param Query $query Query data
     * @param array $bindings Bound values (array is filled by link)
     * @return string SQL text
     * @throws InvalidQueryException
     */
    protected function compileUpdateSetPart(Query $query, array &$bindings): string
    {
        $parts = [];

        foreach ($query->update as $column => $value) {
            $parts[] = $this->quoteIdentifier($column).' = '.$this->compileValue($value, $bindings);
        }

        return $parts ? 'SET '.implode(', ', $parts) : '';
    }

    /**
     * Converts a identifier (table, column, database, etc.) to a part of a SQL query text. Screens all the stuff.
     *
     * @param string|Query|StatementInterface $identifier Identifier
     * @param array $bindings Bound values (array is filled by link)
     * @return string SQL text
     */
    protected function compileIdentifier($identifier, array &$bindings): string
    {
        if ($identifier instanceof Query || $identifier instanceof StatementInterface) {
            return $this->compileSubQuery($identifier, $bindings);
        }

        return $this->quoteIdentifier($identifier);
    }

    /**
     * Converts a value to a part of a SQL query text. Actually it sends all the values to the bindings.
     *
     * @param mixed|Query|\Finesse\QueryScribe\StatementInterface $value Value (a scalar value or a subquery)
     * @param array $bindings Bound values (array is filled by link)
     * @return string SQL text
     */
    protected function compileValue($value, array &$bindings): string
    {
        if ($value instanceof Query || $value instanceof StatementInterface) {
            return $this->compileSubQuery($value, $bindings);
        }

        $this->mergeBindings($bindings, [$value]);
        return '?';
    }

    /**
     * Converts a subquery to a SQL query text.
     *
     * @param Query|StatementInterface $subQuery Subquery
     * @param array $bindings Bound values (array is filled by link)
     * @return string SQL text wrapped in parentheses
     */
    protected function compileSubQuery($subQuery, array &$bindings): string
    {
        if ($subQuery instanceof Query) {
            try {
                $subQuery = $this->compile($subQuery);
            } catch (InvalidQueryException $exception) {
                throw new InvalidQueryException(
                    'Error in subquery: '.$exception->getMessage(),
                    $exception->getCode(),
                    $exception
                );
            }
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
    protected function compileAggregate(Aggregate $aggregate, array &$bindings): string
    {
        return $aggregate->function.'('.$this->compileIdentifier($aggregate->column, $bindings).')';
    }

    /**
     * Converts an array of criteria (logical rules for WHERE, HAVING, etc.) to a SQL query text.
     *
     * @param Criterion[] $criteria List of criteria
     * @param array $bindings Bound values (array is filled by link)
     * @return string SQL text or empty string
     * @throws InvalidQueryException
     */
    protected function compileCriteria(array $criteria, array &$bindings): string
    {
        $criteriaSQL = '';
        $previousAppendRule = null;

        foreach ($criteria as $criterion) {
            $criterionSQL = $this->compileCriterion($criterion, $bindings);
            if ($criterionSQL === '') {
                continue;
            }

            $appendRule = $criterion->appendRule;

            if ($previousAppendRule === null) {
                $criteriaSQL .= $criterionSQL;
            } else {
                switch ($appendRule) {
                    case Criterion::APPEND_RULE_OR:
                        $criteriaSQL .= ' OR '.$criterionSQL;
                        break;
                    case Criterion::APPEND_RULE_AND:
                        if ($previousAppendRule === Criterion::APPEND_RULE_OR) {
                            $criteriaSQL = '('.$criteriaSQL.') AND '.$criterionSQL;
                        } else {
                            $criteriaSQL .= ' AND '.$criterionSQL;
                        }
                        break;
                    default:
                        throw new InvalidQueryException('Unknown criterion append rule `'.$appendRule.'`');
                }
            }

            $previousAppendRule = $appendRule;
        }

        return $criteriaSQL;
    }

    /**
     * Converts a single criterion to a SQL query text.
     *
     * @param Criterion $criterion Criterion
     * @param array $bindings Bound values (array is filled by link)
     * @return string SQL text or empty string
     * @throws InvalidQueryException
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

        if ($criterion instanceof ColumnsCriterion) {
            return sprintf(
                '%s %s %s',
                $this->compileIdentifier($criterion->column1, $bindings),
                $criterion->rule,
                $this->compileIdentifier($criterion->column2, $bindings)
            );
        }

        if ($criterion instanceof BetweenCriterion) {
            return sprintf(
                '(%s %sBETWEEN %s AND %s)',
                $this->compileIdentifier($criterion->column, $bindings),
                $criterion->not ? 'NOT ' : '',
                $this->compileValue($criterion->min, $bindings),
                $this->compileValue($criterion->max, $bindings)
            );
        }

        if ($criterion instanceof CriteriaCriterion) {
            $groupBindings = [];
            $groupSQL = $this->compileCriteria($criterion->criteria, $groupBindings);

            if ($groupSQL === '') {
                return '';
            } else {
                $this->mergeBindings($bindings, $groupBindings);

                return sprintf(
                    '%s(%s)',
                    $criterion->not ? 'NOT ' : '',
                    $groupSQL
                );
            }
        }

        if ($criterion instanceof ExistsCriterion) {
            return sprintf(
                '%sEXISTS %s',
                $criterion->not ? 'NOT ' : '',
                $this->compileSubQuery($criterion->subQuery, $bindings)
            );
        }

        if ($criterion instanceof InCriterion) {
            if (is_array($criterion->values)) {
                $values = [];
                foreach ($criterion->values as $value) {
                    $values[] = $this->compileValue($value, $bindings);
                }
                $subQuery = '('.implode(', ', $values).')';
            } else {
                $subQuery = $this->compileSubQuery($criterion->values, $bindings);
            }

            return sprintf(
                '%s %sIN %s',
                $this->compileIdentifier($criterion->column, $bindings),
                $criterion->not ? 'NOT ' : '',
                $subQuery
            );
        }

        if ($criterion instanceof NullCriterion) {
            return sprintf(
                '%s IS %sNULL',
                $this->compileIdentifier($criterion->column, $bindings),
                $criterion->isNull ? '' : 'NOT '
            );
        }

        if ($criterion instanceof RawCriterion) {
            return $this->compileSubQuery($criterion->raw, $bindings);
        }

        throw new InvalidQueryException('The given criterion '.get_class($criterion).' is unknown');
    }

    /**
     * Converts a single order to a SQL query text.
     *
     * @param Order|string $order Order. String `random` means that the order should be random.
     * @param array $bindings Bound values (array is filled by link)
     * @return string SQL text or empty string
     * @throws InvalidQueryException
     */
    protected function compileOneOrder($order, array &$bindings): string
    {
        if ($order instanceof Order) {
            return $this->compileIdentifier($order->column, $bindings).' '.($order->isDescending ? 'DESC' : 'ASC');
        }

        if ($order === 'random') {
            return 'RANDOM()';
        }

        throw new InvalidQueryException(sprintf(
            'The given order `%s` is unknown',
            is_string($order) ? $order : gettype($order)
        ));
    }

    /**
     * Converts a identifier (table, column, database, etc.) with alias to a part of a SQL query text. Screens all the
     * stuff.
     *
     * @param string|Query|StatementInterface $identifier Identifier
     * @param string|null $alias Alias name
     * @param array $bindings Bound values (array is filled by link)
     * @return string SQL text
     */
    protected function compileIdentifierWithAlias($identifier, string $alias = null, array &$bindings): string
    {
        return $this->compileIdentifier($identifier, $bindings)
            . ($alias === null ? '' : ' AS '.$this->quotePlainIdentifier($alias));
    }

    /**
     * Wraps a identifier (table, column, database, etc.) name with quotes.
     *
     * @param string $name
     * @return string
     */
    protected function quoteIdentifier(string $name): string
    {
        $components = explode('.', $name);

        foreach ($components as $index => $component) {
            if ($component === '*') {
                continue;
            }

            $components[$index] = $this->quotePlainIdentifier($component);
        }

        return implode('.', $components);
    }

    /**
     * Wraps a plain (without nesting by dots) identifier (table, column, database, etc.) name with quotes and screens
     * inside quotes.
     *
     * @param string $name
     * @return string
     */
    protected function quotePlainIdentifier(string $name): string
    {
        return '"'.str_replace('"', '""', $name).'"';
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
        $result = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            $result .= ($result === '' ? '' : "\n").$part;
        }

        return $result;
    }
}
