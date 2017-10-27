<?php

namespace Finesse\QueryScribe;

use Finesse\QueryScribe\Common\StatementInterface;
use Finesse\QueryScribe\Common\AddTablePrefixTrait;
use Finesse\QueryScribe\Common\MakeRawTrait;
use Finesse\QueryScribe\Exceptions\InvalidArgumentException;

/**
 * Represents a built query. It contains only a basic query data, not a SQL text.
 *
 * @author Surgie
 */
class Query
{
    use AddTablePrefixTrait, MakeRawTrait;

    /**
     * @var string[]|StatementInterface[] Columns names to select. The string indexes are the aliases names.
     */
    public $select = ['*'];

    /**
     * @var string|StatementInterface|null Query target table name (prefixed)
     */
    public $from = null;

    /**
     * @var int|StatementInterface|null Offset
     */
    public $offset = null;

    /**
     * @var int|StatementInterface|null Limit
     */
    public $limit = null;

    /**
     * @var GrammarInterface Query to SQL converter
     */
    protected $grammar;

    /**
     * @param GrammarInterface $grammar Query to SQL converter
     * @param string $tablePrefix Tables prefix
     */
    public function __construct(GrammarInterface $grammar, string $tablePrefix = '')
    {
        $this->grammar = $grammar;
        $this->tablePrefix = $tablePrefix;
    }

    /**
     * Sets the target table.
     *
     * @param string|StatementInterface $table Table name
     * @return self
     * @throws InvalidArgumentException
     */
    public function from($table): self
    {
        if (!is_string($table) && !($table instanceof StatementInterface)) {
            throw InvalidArgumentException::create('Argument $table', $table, ['string', StatementInterface::class]);
        }

        $this->from = $this->addTablePrefix($table);
        return $this;
    }

    /**
     * Sets column or columns of the SELECT section.
     *
     * @param string|StatementInterface|string[]|StatementInterface[] $columns Columns to set. If string or raw, one column is set.
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
     * @param string|StatementInterface|string[]|StatementInterface[] $columns Columns to add. If string or raw, one column is added.
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
            } elseif ($column instanceof StatementInterface) {
            } else {
                throw InvalidArgumentException::create(
                    'Argument $columns['.$alias.']',
                    $column,
                    ['string', StatementInterface::class]
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
     * Sets the offset.
     *
     * @param int|StatementInterface|null $offset Offset. Null removes the offset.
     * @return self
     */
    public function offset($offset): self
    {
        if ($offset !== null && !is_numeric($offset) && !($offset instanceof StatementInterface)) {
            throw InvalidArgumentException::create(
                'Argument $limit',
                $offset,
                ['integer', 'null', StatementInterface::class]
            );
        }

        $this->offset = is_numeric($offset) ? (int)$offset : $offset;
        return $this;
    }

    /**
     * Sets the limit.
     *
     * @param int|StatementInterface|null $limit Limit. Null removes the limit.
     * @return self
     */
    public function limit($limit): self
    {
        if ($limit !== null && !is_numeric($limit) && !($limit instanceof StatementInterface)) {
            throw InvalidArgumentException::create(
                'Argument $limit',
                $limit,
                ['integer', 'null', StatementInterface::class]
            );
        }

        $this->limit = is_numeric($limit) ? (int)$limit : $limit;
        return $this;
    }

    /**
     * Compiles SELECT query.
     *
     * @return StatementInterface
     */
    public function get(): StatementInterface
    {
        return $this->grammar->compileSelect($this);
    }
}
