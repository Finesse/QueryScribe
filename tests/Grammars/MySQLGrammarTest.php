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
            WHERE `goal` LIKE ?
        ', ['win%'], $grammar->compileSelect(
            (new Query())
                ->from('table')
                ->addSelect('description', 'des`ion')
                ->where('goal', 'like', 'win%')
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
            (new Query())
                ->from('table')
                ->orderBy('category')
                ->inRandomOrder()
        ));
    }
}
