<?php

namespace Finesse\QueryScribe\Tests\Grammars;

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
            (new Query('demo_'))
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
            INSERT INTO "demo_posts" ("title", "author_id")
            VALUES (?, ?)
        ', ['Foo!!', 12], $statements[0]);
        $this->assertStatement('
            INSERT INTO "demo_posts" ("title", "date")
            VALUES (?, (NOW()))
        ', ['Bar?'], $statements[1]);
        $this->assertStatement('
            INSERT INTO "demo_posts" ("description", "date")
            VALUES (?, (SELECT MAX("start") FROM "demo_events" WHERE "type" = ?))
        ', [null, 'post'], $statements[2]);

        // Insert from select
        $this->assertStatement('
            INSERT INTO "demo_posts" ("name", "address")
            SELECT "first_name", "home_address"
            FROM "demo_users"
        ', [], $statements[3]);
    }
}
