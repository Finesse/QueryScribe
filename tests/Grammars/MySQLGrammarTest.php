<?php

namespace Finesse\QueryScribe\Tests\Grammars;

use Finesse\QueryScribe\Grammars\MySQLGrammar;
use Finesse\QueryScribe\Query;
use Finesse\QueryScribe\Tests\TestCase;

/**
 * Tests the MySQLGrammar class
 *
 * @author Surgie
 */
class MySQLGrammarTest extends TestCase
{
    /**
     * Tests that the proper quotes are used
     */
    public function testQuotes()
    {
        $grammar = new MySQLGrammar();

        $this->assertEquals('`name`', $grammar->quoteIdentifier('name'));
        $this->assertEquals('`sub``name`', $grammar->quoteIdentifier('sub`name'));
        $this->assertEquals('`*`', $grammar->quoteIdentifier('*'));

        $this->assertEquals('`name`', $grammar->quoteCompositeIdentifier('name'));
        $this->assertEquals('`table`.*', $grammar->quoteCompositeIdentifier('table.*'));
        $this->assertEquals(
            '`database`.`table`.`col``umn`',
            $grammar->quoteCompositeIdentifier('database.table.col`umn')
        );

        // Real query
        $this->assertStatement('
            SELECT `description` AS `des``ion`
            FROM `table`
            WHERE `goal` = ?
        ', ['win'], $grammar->compileSelect(
            (new Query)
                ->from('table')
                ->addSelect('description', 'des`ion')
                ->where('goal', 'win')
        ));
    }

    /**
     * Tests an explicit order compilation
     */
    public function testExplicitOrder()
    {
        $grammar = new MySQLGrammar();

        $this->assertStatement('
            SELECT *
            FROM `table`
            ORDER BY
                FIELD(`type`, ?, ?, ?) DESC,
                FIELD(`category`, ?, ?, ?) ASC
        ', ['third', 'second', 'first', 11, 12, 13], $grammar->compileSelect(
            (new Query)
                ->from('table')
                ->inExplicitOrder('type', ['first', 'second', 'third'])
                ->inExplicitOrder('category', [11, 12, 13], true)
                ->inExplicitOrder('foo', [])
        ));
    }

    /**
     * Tests random order compilation
     */
    public function testRandomOrder()
    {
        $grammar = new MySQLGrammar();

        $this->assertStatement('
            SELECT *
            FROM `table`
            ORDER BY
                `category` ASC,
                RAND()
        ', [], $grammar->compileSelect(
            (new Query)
                ->from('table')
                ->orderBy('category')
                ->inRandomOrder()
        ));
    }

    /**
     * Tests like criterion compilation
     */
    public function testLikeEscaping()
    {
        $grammar = new MySQLGrammar();

        $this->assertStatement('
            SELECT *
            FROM `table`
            WHERE
                `column` LIKE ? AND
                `created_at` = `updated_at`
        ', ['\\%foo%'], $grammar->compileSelect(
            (new Query)
                ->table('table')
                ->where('column', 'like', $grammar->escapeLikeWildcards('%foo').'%')
                ->whereColumn('created_at', 'updated_at')
        ));
    }
}
