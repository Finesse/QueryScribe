<?php

namespace Finesse\QueryScribe\PostProcessors;

use Finesse\QueryScribe\PostProcessorInterface;
use Finesse\QueryScribe\Query;
use Finesse\QueryScribe\QueryBricks\Aggregate;
use Finesse\QueryScribe\QueryBricks\Criteria\BetweenCriterion;
use Finesse\QueryScribe\QueryBricks\Criteria\ColumnsCriterion;
use Finesse\QueryScribe\QueryBricks\Criteria\CriteriaCriterion;
use Finesse\QueryScribe\QueryBricks\Criteria\ExistsCriterion;
use Finesse\QueryScribe\QueryBricks\Criteria\InCriterion;
use Finesse\QueryScribe\QueryBricks\Criteria\NullCriterion;
use Finesse\QueryScribe\QueryBricks\Criteria\ValueCriterion;
use Finesse\QueryScribe\QueryBricks\Criterion;
use Finesse\QueryScribe\QueryBricks\InsertFromSelect;
use Finesse\QueryScribe\QueryBricks\Join;
use Finesse\QueryScribe\QueryBricks\Orders\ExplicitOrder;
use Finesse\QueryScribe\QueryBricks\Orders\Order;
use Finesse\QueryScribe\QueryBricks\Orders\OrderByIsNull;
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

    /**
     * {@inheritDoc}
     */
    public function process(Query $query): Query
    {
        return $this->processQuery($query, $this->getInitialContext($query));
    }

    /**
     * {@inheritDoc}
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

    /**
     * Processes a single criterion.
     *
     * @param Criterion $criterion
     * @param mixed $context The processing context
     * @return Criterion
     */
    protected function processCriterion(Criterion $criterion, $context): Criterion
    {
        if ($criterion instanceof ValueCriterion) {
            $column = $this->processColumnOrSubQuery($criterion->column, $context);
            $value = $this->processValueOrSubQuery($criterion->value, $context);

            if ($column === $criterion->column && $value === $criterion->value) {
                return $criterion;
            } else {
                return new ValueCriterion($column, $criterion->rule, $value, $criterion->appendRule);
            }
        }

        if ($criterion instanceof ColumnsCriterion) {
            $column1 = $this->processColumnOrSubQuery($criterion->column1, $context);
            $column2 = $this->processColumnOrSubQuery($criterion->column2, $context);

            if ($column1 === $criterion->column1 && $column2 === $criterion->column2) {
                return $criterion;
            } else {
                return new ColumnsCriterion($column1, $criterion->rule, $column2, $criterion->appendRule);
            }
        }

        if ($criterion instanceof BetweenCriterion) {
            $column = $this->processColumnOrSubQuery($criterion->column, $context);
            $min = $this->processValueOrSubQuery($criterion->min, $context);
            $max = $this->processValueOrSubQuery($criterion->max, $context);

            if ($column === $criterion->column && $min === $criterion->min && $max === $criterion->max) {
                return $criterion;
            } else {
                return new BetweenCriterion($column, $min, $max, $criterion->not, $criterion->appendRule);
            }
        }

        if ($criterion instanceof CriteriaCriterion) {
            $criteria = [];
            foreach ($criterion->criteria as $index => $subCriterion) {
                $criteria[$index] = $this->processCriterion($subCriterion, $context);
            }

            if ($criteria === $criterion->criteria) {
                return $criterion;
            } else {
                return new CriteriaCriterion($criteria, $criterion->not, $criterion->appendRule);
            }
        }

        if ($criterion instanceof ExistsCriterion) {
            $subQuery = $this->processSubQuery($criterion->subQuery, $context);

            if ($subQuery === $criterion->subQuery) {
                return $criterion;
            } else {
                return new ExistsCriterion($subQuery, $criterion->not, $criterion->appendRule);
            }
        }

        if ($criterion instanceof InCriterion) {
            $column = $this->processColumnOrSubQuery($criterion->column, $context);
            if (is_array($criterion->values)) {
                $values = [];
                foreach ($criterion->values as $index => $value) {
                    $values[$index] = $this->processValueOrSubQuery($value, $context);
                }
            } else {
                $values = $this->processSubQuery($criterion->values, $context);
            }

            if ($column === $criterion->column && $values === $criterion->values) {
                return $criterion;
            } else {
                return new InCriterion($column, $values, $criterion->not, $criterion->appendRule);
            }
        }

        if ($criterion instanceof NullCriterion) {
            $column = $this->processColumnOrSubQuery($criterion->column, $context);

            if ($column === $criterion->column) {
                return $criterion;
            } else {
                return new NullCriterion($column, $criterion->isNull, $criterion->appendRule);
            }
        }

        return $criterion;
    }

    /**
     * Processes a single order statement.
     *
     * @param Order|OrderByIsNull|ExplicitOrder|string $order
     * @param mixed $context The processing context
     * @return Order|string
     */
    protected function processOrder($order, $context)
    {
        if ($order instanceof Order || $order instanceof OrderByIsNull) {
            $column = $this->processColumnOrSubQuery($order->column, $context);

            if ($column === $order->column) {
                return $order;
            } elseif ($order instanceof Order) {
                return new Order($column, $order->isDescending);
            } else {
                return new OrderByIsNull($column, $order->areNullFirst);
            }
        }

        if ($order instanceof ExplicitOrder) {
            $column = $this->processColumnOrSubQuery($order->column, $context);

            $values = [];
            foreach ($order->order as $index => $value) {
                $values[$index] = $this->processValueOrSubQuery($value, $context);
            }

            if ($column === $order->column && $values === $order->order) {
                return $order;
            } else {
                return new ExplicitOrder($column, $values, $order->areOtherFirst);
            }
        }

        return $order;
    }
}
