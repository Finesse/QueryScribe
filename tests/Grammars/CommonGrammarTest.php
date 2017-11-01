<?php

namespace Finesse\QueryScribe\Tests\Grammars;

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
     * Tests the `compile` method
     */
    public function testCompile()
    {
        $grammar = new CommonGrammar();

        // Select
        $this->assertStatement('SELECT "foo" FROM "table"', [], $grammar->compile(
            (new Query())->select('foo')->from('table')
        ));

        // One more select
        $this->assertStatement('SELECT * FROM "table"', [], $grammar->compile(
            (new Query())->from('table')
        ));

        // Insert
        $this->assertStatement('
            INSERT INTO "table" ("weight", "name") 
            VALUES (?, ?)
        ', [12, 'foo'], $grammar->compile(
            (new Query())->table('table')->insert(['weight' => 12, 'name' => 'foo'])
        ));

        // Update
        $this->assertStatement('UPDATE "table" SET "name" = ?', ['Joe'], $grammar->compile(
            (new Query())->table('table')->update(['name' => 'Joe'])
        ));

        // Delete
        $this->assertStatement('DELETE FROM "table"', [], $grammar->compile(
            (new Query())->delete()->from('table')
        ));
    }

    /**
     * Tests the `compileSelect` method
     */
    public function testCompileSelect()
    {
        $grammar = new CommonGrammar();

        // Comprehensive case
        $this->assertStatement('
            SELECT
                "prefix_table".*,
                "prefix_table"."foo" AS "f", 
                "prefix_table"."bar" AS "b", 
                (t.column) AS "r",
                (SELECT "foo" FROM "test_bar") AS "sub""query",
                COUNT(*) AS "count",
                MIN("prefix_table"."bar"),
                MAX("baz"),
                AVG("boo") AS "avg",
                SUM((baz * boo))
            FROM "prefix_table" AS "t"
            WHERE "price" > ?
            ORDER BY "position" ASC
            OFFSET ?
            LIMIT ?
        ', [100, 140, 12], $grammar->compileSelect(
            (new Query('prefix_'))
                ->select([
                    'table.*',
                    'f' => 'table.foo',
                    'b' => 'table.bar',
                    'r' => new Raw('t.column'),
                    'sub"query' => (new Query('test_'))->select('foo')->from('bar')
                ])
                ->count('*', 'count')
                ->min('table.bar')
                ->max('baz')
                ->avg('boo', 'avg')
                ->sum(new Raw('baz * boo'))
                ->from('table', 't')
                ->where('price', '>', 100)
                ->orderBy('position')
                ->offset(140)
                ->limit(12)
        ));

        // Simple count
        $this->assertStatement('SELECT COUNT(*) FROM "prefix_table"', [], $grammar->compileSelect(
            (new Query('prefix_'))->from('table')->count()
        ));

        // No columns
        $this->assertStatement('
            SELECT *
            FROM "prefix_table" AS "t"
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
     * Tests the `compileInsert` method
     */
    public function testCompileInsert()
    {
        $grammar = new CommonGrammar();

        // Insert values
        $this->assertStatement('
            INSERT INTO "demo_posts" ("title", "author_id", "date", "description")
            VALUES
                (?, ?, DEFAULT, DEFAULT),
                (?, DEFAULT, (NOW()), DEFAULT),
                (DEFAULT, DEFAULT, (SELECT MAX("start") FROM "demo_events" WHERE "type" = ?), ?)
        ', ['Foo!!', 12, 'Bar?', 'post', null], $grammar->compileInsert(
            (new Query('demo_'))
                ->table('posts')
                ->insert([
                    ['title' => 'Foo!!', 'author_id' => 12],
                    ['title' => 'Bar?', 'date' => new Raw('NOW()')],
                    ['description' => null, 'date' => function (Query $query) {
                        $query->max('start')->from('events')->where('type', 'post');
                    }]
                ])
        ));

        // Insert from select
        $this->assertStatement('
            INSERT INTO "demo_posts" ("name", "address") (
                SELECT "first_name", "home_address"
                FROM "demo_users"
            )
        ', [], $grammar->compileInsert(
            (new Query('demo_'))
                ->table('posts')
                ->insertFromSelect(['name', 'address'], function (Query $query) {
                    $query->select(['first_name', 'home_address'])->from('users');
                })
        ));

        // No table
        $this->assertException(InvalidQueryException::class, function () use ($grammar) {
            $grammar->compileInsert(
                (new Query())->insert(['value' => 1, 'name' => 'foo'])
            );
        });

        // Unknown insert type
        $this->assertException(InvalidQueryException::class, function () use ($grammar) {
            $query = (new Query())->table('bar');
            $query->insert = 'VALUES (0, 1, 2)';
            $grammar->compileInsert($query);
        }, function (InvalidQueryException $exception) {
            $this->assertStringStartsWith('Unknown insert instruction type', $exception->getMessage());
        });
    }

    /**
     * Tests the `compileUpdate` method
     */
    public function testCompileUpdate()
    {
        $grammar = new CommonGrammar();

        // Comprehensive case
        $this->assertStatement('
            UPDATE "pref_table" AS "t"
            SET
                "name" = ?,
                "pref_table"."price" = ?,
                "date" = (NEXT_DAY(?)),
                "description" = (
                    SELECT "title"
                    FROM "pref_stories"
                    LIMIT ?
                )
            WHERE "old" = ?
            ORDER BY "date" DESC
            OFFSET ?
            LIMIT ?
        ', ['Hello darkness', 145.5, 56, 1, true, 2, 10], $grammar->compileUpdate(
            (new Query('pref_'))
                ->table('table', 't')
                ->where('old', true)
                ->orderBy('date', 'desc')
                ->offset(2)
                ->limit(10)
                ->update([
                    'name' => 'Hello darkness',
                    'table.price' => 145.5,
                    'date' => new Raw('NEXT_DAY(?)', [56]),
                    'description' => function (Query $query) {
                        $query->from('stories')->select('title')->limit(1);
                    }
                ])
        ));

        // No table
        $this->assertException(InvalidQueryException::class, function () use ($grammar) {
            $grammar->compileUpdate(
                (new Query())->update(['value' => 1, 'name' => 'foo'])
            );
        }, function (InvalidQueryException $exception) {
            $this->assertEquals('The updated table is not set', $exception->getMessage());
        });

        // No updated values
        $this->assertException(InvalidQueryException::class, function () use ($grammar) {
            $grammar->compileUpdate(
                (new Query())->table('foo')
            );
        }, function (InvalidQueryException $exception) {
            $this->assertEquals('The updated values are not set', $exception->getMessage());
        });
    }

    /**
     * Tests the `compileDelete` method
     */
    public function testCompileDelete()
    {
        $grammar = new CommonGrammar();

        // Comprehensive case
        $this->assertStatement('
            DELETE FROM "test_table"
            WHERE "date" < ?
            ORDER BY "name" ASC
            OFFSET ?
            LIMIT ?
        ', ['2017-01-01', 10, 5], $grammar->compileDelete(
            (new Query('test_'))
                ->delete()
                ->from('table')
                ->where('date', '<', '2017-01-01')
                ->orderBy('name')
                ->offset(10)
                ->limit(5)
        ));

        // No explicit `delete` call
        $this->assertStatement('DELETE FROM "names" WHERE "foo" = ?', ['bar'], $grammar->compileDelete(
            (new Query())->table('names')->where('foo', 'bar')
        ));

        // No table
        $this->assertException(InvalidQueryException::class, function () use ($grammar) {
            $grammar->compileDelete(
                (new Query())->delete()
            );
        }, function (InvalidQueryException $exception) {
            $this->assertEquals('The FROM table is not set', $exception->getMessage());
        });
    }

    /**
     * Tests the `quoteIdentifier` and `quotePlainIdentifier` methods
     */
    public function testQuoteIdentifier()
    {
        $grammar = new CommonGrammar();

        $this->assertEquals('"name"', $grammar->quotePlainIdentifier('name'));
        $this->assertEquals('"sub""name"', $grammar->quotePlainIdentifier('sub"name'));
        $this->assertEquals('"*"', $grammar->quotePlainIdentifier('*'));

        $this->assertEquals('"name"', $grammar->quoteIdentifier('name'));
        $this->assertEquals('"table".*', $grammar->quoteIdentifier('table.*'));
        $this->assertEquals('"database"."table"."col""umn"', $grammar->quoteIdentifier('database.table.col"umn'));
    }

    /**
     * Tests the FROM part compilation
     */
    public function testCompileFrom()
    {
        $grammar = new CommonGrammar();

        // Simple from
        $this->assertStatement('SELECT * FROM "database"."prefix_table" AS "t"', [], $grammar->compileSelect(
            (new Query('prefix_'))->from('database.table', 't')
        ));

        // Raw from
        $this->assertStatement('SELECT * FROM (TABLES(?)) AS "t"', ['foo'], $grammar->compileSelect(
            (new Query())->from(new Raw('TABLES(?)', ['foo']), 't')
        ));

        // From subquery
        $this->assertStatement('
            SELECT * 
            FROM (
                SELECT "foo", (? + ?)
                FROM "other"
            ) AS "t"
        ', [2, 3], $grammar->compileSelect(
            (new Query())->from(function (Query $query) {
                $query->select(['foo', new Raw('? + ?', [2, 3])])->from('other');
            }, 't')
        ));
    }

    /**
     * Tests the WHERE part compilation
     */
    public function testCompileWhere()
    {
        $grammar = new CommonGrammar();

        $this->assertStatement('
            SELECT *
            FROM "test_posts"
            WHERE
                (
                    (
                        "date" < (NOW()) OR
                        (ARE_ABOUT_EQUAL(title, description))
                    ) AND
                    ("position" NOT BETWEEN ? AND (
                        SELECT MAX("price")
                        FROM "test_products"
                    )) AND (
                        "foo" = "bar" AND
                        "bar" != "baz"
                    ) OR (
                        "title" LIKE ? AND
                        "type" = ?
                    ) OR
                    NOT EXISTS(
                        SELECT *
                        FROM "test_comments"
                        WHERE
                            "test_posts"."id" = "test_comments"."post_id" AND
                            "content" = ?
                    )
                ) AND
                (MONTH(date)) IN (?, ?, ?) AND
                "position" IS NULL AND
                "author_id" NOT IN (
                    SELECT "id"
                    FROM "test_users"
                    WHERE "deleted" = ?
                )
        ', [0, '%boss%', 'Important', 'Hello', 1, 4, 6, true], $grammar->compileSelect(
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
                ->whereNotIn('author_id', function (Query $query) {
                    $query->select('id')->from('users')->where('deleted', true);
                })
        ));

        // Unknown criterion type
        $this->assertException(InvalidQueryException::class, function () use ($grammar) {
            $query = (new Query())->from('test');
            $query->where[] = new class(Criterion::APPEND_RULE_AND) extends Criterion {};
            $grammar->compileSelect($query);
        }, function (InvalidQueryException $exception) {
            $this->assertStringStartsWith('The given criterion', $exception->getMessage());
        });

        // Unknown append type
        $this->assertException(InvalidQueryException::class, function () use ($grammar) {
            $grammar->compileSelect(
                (new Query())
                    ->from('test')
                    ->whereRaw('TRUE')
                    ->where('foo', '=', 'bar', -2394723)
            );
        }, function (InvalidQueryException $exception) {
            $this->assertStringStartsWith('Unknown criterion append rule', $exception->getMessage());
        });
    }

    /**
     * Tests the ORDER part compilation
     */
    public function testCompileOrder()
    {
        $grammar = new CommonGrammar();

        $this->assertStatement('
            SELECT *
            FROM "test_stories"
            ORDER BY
                "category" ASC,
                (
                    SELECT "foo"
                    FROM "test2_bar"
                    WHERE "foo" > ?
                ) DESC,
                RANDOM()
        ', [3], $grammar->compileSelect(
            (new Query('test_'))
                ->from('stories')
                ->orderBy('category', 'asc')
                ->orderBy((new Query('test2_'))->select('foo')->from('bar')->where('foo', '>', 3), 'DESC')
                ->inRandomOrder()
        ));

        $this->assertException(InvalidQueryException::class, function () use ($grammar) {
            $query = (new Query())->from('table');
            $query->order[] = 'foo ASC';
            $grammar->compileSelect($query);
        }, function (InvalidQueryException $exception) {
            $this->assertEquals('The given order `foo ASC` is unknown', $exception->getMessage());
        });
    }

    /**
     * Tests the OFFSET and the LIMIT parts compilation
     */
    public function testCompileOffsetAndLimit()
    {
        $grammar = new CommonGrammar();

        // Specify only offset
        $this->assertStatement('SELECT * FROM "table" OFFSET ?', [140], $grammar->compileSelect(
            (new Query())->from('table')->offset(140)
        ));

        // Specify only limit
        $this->assertStatement('SELECT * FROM "table" LIMIT ?', [12], $statement = $grammar->compileSelect(
            (new Query())->from('table')->limit(12)
        ));

        // Specify complex values
        $this->assertStatement('
            SELECT * 
            FROM "table" 
            OFFSET (? + ?) 
            LIMIT (SELECT (AVG(price)) FROM "prices")
        ', [12, 19], $grammar->compileSelect(
            (new Query())
                ->from('table')
                ->offset(new Raw('? + ?', [12, 19]))
                ->limit(function (Query $query) {
                    $query->select(new Raw('AVG(price)'))->from('prices');
                })
        ));
    }

    /**
     * Tests that an errors in a subquery is passed with the proper message
     */
    public function testErrorInSubQuery()
    {
        $grammar = new CommonGrammar();

        $this->assertException(InvalidQueryException::class, function () use ($grammar) {
            $grammar->compileSelect(
                (new Query())
                    ->select('*')
                    ->select(function (Query $query) {
                        $query->select('name')->from('users');
                        $query->order[] = 'status DESC';
                        return $query;
                    }, 'useless')
                    ->from('table1')
            );
        }, function (InvalidQueryException $exception) {
            $this->assertStringStartsWith('Error in subquery: ', $exception->getMessage());
        });
    }
}
