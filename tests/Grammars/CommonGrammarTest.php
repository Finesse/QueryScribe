<?php

namespace Finesse\QueryScribe\Tests\Grammars;

use Finesse\QueryScribe\Exceptions\InvalidCriterionException;
use Finesse\QueryScribe\Exceptions\InvalidQueryException;
use Finesse\QueryScribe\Grammars\CommonGrammar;
use Finesse\QueryScribe\Query;
use Finesse\QueryScribe\QueryBricks\Criterion;
use Finesse\QueryScribe\Raw;
use Finesse\QueryScribe\Tests\TestCase;

/**
 * Tests the grammars (how queries are compiled to SQL)
 *
 * @author Surgie
 */
class CommonGrammarTest extends TestCase
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
            WHERE `price` > ?
            OFFSET ?
            LIMIT ?
        ', [100, 140, 12], $grammar->compileSelect(
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
                ->where('price', '>', 100)
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
     * Tests the WHERE part compilation
     */
    public function testWhere()
    {
        $grammar = new CommonGrammar();

        $this->assertStatement('
            SELECT *
            FROM `test_posts`
            WHERE
                (
                    (
                        `date` < (NOW()) OR
                        (ARE_ABOUT_EQUAL(title, description))
                    ) AND
                    `position` NOT BETWEEN(?, (
                        SELECT MAX(`price`)
                        FROM `test_products`
                    )) AND (
                        `foo` = `bar` AND
                        `bar` != `baz`
                    ) OR (
                        `title` LIKE ? AND
                        `type` = ?
                    ) OR
                    NOT EXISTS(
                        SELECT *
                        FROM `test_comments`
                        WHERE
                            `test_posts`.`id` = `test_comments`.`post_id` AND
                            `content` = ?
                    )
                ) AND
                (MONTH(date)) IN (?, ?, ?) AND
                `position` IS NULL
        ', [0, '%boss%', 'Important', 'Hello', 1, 4, 6], $grammar->compileSelect(
            (new Query('test_'))
                ->from('posts')
                ->where('date', '<', new Raw('NOW()'))
                ->orWhereRaw('ARE_ABOUT_EQUAL(title, description)')
                ->whereNotBetween('position', 0, function (Query $query) {
                    $query->max('price')->from('products');
                })
                ->whereColumn([
                    ['foo', 'bar'],
                    ['bar', '!=', 'baz']
                ])
                ->orWhere(function (Query $query) {
                    $query
                        ->where('title', 'like', '%boss%')
                        ->where('type', 'Important');
                })
                ->orWhereNotExists(function (Query $query) {
                    $query
                        ->from('comments')
                        ->whereColumn('posts.id', 'comments.post_id')
                        ->where('content', 'Hello');
                })
                ->whereIn(new Raw('MONTH(date)'), [1, 4, 6])
                ->whereNull('position')
                ->where(function () {}) // Empty group
        ));

        // Unknown criterion type
        $this->assertException(InvalidCriterionException::class, function () use ($grammar) {
            $query = (new Query())->from('test');
            $query->where[] = new class(Criterion::APPEND_RULE_AND) extends Criterion {};
            $grammar->compileSelect($query);
        });

        // Unknown append type
        $this->assertException(InvalidCriterionException::class, function () use ($grammar) {
            $grammar->compileSelect(
                (new Query())
                    ->from('test')
                    ->whereRaw('TRUE')
                    ->where('foo', '=', 'bar', -2394723)
            );
        });
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
