<?php

namespace Finesse\QueryScribe\QueryBricks;

use Finesse\QueryScribe\Query;
use Finesse\QueryScribe\StatementInterface;

/**
 * One table join.
 *
 * You MUST NOT change the public variables values.
 *
 * @author Surgie
 */
class Join
{
    /**
     * @var string Join type name in uppercase (INNER, LEFT, etc.)
     * @readonly
     */
    public $type;

    /**
     * @var string|Query|StatementInterface The joined table name
     * @readonly
     */
    public $table;

    /**
     * @var string|null The joined table alias
     * @readonly
     */
    public $tableAlias;

    /**
     * @var Criterion[] The joining criteria (coming after ON)
     * @readonly
     */
    public $criteria;

    /**
     * @param string $type Join type name (INNER, LEFT, etc.)
     * @param string|Query|StatementInterface $table The joined table name
     * @param string|null $tableAlias The joined table alias
     * @param Criterion[] $criteria The joining criteria (coming after ON)
     */
    public function __construct(string $type, $table, string $tableAlias = null, array $criteria)
    {
        $this->type = strtoupper($type);
        $this->table = $table;
        $this->tableAlias = $tableAlias;
        $this->criteria = $criteria;
    }
}
