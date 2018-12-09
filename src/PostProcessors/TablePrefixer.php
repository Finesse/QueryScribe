<?php

namespace Finesse\QueryScribe\PostProcessors;

use Finesse\QueryScribe\Query;

/**
 * Adds a prefix to all the tables of a query regarding table aliases.
 *
 * @author Surgie
 */
class TablePrefixer extends AbstractTableNameProcessor
{
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
        if ($this->tablePrefix === '') {
            return $query;
        } else {
            return parent::process($query);
        }
    }

    /**
     * Adds the table prefix to a table name.
     *
     * @param string $table Table name without quotes
     * @return string Table name with prefix
     */
    public function addTablePrefix(string $table): string
    {
        return $this->processTableName($table);
    }

    /**
     * Adds the table prefix to a column name which may contain table name or alias.
     *
     * @param string $column Column name without quotes
     * @return string Column name with prefixed table name
     */
    public function addTablePrefixToColumn(string $column): string
    {
        return $this->processColumnName($column);
    }

    /**
     * {@inheritDoc}
     */
    protected function processTableName(string $table): string
    {
        $components = explode('.', $table);
        $componentsCount = count($components);
        $components[$componentsCount - 1] = $this->tablePrefix.$components[$componentsCount - 1];

        return implode('.', $components);
    }

    /**
     * {@inheritDoc}
     */
    protected function processColumnName(string $column, array $tablesToProcess = null): string
    {
        $columnPosition = strrpos($column, '.');
        if ($columnPosition === false) {
            return $column;
        }

        $table = substr($column, 0, $columnPosition);
        if ($tablesToProcess !== null && !in_array($table, $tablesToProcess)) {
            return $column;
        }

        $column = substr($column, $columnPosition);
        return $this->addTablePrefix($table).$column;
    }
}
