<?php

namespace Finesse\QueryScribe\Tests;

use Finesse\QueryScribe\Exceptions\InvalidQueryException;
use Finesse\QueryScribe\Grammars\CommonGrammar;
use Finesse\QueryScribe\Query;
use Finesse\QueryScribe\Raw;

/**
 * Tests the grammars (how queries are compiled to SQL)
 *
 * @author Surgie
 */
class GrammarTest extends TestCase
{
    /**
     * Tests the compile method
     */
    public function testCompile()
    {
        $grammar = new CommonGrammar();

        // Select
        $this->assertStatement('SELECT `foo` FROM `table`', [], $grammar->compile(
            (new Query())->select('foo')->from('table')
        ));

        // One more select
        $this->assertStatement('SELECT * FROM `table`', [], $grammar->compile(
            (new Query())->from('table')
        ));
    }

    /**
     * Tests the compileSelect method
     */
    public function testCompileSelect()
    {
        $grammar = new CommonGrammar();

        // Comprehensive case
        $this->assertStatement('
            SELECT
                `prefix_table`.*,
                `prefix_table`.`foo` AS `f`, 
                `prefix_table`.`bar` AS `b`, 
                (t.column) AS `r`,
                (SELECT `foo` FROM `test_bar`) AS `sub``query`,
                COUNT(*) AS `count`,
                MIN(`prefix_table`.`bar`),
                MAX(`baz`),
                AVG(`boo`) AS `avg`,
                SUM((baz * boo))
            FROM `prefix_table` AS `t`
            OFFSET ?
            LIMIT ?
        ', [140, 12], $grammar->compileSelect(
            (new Query('prefix_'))
                ->select([
                    'table.*',
                    'f' => 'table.foo',
                    'b' => 'table.bar',
                    'r' => new Raw('t.column'),
                    'sub`query' => (new Query('test_'))->select('foo')->from('bar')
                ])
                ->count('*', 'count')
                ->min('table.bar')
                ->max('baz')
                ->avg('boo', 'avg')
                ->sum(new Raw('baz * boo'))
                ->from('table', 't')
                ->offset(140)
                ->limit(12)
        ));

        // Simple count
        $this->assertStatement('SELECT COUNT(*) FROM `prefix_table`', [], $grammar->compileSelect(
            (new Query('prefix_'))->from('table')->count()
        ));

        // No columns
        $this->assertStatement('
            SELECT *
            FROM `prefix_table` AS `t`
        ', [], $grammar->compileSelect(
            (new Query('prefix_'))->from('table', 't')
        ));

        // No from
        $this->assertException(InvalidQueryException::class, function () use ($grammar) {
            $grammar->compileSelect(
                (new Query('prefix_'))->select(['id', 'name'])
            );
        });
    }

    /**
     * Tests the FROM part compilation
     */
    public function testCompileFrom()
    {
        $grammar = new CommonGrammar();

        // Simple from
        $this->assertStatement('SELECT * FROM `database`.`prefix_table` AS `t`', [], $grammar->compileSelect(
            (new Query('prefix_'))->from('database.table', 't')
        ));

        // Raw from
        $this->assertStatement('SELECT * FROM (TABLES(?)) AS `t`', ['foo'], $grammar->compileSelect(
            (new Query())->from(new Raw('TABLES(?)', ['foo']), 't')
        ));

        // From subquery
        $this->assertStatement('
            SELECT * 
            FROM (
                SELECT `foo`, (? + ?)
                FROM `other`
            ) AS `t`
        ', [2, 3], $grammar->compileSelect(
            (new Query())->from(function (Query $query) {
                $query->select(['foo', new Raw('? + ?', [2, 3])])->from('other');
            }, 't')
        ));
    }

    /**
     * Tests the OFFSET and the LIMIT parts compilation
     */
    public function testOffsetAndLimit()
    {
        $grammar = new CommonGrammar();

        // Specify only offset
        $this->assertStatement('SELECT * FROM `table` OFFSET ?', [140], $grammar->compileSelect(
            (new Query())->from('table')->offset(140)
        ));

        // Specify only limit
        $this->assertStatement('SELECT * FROM `table` LIMIT ?', [12], $statement = $grammar->compileSelect(
            (new Query())->from('table')->limit(12)
        ));

        // Specify complex values
        $this->assertStatement('
            SELECT * 
            FROM `table` 
            OFFSET (? + ?) 
            LIMIT (SELECT (AVG(price)) FROM `prices`)
        ', [12, 19], $grammar->compileSelect(
            (new Query())
                ->from('table')
                ->offset(new Raw('? + ?', [12, 19]))
                ->limit(function (Query $query) {
                    $query->select(new Raw('AVG(price)'))->from('prices');
                })
        ));
    }
}
