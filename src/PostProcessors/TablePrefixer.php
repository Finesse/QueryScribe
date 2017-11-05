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
use Finesse\QueryScribe\QueryBricks\Order;
use Finesse\QueryScribe\StatementInterface;

/**
 * Adds a prefix to all the tables of a query regarding table aliases.
 *
 * @author Surgie
 */
class TablePrefixer implements PostProcessorInterface
{
    /*
     * All the methods here DON'T change a given object but may return it.
     */

    /**
     * @var string Tables prefix
     */
    public $tablePrefix;

    /**
     * @param string $tablePrefix The table prefix name
     */
    public function __construct(string $tablePrefix)
    {
        $this->tablePrefix = $tablePrefix;
    }

    /**
     * {@inheritDoc}
     */
    public function process(Query $query): Query
    {
        return $this->processQuery($query, []);
    }

    /**
     * Adds the table prefix to a table name.
     *
     * @param string $table Table name without quotes
     * @return string Table name with prefix
     */
    public function addTablePrefix(string $table): string
    {
        $components = explode('.', $table);
        $componentsCount = count($components);
        $components[$componentsCount - 1] = $this->tablePrefix.$components[$componentsCount - 1];

        return implode('.', $components);
    }

    /**
     * Adds the table prefix to a column name which may contain table name or alias.
     *
     * @param string $column Column name without quotes
     * @param string[] $tableAliases Known table aliases
     * @return string Column name with prefixed table name
     */
    public function addTablePrefixToColumn(string $column, array $tableAliases = []): string
    {
        $columnPosition = strrpos($column, '.');
        if ($columnPosition === false) {
            return $column;
        }

        $table = substr($column, 0, $columnPosition);
        if (in_array($table, $tableAliases)) {
            return $column;
        }

        $column = substr($column, $columnPosition + 1);
        return $this->addTablePrefix($table).'.'.$column;
    }

