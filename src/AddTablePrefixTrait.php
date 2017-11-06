<?php

namespace Finesse\QueryScribe;

/**
 * Helps to add table prefix to table names
 *
 * @author Surgie
 */
trait AddTablePrefixTrait
{
    /**
     * @var string Tables prefix
     */
    protected $tablePrefix;

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
     * Adds the table prefix to a column name (if it contains the table name).
     *
     * @param string $column Column name without quotes
     * @return string Column name with prefixed table name
     */
    public function addTablePrefixToColumn(string $column): string
    {
        $components = explode('.', $column);
        $componentsCount = count($components);
        if ($componentsCount > 1) {
            $components[$componentsCount - 2] = $this->tablePrefix.$components[$componentsCount - 2];
        }

        return implode('.', $components);
    }
}
