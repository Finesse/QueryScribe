<?php

namespace Finesse\QueryScribe\PostProcessors;

use Finesse\QueryScribe\PostProcessorInterface;
use Finesse\QueryScribe\Query;
use Finesse\QueryScribe\QueryBricks\Aggregate;
use Finesse\QueryScribe\QueryBricks\InsertFromSelect;
use Finesse\QueryScribe\QueryBricks\Join;
use Finesse\QueryScribe\StatementInterface;

/**
 * Scaffolding for making a query post processor.
 *
 * @author Surgie
 */
abstract class AbstractProcessor implements PostProcessorInterface
{
    /*
     * All the methods here DON'T change a given object but may return it.
     */

    use AbstractProcessorCriteriaTrait;
    use AbstractProcessorOrderTrait;

    /**
     * {@inheritDoc}
     */
    public function process(Query $query): Query
    {
        return $this->processQuery($query, $this->getInitialContext($query));
    }

    /**
     * {@inheritDoc}
     *
     * An alias for `process`
     */
    public function __invoke(Query $query): Query
    {
        return $this->process($query);
    }

    /**
     * Returns the initial processing context
     *
     * @param Query $query The processed query
     * @return mixed
     */
    abstract protected function getInitialContext(Query $query);

    /**
     * Processes a Query object. DOES NOT change the given query or it's components by the link but may return it.
     *
     * @param Query $query
     * @param mixed $context The processing context
     * @return Query
     */
    protected function processQuery(Query $query, $context): Query
    {
        $queryProperties = [];

        // Table
        $queryProperties['table'] = $this->processTable($query->table, $context);

        // Select
        foreach ($query->select as $alias => $select) {
            $queryProperties['select'][$alias] = $this->processSelect($select, $context);
        }

        // Insert
        foreach ($query->insert as $index => $insert) {
            $queryProperties['insert'][$index] = $this->processInsert($insert, $context);
        }

        // Update
        foreach ($query->update as $column => $update) {
            $column = $this->processColumnName($column, $context);
            $queryProperties['update'][$column] = $this->processValueOrSubQuery($update, $context);
        }

        // Join
        foreach ($query->join as $index => $join) {
            $queryProperties['join'][$index] = $this->processJoin($join, $context);
        }

        // Where
        foreach ($query->where as $index => $criterion) {
            $queryProperties['where'][$index] = $this->processCriterion($criterion, $context);
        }

        // Order
        foreach ($query->order as $index => $order) {
            $queryProperties['order'][$index] = $this->processOrder($order, $context);
        }

        // Offset
        $queryProperties['offset'] = $this->processValueOrSubQuery($query->offset, $context);

        // Limit
        $queryProperties['limit'] = $this->processValueOrSubQuery($query->limit, $context);

        // Is any property changed?
        $isChanged = false;
        foreach ($queryProperties as $property => $value) {
            if ($value !== $query->$property) {
                $isChanged = true;
                break;
            }
        }

        if ($isChanged) {
            $query = clone $query;
            foreach ($queryProperties as $property => $value) {
                $query->$property = $value;
            }
            return $query;
        } else {
            return $query;
        }
    }

    /**
     * Processes a table name or table subquery
     *
     * @param Query|StatementInterface|string $table
     * @param mixed $context The processing context
     * @return Query|StatementInterface|string
     */
    protected function processTable($table, $context)
    {
        if (is_string($table)) {
            return $this->processTableName($table, $context);
        } else {
            return $this->processSubQuery($table, $context);
        }
    }

    /**
     * Processes a table name (which is not an alias).
     *
     * @param string $table Table name without quotes
     * @return string Processed table name
     */
    protected function processTableName(string $table, $context): string
    {
        return $table;
    }

    /**
     * Processes a single select column.
     *
     * @param string|Aggregate|Query|StatementInterface $select
     * @param mixed $context The processing context
     * @return string|Aggregate|Query|StatementInterface
     */
    protected function processSelect($select, $context)
    {
        if ($select instanceof Aggregate) {
            $column = $this->processColumnOrSubQuery($select->column, $context);
            if ($column === $select->column) {
                return $select;
            } else {
                return new Aggregate($select->function, $column);
            }
        }

        return $this->processColumnOrSubQuery($select, $context);
    }

    /**
     * Processes a "column or subquery" value.
     *
     * @param string|Query|StatementInterface $column
     * @param mixed $context The processing context
     * @return string|Query|StatementInterface
     */
    protected function processColumnOrSubQuery($column, $context)
    {
        if (is_string($column)) {
            return $this->processColumnName($column, $context);
        }

        return $this->processSubQuery($column, $context);
    }

    /**
     * Processes a column name which may contain a table name or an alias.
     *
     * @param string $column Column name without quotes
     * @param string[] $tablesToProcess Table names that must be processed
     * @return string Column name with processed table name
     */
    protected function processColumnName(string $column, $context): string
    {
        return $column;
    }

    /**
     * Processes a "value or subquery" value
     *
     * @param mixed|Query|StatementInterface $value
     * @param $context
     * @return mixed|Query|StatementInterface
     */
    protected function processValueOrSubQuery($value, $context)
    {
        return $this->processSubQuery($value, $context);
    }

    /**
     * Processes a subquery. Not-subquery values are just passed through.
     *
     * @param Query|StatementInterface $subQuery
     * @param mixed $context The processing context
     * @return Query|StatementInterface
     */
    protected function processSubQuery($subQuery, $context)
    {
        if ($subQuery instanceof Query) {
            return $this->processQuery($subQuery, $context);
        }

        return $subQuery;
    }

    /**
     * Processes a single insert statement.
     *
     * @param mixed[]|Query[]|StatementInterface[]|InsertFromSelect $row
     * @param mixed $context The processing context
     * @return mixed[]|Query[]|StatementInterface[]|InsertFromSelect
     */
    protected function processInsert($row, $context)
    {
        if ($row instanceof InsertFromSelect) {
            return $this->processInsertFromSelect($row, $context);
        }

        $newRow = [];
        foreach ($row as $column => $value) {
            $column = $this->processColumnName($column, $context);
            $newRow[$column] = $this->processValueOrSubQuery($value, $context);
        }

        return $newRow;
    }

    /**
     * Processes a single "insert from select" statement.
     *
     * @param InsertFromSelect $row
     * @param mixed $context The processing context
     * @return InsertFromSelect
     */
    protected function processInsertFromSelect(InsertFromSelect $row, $context): InsertFromSelect
    {
        if ($row->columns === null) {
            $columns = null;
        } else {
            $columns = [];
            foreach ($row->columns as $index => $column) {
                $columns[$index] = $this->processColumnName($column, $context);
            }
        }
        $selectQuery = $this->processSubQuery($row->selectQuery, $context);

        if ($selectQuery === $row->selectQuery && $columns === $row->columns) {
            return $row;
        } else {
            return new InsertFromSelect($columns, $selectQuery);
        }
    }

    /**
     * Processes a single join.
     *
     * @param Join $join
     * @param mixed $context The processing context
     * @return Join
     */
    protected function processJoin(Join $join, $context): Join
    {
        $table = $this->processTable($join->table, $context);
        $criteria = [];
        foreach ($join->criteria as $index => $criterion) {
            $criteria[$index] = $this->processCriterion($criterion, $context);
        }

        if ($table === $join->table && $criteria === $join->criteria) {
            return $join;
        } else {
            return new Join($join->type, $table, $join->tableAlias, $criteria);
        }
    }
}
