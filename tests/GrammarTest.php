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
        $statement = $grammar->compile(
            (new Query())->select('foo')->from('table')
        );
        $this->assertEquals($this->plainSQL('SELECT `foo` FROM `table`'), $this->plainSQL($statement->getSQL()));
        $this->assertEquals([], $statement->getBindings());

        // One more select
        $statement = $grammar->compile(
            (new Query())->from('table')
        );
        $this->assertEquals($this->plainSQL('SELECT * FROM `table`'), $this->plainSQL($statement->getSQL()));
        $this->assertEquals([], $statement->getBindings());
    }

    /**
     * Tests the compileSelect method
     */
    public function testCompileSelect()
    {
        $grammar = new CommonGrammar();

        $statement = $grammar->compileSelect(
            (new Query('prefix_'))
                ->select([
                    'table.*',
                    'f' => 'table.foo',
                    'b' => 'table.bar',
                    'r' => new Raw('t.column'),
                    'sub`query' => (new Query('test_'))->select('foo')->from('bar')
                ])
                ->from('table', 't')
                ->offset(140)
                ->limit(12)
        );
        $this->assertEquals(
            $this->plainSQL('
                SELECT
                    `prefix_table`.*,
                    `prefix_table`.`foo` AS `f`, 
                    `prefix_table`.`bar` AS `b`, 
                    (t.column) AS `r`,
                    (SELECT `foo` FROM `test_bar`) AS `sub``query`
                FROM `prefix_table` AS `t`
                OFFSET ?
                LIMIT ?
            '),
            $this->plainSQL($statement->getSQL())
        );
        $this->assertEquals([140, 12], $statement->getBindings());

        // No columns
        $statement = $grammar->compileSelect(
            (new Query('prefix_'))->from('table', 't')
        );
        $this->assertEquals(
            $this->plainSQL('
                SELECT *
                FROM `prefix_table` AS `t`
            '),
            $this->plainSQL($statement->getSQL())
        );
        $this->assertEquals([], $statement->getBindings());

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
        $statement = $grammar->compileSelect(
            (new Query('prefix_'))->from('database.table', 't')
        );
        $this->assertEquals(
            $this->plainSQL('SELECT * FROM `database`.`prefix_table` AS `t`'),
            $this->plainSQL($statement->getSQL())
        );
        $this->assertEquals([], $statement->getBindings());

        // Raw from
        $statement = $grammar->compileSelect(
            (new Query())->from(new Raw('TABLES(?)', ['foo']), 't')
        );
        $this->assertEquals(
            $this->plainSQL('SELECT * FROM (TABLES(?)) AS `t`'),
            $this->plainSQL($statement->getSQL())
        );
        $this->assertEquals(['foo'], $statement->getBindings());

        // From subquery
        $statement = $grammar->compileSelect(
            (new Query())->from(function (Query $query) {
                $query->select(['foo', new Raw('? + ?', [2, 3])])->from('other');
            }, 't')
        );
        $this->assertEquals(
            $this->plainSQL('SELECT * FROM (SELECT `foo`, (? + ?) FROM `other`) AS `t`'),
            $this->plainSQL($statement->getSQL())
        );
        $this->assertEquals([2, 3], $statement->getBindings());
    }

    /**
     * Tests the OFFSET and the LIMIT parts compilation
     */
    public function testOffsetAndLimit()
    {
        $grammar = new CommonGrammar();

        // Specify only offset
        $statement = $grammar->compileSelect(
            (new Query())->from('table')->offset(140)
        );
        $this->assertEquals(
            $this->plainSQL('SELECT * FROM `table` OFFSET ?'),
            $this->plainSQL($statement->getSQL())
        );
        $this->assertEquals([140], $statement->getBindings());

        // Specify only limit
        $statement = $grammar->compileSelect(
            (new Query())->from('table')->limit(12)
        );
        $this->assertEquals(
            $this->plainSQL('SELECT * FROM `table` LIMIT ?'),
            $this->plainSQL($statement->getSQL())
        );
        $this->assertEquals([12], $statement->getBindings());

        // Specify complex values
        $statement = $grammar->compileSelect(
            (new Query())
                ->from('table')
                ->offset(new Raw('? + ?', [12, 19]))
                ->limit(function (Query $query) {
                    $query->select(new Raw('AVG(price)'))->from('prices');
                })
        );
        $this->assertEquals(
            $this->plainSQL('SELECT * FROM `table` OFFSET (? + ?) LIMIT (SELECT (AVG(price)) FROM `prices`)'),
            $this->plainSQL($statement->getSQL())
        );
        $this->assertEquals([12, 19], $statement->getBindings());
    }
}
