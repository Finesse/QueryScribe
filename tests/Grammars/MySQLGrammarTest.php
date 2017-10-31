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

        $this->assertStatement('
            SELECT `description` AS `des``ion`
            FROM `pref_table`
            WHERE `goal` LIKE ?
        ', ['win%'], $grammar->compileSelect(
            (new Query('pref_'))
                ->from('table')
                ->select('description', 'des`ion')
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
            FROM `test_table`
            ORDER BY
                `category` ASC,
                RAND()
        ', [], $grammar->compileSelect(
            (new Query('test_'))
                ->from('table')
                ->orderBy('category')
                ->inRandomOrder()
        ));
    }
}