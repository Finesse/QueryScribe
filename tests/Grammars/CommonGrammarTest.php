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
            (new Query())->addSelect('foo')->from('table')
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
            (new Query())->table('table')->addInsert([ 'weight' => 12, 'name' => 'foo'])
        ));

        // Update
        $this->assertStatement('UPDATE "table" SET "name" = ?', ['Joe'], $grammar->compile(
            (new Query())->table('table')->addUpdate([ 'name' => 'Joe'])
        ));

        // Delete
        $this->assertStatement('DELETE FROM "table"', [], $grammar->compile(
            (new Query())->setDelete()->from('table')
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
                "table".*,
                "table"."foo" AS "f", 
                "table"."bar" AS "b", 
                "t"."column" AS "r",
                (SELECT "foo" FROM "bar") AS "sub""query",
                COUNT(*) AS "count",
                MIN("table"."bar"),
                MAX("baz"),
                AVG("boo") AS "avg",
                SUM((baz * boo))
            FROM "table" AS "t"
            WHERE "price" > ?
            ORDER BY "position" ASC
            LIMIT ?
            OFFSET ?
        ', [100, 12, 140], $grammar->compileSelect(
            (new Query())
                ->addSelect([
                    'table.*',
                    'f' => 'table.foo',
                    'b' => 'table.bar',
                    'r' => 't.column',
                    'sub"query' => (new Query())->addSelect('foo')->from('bar')
                ])
                ->addCount('*', 'count')
                ->addMin('table.bar')
                ->addMax('baz')
                ->addAvg('boo', 'avg')
                ->addSum(new Raw('baz * boo'))
                ->from('table', 't')
                ->where('price', '>', 100)
                ->orderBy('position')
                ->offset(140)
                ->limit(12)
        ));

        // Simple count
        $this->assertStatement('SELECT COUNT(*) FROM "table"', [], $grammar->compileSelect(
            (new Query())->from('table')->addCount()
        ));

        // No columns
        $this->assertStatement('
            SELECT *
            FROM "table" AS "t"
        ', [], $grammar->compileSelect(
            (new Query())->from('table', 't')
        ));

        // No from
        $this->assertException(InvalidQueryException::class, function () use ($grammar) {
            $grammar->compileSelect(
                (new Query())->addSelect(['id', 'name'])
            );
        });
    }

    /**
     * Tests the `compileInsert` method
     */
    public function testCompileInsert()
    {
        $grammar = new CommonGrammar();

        $statements = $grammar->compileInsert(
            (new Query())
                ->table('posts')
                ->addInsert([
                    ['title' => 'Foo!!', 'author_id' => 12],
                    ['title' => 'Bar?', 'date' => new Raw('NOW()')],
                    ['description' => null, 'date' => function (Query $query) {
                        $query->addMax('start')->from('events')->where('type', 'post');
                    }]
                ])
                ->addInsertFromSelect(['name', 'address'], function (Query $query) {
                    $query->addSelect(['first_name', 'home_address'])->from('users');
                })
        );
        $this->assertCount(2, $statements);

        // Insert values
        $this->assertStatement('
            INSERT INTO "posts" ("title", "author_id", "date", "description")
            VALUES
                (?, ?, DEFAULT, DEFAULT),
                (?, DEFAULT, (NOW()), DEFAULT),
                (DEFAULT, DEFAULT, (SELECT MAX("start") FROM "events" WHERE "type" = ?), ?)
        ', ['Foo!!', 12, 'Bar?', 'post', null], $statements[0]);

        // Insert from select
        $this->assertStatement('
            INSERT INTO "posts" ("name", "address")
            SELECT "first_name", "home_address"
            FROM "users"
        ', [], $statements[1]);

        // No table
        $this->assertException(InvalidQueryException::class, function () use ($grammar) {
            $grammar->compileInsert(
                (new Query())->addInsert(['value' => 1, 'name' => 'foo'])
            );
        });

        // Unknown insert type
        $this->assertException(InvalidQueryException::class, function () use ($grammar) {
            $query = (new Query())->table('bar');
            $query->insert[] = 'VALUES (0, 1, 2)';
            $grammar->compileInsert($query);
        }, function (InvalidQueryException $exception) {
            $this->assertEquals('Unknown type of insert instruction #0: string', $exception->getMessage());
        });

        // No insert rows
        $this->assertCount(0, $grammar->compileInsert(
            (new Query())->table('foo')
        ));

        // With table alias
        $this->assertException(InvalidQueryException::class, function () use ($grammar) {
            $grammar->compileInsert((new Query())->table('table', 't')->addInsert(['foo' => 'bar']));
        }, function (InvalidQueryException $exception) {
            $this->assertEquals('Table alias is not allowed in insert query', $exception->getMessage());
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
            UPDATE "table" AS "t"
            SET
                "name" = ?,
                "table"."price" = ?,
                "t"."date" = (NEXT_DAY(?)),
                "description" = (
                    SELECT "title"
                    FROM "stories"
                    LIMIT ?
                )
            WHERE "old" = ?
            ORDER BY "date" DESC
            LIMIT ?
            OFFSET ?
        ', ['Hello darkness', 145.5, 56, 1, true, 10, 2], $grammar->compileUpdate(
            (new Query())
                ->table('table', 't')
                ->where('old', true)
                ->orderBy('date', 'desc')
                ->offset(2)
                ->limit(10)
                ->addUpdate([
                    'name' => 'Hello darkness',
                    'table.price' => 145.5,
                    't.date' => new Raw('NEXT_DAY(?)', [56]),
                    'description' => function (Query $query) {
                        $query->from('stories')->addSelect('title')->limit(1);
                    }
                ])
        ));

        // No table
        $this->assertException(InvalidQueryException::class, function () use ($grammar) {
            $grammar->compileUpdate(
                (new Query())->addUpdate([ 'value' => 1, 'name' => 'foo'])
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
            DELETE FROM "table"
            WHERE "date" < ?
            ORDER BY "name" ASC
            LIMIT ?
            OFFSET ?
        ', ['2017-01-01', 5, 10], $grammar->compileDelete(
            (new Query())
                ->setDelete()
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
                (new Query())->setDelete()
            );
        }, function (InvalidQueryException $exception) {
            $this->assertEquals('The FROM table is not set', $exception->getMessage());
        });

        // With table alias
        $this->assertStatement('
            DELETE "n" FROM "names" AS "n"
            WHERE "n"."title" = ?
        ', ['Foo'], $grammar->compileDelete(
            (new Query())->table('names', 'n')->where('n.title', 'Foo')
        ));
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
     * Tests the `escapeLikeWildcards` method.
     */
    public function testEscapeLikeWildcards()
    {
        $grammar = new CommonGrammar();

        $this->assertEquals('foo"bar', $grammar->escapeLikeWildcards('foo"bar'));
        $this->assertEquals('50\\%\\_100\\%', $grammar->escapeLikeWildcards('50%_100%'));
    }

    /**
     * Tests the FROM part compilation
     */
    public function testCompileFrom()
    {
        $grammar = new CommonGrammar();

        // Simple from
        $this->assertStatement('SELECT * FROM "database"."table" AS "t"', [], $grammar->compileSelect(
            (new Query())->from('database.table', 't')
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
                $query->addSelect(['foo', new Raw('? + ?', [2, 3])])->from('other');
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
            FROM "posts"
            WHERE
                (
                    (
                        "date" < (NOW()) OR
                        (ARE_ABOUT_EQUAL(title, description))
                    ) AND
                    ("position" NOT BETWEEN ? AND (
                        SELECT MAX("price")
                        FROM "products"
                    )) AND (
                        "foo" = "bar" AND
                        "bar" != "baz"
                    ) OR (
                        "title" LIKE ? AND
                        "type" = ?
                    ) OR
                    NOT EXISTS(
                        SELECT *
                        FROM "comments"
                        WHERE
                            "posts"."id" = "comments"."post_id" AND
                            "content" = ?
                    )
                ) AND
                (MONTH(date)) IN (?, ?, ?) AND
                "position" IS NULL AND
                "author_id" NOT IN (
                    SELECT "id"
                    FROM "users"
                    WHERE "deleted" = ?
                )
        ', [0, '%boss%', 'Important', 'Hello', 1, 4, 6, true], $grammar->compileSelect(
            (new Query())
                ->from('posts')
                ->where('date', '<', new Raw('NOW()'))
                ->orWhereRaw('ARE_ABOUT_EQUAL(title, description)')
                ->whereNotBetween('position', 0, function (Query $query) {
                    $query->addMax('price')->from('products');
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
                    $query->addSelect('id')->from('users')->where('deleted', true);
                })
        ));

        // Unknown criterion type
        $this->assertException(InvalidQueryException::class, function () use ($grammar) {
            $query = (new Query())->from('test');
            $query->where[] = new class('AND') extends Criterion {};
            $grammar->compileSelect($query);
        }, function (InvalidQueryException $exception) {
            $this->assertStringStartsWith('The given criterion', $exception->getMessage());
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
            FROM "stories"
            ORDER BY
                "category" ASC,
                (
                    SELECT "foo"
                    FROM "bar"
                    WHERE "foo" > ?
                ) DESC,
                RANDOM()
        ', [3], $grammar->compileSelect(
            (new Query())
                ->from('stories')
                ->orderBy('category', 'asc')
                ->orderBy((new Query())->addSelect('foo')->from('bar')->where('foo', '>', 3), 'DESC')
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

        // Specify only limit
        $this->assertStatement('SELECT * FROM "table" LIMIT ?', [12], $statement = $grammar->compileSelect(
            (new Query())->from('table')->limit(12)
        ));

        // Specify limit and offset
        $this->assertStatement('SELECT * FROM "table" LIMIT ? OFFSET ?', [10, 140], $grammar->compileSelect(
            (new Query())->from('table')->limit(10)->offset(140)
        ));

        // Specify complex values
        $this->assertStatement('
            SELECT * 
            FROM "table" 
            LIMIT (SELECT (AVG(price)) FROM "prices")
            OFFSET (? + ?) 
        ', [12, 19], $grammar->compileSelect(
            (new Query())
                ->from('table')
                ->offset(new Raw('? + ?', [12, 19]))
                ->limit(function (Query $query) {
                    $query->addSelect(new Raw('AVG(price)'))->from('prices');
                })
        ));

        // Specify only offset
        $this->assertException(InvalidQueryException::class, function () use ($grammar) {
            $grammar->compileSelect((new Query())->from('table')->offset(10));
        }, function (InvalidQueryException $exception) {
            $this->assertEquals('Offset is not allowed without Limit', $exception->getMessage());
        });
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
                    ->addSelect('*')
                    ->addSelect(function (Query $query) {
                        $query->addSelect('name')->from('users');
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
