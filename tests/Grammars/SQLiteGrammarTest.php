<?php

namespace Finesse\QueryScribe\Tests\Grammars;

use Finesse\QueryScribe\Exceptions\InvalidQueryException;
use Finesse\QueryScribe\Grammars\SQLiteGrammar;
use Finesse\QueryScribe\Query;
use Finesse\QueryScribe\Raw;
use Finesse\QueryScribe\Tests\TestCase;

/**
 * Tests the SQLiteGrammar class
 *
 * @author Surgie
 */
class SQLiteGrammarTest extends TestCase
{
    /**
     * Tests the `compileInsert` method
     */
    public function testCompileInsert()
    {
        $grammar = new SQLiteGrammar();

        $statements = $grammar->compileInsert(
            (new Query)
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
        $this->assertCount(4, $statements);

        // Insert values
        $this->assertStatement('
            INSERT INTO "posts" ("title", "author_id")
            VALUES (?, ?)
        ', ['Foo!!', 12], $statements[0]);
        $this->assertStatement('
            INSERT INTO "posts" ("title", "date")
            VALUES (?, (NOW()))
        ', ['Bar?'], $statements[1]);
        $this->assertStatement('
            INSERT INTO "posts" ("description", "date")
            VALUES (?, (SELECT MAX("start") FROM "events" WHERE "type" = ?))
        ', [null, 'post'], $statements[2]);

        // Insert from select
        $this->assertStatement('
            INSERT INTO "posts" ("name", "address")
            SELECT "first_name", "home_address"
            FROM "users"
        ', [], $statements[3]);
    }

    /**
     * Tests the `compileUpdate` method
     */
    public function testCompileUpdate()
    {
        $grammar = new SQLiteGrammar();

        $this->assertStatement('UPDATE "table" SET "foo" = ?', ['bar'], $grammar->compileUpdate(
            (new Query)->table('table')->addUpdate(['foo' => 'bar'])
        ));

        $this->assertException(InvalidQueryException::class, function () use ($grammar) {
            $grammar->compileUpdate((new Query)->from('table', 't')->addUpdate(['foo' => 'bar']));
        }, function (InvalidQueryException $exception) {
            $this->assertEquals('Table alias is not allowed in update query', $exception->getMessage());
        });
    }

    /**
     * Tests the `compileDelete` method
     */
    public function testCompileDelete()
    {
        $grammar = new SQLiteGrammar();

        $this->assertStatement('DELETE FROM "table"', [], $grammar->compileDelete(
            (new Query)->setDelete()->from('table')
        ));

        $this->assertException(InvalidQueryException::class, function () use ($grammar) {
            $grammar->compileDelete((new Query)->setDelete()->from('table', 't'));
        }, function (InvalidQueryException $exception) {
            $this->assertEquals('Table alias is not allowed in delete query', $exception->getMessage());
        });
    }

    /**
     * Tests the `compileEmptyInCriterion` method
     */
    public function testCompileEmptyInCriterion()
    {
        $grammar = new SQLiteGrammar();

        $this->assertStatement('
            SELECT *
            FROM "posts"
            WHERE
              "type" IN () AND
              "status" NOT IN ()
        ', [], $grammar->compileSelect(
            (new Query)
                ->table('posts')
                ->whereIn('type', [])
                ->whereNotIn('status', [])
        ));
    }
}
