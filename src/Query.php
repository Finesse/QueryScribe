<?php

namespace Finesse\QueryScribe;

use Finesse\QueryScribe\Common\IQueryable;
use Finesse\QueryScribe\Common\TAddTablePrefix;
use Finesse\QueryScribe\Common\TMakeRaw;
use Finesse\QueryScribe\Exceptions\InvalidArgumentException;

/**
 * Represents a built query. It contains only a basic query data, not a SQL text.
 *
 * @author Surgie
 */
class Query
{
    use TAddTablePrefix, TMakeRaw;

    /**
     * @var string[]|IQueryable[] Columns names to select. The string indexes are the aliases names.
     */
    public $select = ['*'];

    /**
     * @var string|IQueryable|null Query target table name (prefixed)
     */
    public $from = null;

    /**
     * @var IGrammar Query to SQL converter
     */
    protected $grammar;

    /**
     * @param IGrammar $grammar Query to SQL converter
     * @param string $tablePrefix Tables prefix
     */
    public function __construct(IGrammar $grammar, string $tablePrefix = '')
    {
        $this->grammar = $grammar;
        $this->tablePrefix = $tablePrefix;
    }

    /**
     * Sets the target table.
     *
     * @param string|IQueryable $table Table name
     * @return self
     * @throws InvalidArgumentException
     */
    public function from($table): self
    {
        if (!is_string($table) && !($table instanceof IQueryable)) {
            throw InvalidArgumentException::create('Argument $table', $table, ['string', IQueryable::class]);
        }

        $this->from = $this->addTablePrefix($table);
        return $this;
    }

    /**
     * Sets column or columns of the SELECT section.
     *
     * @param string|IQueryable|string[]|IQueryable[] $columns Columns to set. If string or raw, one column is set.
     *     If array, many columns are set and string indexes are treated as aliases.
     * @param string|null $alias Column alias name. Used only if the first argument is not an array.
     * @return self
     */
    public function select($columns, string $alias = null): self
    {
        $this->select = [];
        return $this->addSelect($columns, $alias);
    }

    /**
     * Adds column or columns to the SELECT section.
     *
     * @param string|IQueryable|string[]|IQueryable[] $columns Columns to add. If string or raw, one column is added.
     *     If array, many columns are added and string indexes are treated as aliases.
     * @param string|null $alias Column alias name. Used only if the first argument is not an array.
     * @return self
     */
    public function addSelect($columns, string $alias = null): self
    {
        if (!is_array($columns)) {
            if ($alias === null) {
                $columns = [$columns];
            } else {
                $columns = [$alias => $columns];
            }
        }

        foreach ($columns as $alias => $column) {
            if (is_string($column)) {
                $column = $this->addTablePrefixToColumn($column);
            } elseif ($column instanceof IQueryable) {
            } else {
                throw InvalidArgumentException::create(
                    'Argument $columns['.$alias.']',
                    $column,
                    ['string', IQueryable::class]
                );
            }

            if (is_string($alias)) {
                $this->select[$alias] = $column;
            } else {
                $this->select[] = $column;
            }
        }

        return $this;
    }

    /**
     * Compiles SELECT query.
     *
     * @return IQueryable
     */
    public function get(): IQueryable
    {
        return $this->grammar->makeSelect($this);
    }
}