    /**
     * Processes a Query object. DOES NOT change the given query or it's components by the link but may return it.
     *
     * @param Query $query
     * @param string[] $tableAliases Known table aliases
     * @return Query
     */
    public function processQuery(Query $query, array $tableAliases): Query
    {
        if ($this->tablePrefix === '') {
            return $query;
        }

        $tableAliases = array_merge($tableAliases, $this->getTablesAliases($query));
        $queryProperties = [];

        // Table
        if (is_string($query->table)) {
            $queryProperties['table'] = $this->addTablePrefix($query->table);
        } else {
            $queryProperties['table'] = $this->processSubQuery($query->table, $tableAliases);
        }

        // Select
        $queryProperties['select'] = [];
        foreach ($query->select as $alias => $select) {
            $queryProperties['select'][$alias] = $this->processSelect($select, $tableAliases);
        }

        // Insert
        $queryProperties['insert'] = [];
        foreach ($query->insert as $index => $insert) {
            $queryProperties['insert'][$index] = $this->processInsert($insert, $tableAliases);
        }

        // Update
        $queryProperties['update'] = [];
        foreach ($query->update as $column => $update) {
            $column = $this->addTablePrefixToColumn($column, $tableAliases);
            $queryProperties['update'][$column] = $this->processValueOrSubQuery($update, $tableAliases);
        }

        // Where
        $queryProperties['where'] = [];
        foreach ($query->where as $index => $criterion) {
            $queryProperties['where'][$index] = $this->processCriterion($criterion, $tableAliases);
        }

        // Order
        $queryProperties['order'] = [];
        foreach ($query->order as $index => $order) {
            $queryProperties['order'][$index] = $this->processOrder($order, $tableAliases);
        }

        // Offset
        $queryProperties['offset'] = $this->processValueOrSubQuery($query->offset, $tableAliases);

        // Limit
        $queryProperties['limit'] = $this->processValueOrSubQuery($query->limit, $tableAliases);

        // Is any property is changed?
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
     * Retrieves tables aliases used in a query (excluding subqueries).
     *
     * @param Query $query
     * @return string[]
     */
    protected function getTablesAliases(Query $query): array
    {
        if ($query->tableAlias !== null) {
            return [$query->tableAlias];
        } else {
            return [];
        }
    }

    /**
     * Processes a single select column.
     *
     * @param string|Aggregate|Query|StatementInterface $select
     * @param string[] $tableAliases Known table aliases
     * @return string|Aggregate|Query|StatementInterface
     */
    protected function processSelect($select, array $tableAliases)
    {
        if ($select instanceof Aggregate) {
            $column = $this->processColumnOrSubQuery($select->column, $tableAliases);
            if ($column === $select->column) {
                return $select;
            } else {
                return new Aggregate($select->function, $column);
            }
        }

        return $this->processColumnOrSubQuery($select, $tableAliases);
    }

    /**
     * Processes a "column or subquery" value.
     *
     * @param string|Query|StatementInterface $column
     * @param string[] $tableAliases Known table aliases
     * @return string|Query|StatementInterface
     */
    protected function processColumnOrSubQuery($column, array $tableAliases)
    {
        if (is_string($column)) {
            return $this->addTablePrefixToColumn($column, $tableAliases);
        }

        return $this->processSubQuery($column, $tableAliases);
    }

    /**
     * Processes a "value or subquery" value
     *
     * @param mixed|Query|StatementInterface $value
     * @param array $tableAliases
     * @return mixed|Query|StatementInterface
     */
    protected function processValueOrSubQuery($value, array $tableAliases)
    {
        return $this->processSubQuery($value, $tableAliases);
    }

    /**
     * Processes a subquery. Not a subquery values are just passed through.
     *
     * @param Query|StatementInterface $subQuery
     * @param string[] $tableAliases Known table aliases
     * @return Query|StatementInterface
     */
    protected function processSubQuery($subQuery, array $tableAliases)
    {
        if ($subQuery instanceof Query) {
            return $this->processQuery($subQuery, $tableAliases);
        }

        return $subQuery;
    }

    /**
     * Processes a single insert statement.
     *
     * @param mixed[]|Query[]|StatementInterface[]|InsertFromSelect $row
     * @param string[] $tableAliases Known table aliases
     * @return mixed[]|Query[]|StatementInterface[]|InsertFromSelect
     */
    protected function processInsert($row, array $tableAliases)
    {
        if ($row instanceof InsertFromSelect) {
            if ($row->columns === null) {
                $columns = null;
            } else {
                $columns = [];
                foreach ($row->columns as $index => $column) {
                    $columns[ $index ] = $this->addTablePrefixToColumn($column, $tableAliases);
                }
            }
            $selectQuery = $this->processSubQuery($row->selectQuery, $tableAliases);

            if ($selectQuery === $row->selectQuery && $columns === $row->columns) {
                return $row;
            } else {
                return new InsertFromSelect($columns, $selectQuery);
            }
        }

        $newRow = [];
        foreach ($row as $column => $value) {
            $column = $this->addTablePrefixToColumn($column, $tableAliases);
            $newRow[$column] = $this->processValueOrSubQuery($value, $tableAliases);
        }

        return $newRow;
    }

    /**
     * Processes a single criterion.
     *
     * @param Criterion $criterion
     * @param string[] $tableAliases Known table aliases
     * @return Criterion
     */
    protected function processCriterion(Criterion $criterion, array $tableAliases): Criterion
    {
        if ($criterion instanceof ValueCriterion) {
            $column = $this->processColumnOrSubQuery($criterion->column, $tableAliases);
            $value = $this->processValueOrSubQuery($criterion->value, $tableAliases);

            if ($column === $criterion->column && $value === $criterion->value) {
                return $criterion;
            } else {
                return new ValueCriterion($column, $criterion->rule, $value, $criterion->appendRule);
            }
        }

        if ($criterion instanceof ColumnsCriterion) {
            $column1 = $this->processColumnOrSubQuery($criterion->column1, $tableAliases);
            $column2 = $this->processColumnOrSubQuery($criterion->column2, $tableAliases);

            if ($column1 === $criterion->column1 && $column2 === $criterion->column2) {
                return $criterion;
            } else {
                return new ColumnsCriterion($column1, $criterion->rule, $column2, $criterion->appendRule);
            }
        }

        if ($criterion instanceof BetweenCriterion) {
            $column = $this->processColumnOrSubQuery($criterion->column, $tableAliases);
            $min = $this->processValueOrSubQuery($criterion->min, $tableAliases);
            $max = $this->processValueOrSubQuery($criterion->max, $tableAliases);

            if ($column === $criterion->column && $min === $criterion->min && $max === $criterion->max) {
                return $criterion;
            } else {
                return new BetweenCriterion($column, $min, $max, $criterion->not, $criterion->appendRule);
            }
        }

        if ($criterion instanceof CriteriaCriterion) {
            $criteria = [];
            foreach ($criterion->criteria as $index => $subCriterion) {
                $criteria[$index] = $this->processCriterion($subCriterion, $tableAliases);
            }

            if ($criteria === $criterion->criteria) {
                return $criterion;
            } else {
                return new CriteriaCriterion($criteria, $criterion->not, $criterion->appendRule);
            }
        }

        if ($criterion instanceof ExistsCriterion) {
            $subQuery = $this->processSubQuery($criterion->subQuery, $tableAliases);

            if ($subQuery === $criterion->subQuery) {
                return $criterion;
            } else {
                return new ExistsCriterion($subQuery, $criterion->not, $criterion->appendRule);
            }
        }

        if ($criterion instanceof InCriterion) {
            $column = $this->processColumnOrSubQuery($criterion->column, $tableAliases);
            if (is_array($criterion->values)) {
                $values = [];
                foreach ($criterion->values as $index => $value) {
                    $values[$index] = $this->processValueOrSubQuery($value, $tableAliases);
                }
            } else {
                $values = $this->processSubQuery($criterion->values, $tableAliases);
            }

            if ($column === $criterion->column && $values === $criterion->values) {
                return $criterion;
            } else {
                return new InCriterion($column, $values, $criterion->not, $criterion->appendRule);
            }
        }

        if ($criterion instanceof NullCriterion) {
            $column = $this->processColumnOrSubQuery($criterion->column, $tableAliases);

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
     * @param string[] $tableAliases Known table aliases
     * @return Order|string
     */
    protected function processOrder($order, array $tableAliases)
    {
        if ($order instanceof Order) {
            $column = $this->processColumnOrSubQuery($order->column, $tableAliases);

            if ($column === $order->column) {
                return $order;
            } else {
                return new Order($column, $order->isDescending);
            }
        }

        return $order;
    }
}
