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
use Finesse\QueryScribe\QueryBricks\Orders\Order;
use Finesse\QueryScribe\QueryBricks\Orders\OrderByIsNull;
use Finesse\QueryScribe\StatementInterface;

/**
 * Transforms all the table names of a query regarding table aliases.
 *
 * @author Surgie
 */
abstract class AbstractTableNameProcessor implements PostProcessorInterface
{
    /*
     * All the methods here DON'T change a given object but may return it.
     */

    /**
     * {@inheritDoc}
     */
    public function process(Query $query): Query
    {
        return $this->processQuery($query, []);
    }

    /**
     * Processes a table name (which is not an alias).
     *
     * @param string $table Table name without quotes
     * @return string Processed table name
     */
    abstract protected function processTableName(string $table): string;

    /**
     * Processes a column name which may contain a table name or an alias.
     *
     * @param string $column Column name without quotes
     * @param string[]|null $knownTables Known unprocessed table names. If null, every table name should be processed.
     * @return string Column name with processed table name
     */
    abstract protected function processColumnName(string $column, array $knownTables = null): string;

    /**
     * Processes a Query object. DOES NOT change the given query or it's components by the link but may return it.
     *
     * @param Query $query
     * @param string[] $knownTables Known unprocessed table names
     * @return Query
     */
    public function processQuery(Query $query, array $knownTables): Query
    {
        foreach ($this->getTables($query) as $table) {
            $knownTables[] = $table;
        }
        $queryProperties = [];

        // Table
        if (is_string($query->table)) {
            $queryProperties['table'] = $this->processTableName($query->table);
        } else {
            $queryProperties['table'] = $this->processSubQuery($query->table, $knownTables);
        }

        // Select
        $queryProperties['select'] = [];
        foreach ($query->select as $alias => $select) {
            $queryProperties['select'][$alias] = $this->processSelect($select, $knownTables);
        }

        // Insert
        $queryProperties['insert'] = [];
        foreach ($query->insert as $index => $insert) {
            $queryProperties['insert'][$index] = $this->processInsert($insert, $knownTables);
        }

        // Update
        $queryProperties['update'] = [];
        foreach ($query->update as $column => $update) {
            $column = $this->processColumnName($column, $knownTables);
            $queryProperties['update'][$column] = $this->processValueOrSubQuery($update, $knownTables);
        }

        // Where
        $queryProperties['where'] = [];
        foreach ($query->where as $index => $criterion) {
            $queryProperties['where'][$index] = $this->processCriterion($criterion, $knownTables);
        }

        // Order
        $queryProperties['order'] = [];
        foreach ($query->order as $index => $order) {
            $queryProperties['order'][$index] = $this->processOrder($order, $knownTables);
        }

        // Offset
        $queryProperties['offset'] = $this->processValueOrSubQuery($query->offset, $knownTables);

        // Limit
        $queryProperties['limit'] = $this->processValueOrSubQuery($query->limit, $knownTables);

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
     * Retrieves unprocessed table names used in a query (excluding subqueries).
     *
     * @param Query $query
     * @return string[]
     */
    protected function getTables(Query $query): array
    {
        if (is_string($query->table)) {
            return [$query->table];
        } else {
            return [];
        }
    }

    /**
     * Processes a single select column.
     *
     * @param string|Aggregate|Query|StatementInterface $select
     * @param string[] $knownTables Known unprocessed table names
     * @return string|Aggregate|Query|StatementInterface
     */
    protected function processSelect($select, array $knownTables)
    {
        if ($select instanceof Aggregate) {
            $column = $this->processColumnOrSubQuery($select->column, $knownTables);
            if ($column === $select->column) {
                return $select;
            } else {
                return new Aggregate($select->function, $column);
            }
        }

        return $this->processColumnOrSubQuery($select, $knownTables);
    }

    /**
     * Processes a "column or subquery" value.
     *
     * @param string|Query|StatementInterface $column
     * @param string[] $knownTables Known unprocessed table names
     * @return string|Query|StatementInterface
     */
    protected function processColumnOrSubQuery($column, array $knownTables)
    {
        if (is_string($column)) {
            return $this->processColumnName($column, $knownTables);
        }

        return $this->processSubQuery($column, $knownTables);
    }

    /**
     * Processes a "value or subquery" value
     *
     * @param mixed|Query|StatementInterface $value
     * @param array $knownTables
     * @return mixed|Query|StatementInterface
     */
    protected function processValueOrSubQuery($value, array $knownTables)
    {
        return $this->processSubQuery($value, $knownTables);
    }

    /**
     * Processes a subquery. Not-subquery values are just passed through.
     *
     * @param Query|StatementInterface $subQuery
     * @param string[] $knownTables Known unprocessed table names
     * @return Query|StatementInterface
     */
    protected function processSubQuery($subQuery, array $knownTables)
    {
        if ($subQuery instanceof Query) {
            return $this->processQuery($subQuery, $knownTables);
        }

        return $subQuery;
    }

    /**
     * Processes a single insert statement.
     *
     * @param mixed[]|Query[]|StatementInterface[]|InsertFromSelect $row
     * @param string[] $knownTables Known unprocessed table names
     * @return mixed[]|Query[]|StatementInterface[]|InsertFromSelect
     */
    protected function processInsert($row, array $knownTables)
    {
        if ($row instanceof InsertFromSelect) {
            if ($row->columns === null) {
                $columns = null;
            } else {
                $columns = [];
                foreach ($row->columns as $index => $column) {
                    $columns[$index] = $this->processColumnName($column, $knownTables);
                }
            }
            $selectQuery = $this->processSubQuery($row->selectQuery, $knownTables);

            if ($selectQuery === $row->selectQuery && $columns === $row->columns) {
                return $row;
            } else {
                return new InsertFromSelect($columns, $selectQuery);
            }
        }

        $newRow = [];
        foreach ($row as $column => $value) {
            $column = $this->processColumnName($column, $knownTables);
            $newRow[$column] = $this->processValueOrSubQuery($value, $knownTables);
        }

        return $newRow;
    }

    /**
     * Processes a single criterion.
     *
     * @param Criterion $criterion
     * @param string[] $knownTables Known unprocessed table names
     * @return Criterion
     */
    protected function processCriterion(Criterion $criterion, array $knownTables): Criterion
    {
        if ($criterion instanceof ValueCriterion) {
            $column = $this->processColumnOrSubQuery($criterion->column, $knownTables);
            $value = $this->processValueOrSubQuery($criterion->value, $knownTables);

            if ($column === $criterion->column && $value === $criterion->value) {
                return $criterion;
            } else {
                return new ValueCriterion($column, $criterion->rule, $value, $criterion->appendRule);
            }
        }

        if ($criterion instanceof ColumnsCriterion) {
            $column1 = $this->processColumnOrSubQuery($criterion->column1, $knownTables);
            $column2 = $this->processColumnOrSubQuery($criterion->column2, $knownTables);

            if ($column1 === $criterion->column1 && $column2 === $criterion->column2) {
                return $criterion;
            } else {
                return new ColumnsCriterion($column1, $criterion->rule, $column2, $criterion->appendRule);
            }
        }

        if ($criterion instanceof BetweenCriterion) {
            $column = $this->processColumnOrSubQuery($criterion->column, $knownTables);
            $min = $this->processValueOrSubQuery($criterion->min, $knownTables);
            $max = $this->processValueOrSubQuery($criterion->max, $knownTables);

            if ($column === $criterion->column && $min === $criterion->min && $max === $criterion->max) {
                return $criterion;
            } else {
                return new BetweenCriterion($column, $min, $max, $criterion->not, $criterion->appendRule);
            }
        }

        if ($criterion instanceof CriteriaCriterion) {
            $criteria = [];
            foreach ($criterion->criteria as $index => $subCriterion) {
                $criteria[$index] = $this->processCriterion($subCriterion, $knownTables);
            }

            if ($criteria === $criterion->criteria) {
                return $criterion;
            } else {
                return new CriteriaCriterion($criteria, $criterion->not, $criterion->appendRule);
            }
        }

        if ($criterion instanceof ExistsCriterion) {
            $subQuery = $this->processSubQuery($criterion->subQuery, $knownTables);

            if ($subQuery === $criterion->subQuery) {
                return $criterion;
            } else {
                return new ExistsCriterion($subQuery, $criterion->not, $criterion->appendRule);
            }
        }

        if ($criterion instanceof InCriterion) {
            $column = $this->processColumnOrSubQuery($criterion->column, $knownTables);
            if (is_array($criterion->values)) {
                $values = [];
                foreach ($criterion->values as $index => $value) {
                    $values[$index] = $this->processValueOrSubQuery($value, $knownTables);
                }
            } else {
                $values = $this->processSubQuery($criterion->values, $knownTables);
            }

            if ($column === $criterion->column && $values === $criterion->values) {
                return $criterion;
            } else {
                return new InCriterion($column, $values, $criterion->not, $criterion->appendRule);
            }
        }

        if ($criterion instanceof NullCriterion) {
            $column = $this->processColumnOrSubQuery($criterion->column, $knownTables);

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
     * @param Order|string $order
     * @param string[] $knownTables Known unprocessed table names
     * @return Order|string
     */
    protected function processOrder($order, array $knownTables)
    {
        if ($order instanceof Order || $order instanceof OrderByIsNull) {
            $column = $this->processColumnOrSubQuery($order->column, $knownTables);

            if ($column === $order->column) {
                return $order;
            } elseif ($order instanceof Order) {
                return new Order($column, $order->isDescending);
            } else {
                return new OrderByIsNull($column, $order->nullFirst);
            }
        }

        return $order;
    }
}
