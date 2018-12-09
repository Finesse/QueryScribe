<?php

namespace Finesse\QueryScribe\Tests;

use Finesse\QueryScribe\Exceptions\InvalidArgumentException;
use Finesse\QueryScribe\Query;
use Finesse\QueryScribe\QueryBricks\Criteria\ColumnsCriterion;
use Finesse\QueryScribe\QueryBricks\Criteria\ValueCriterion;
use Finesse\QueryScribe\QueryBricks\Join;

/**
 * Tests the JoinTrait trait
 *
 * @author Surgie
 */
class JoinTraitTest extends TestCase
{
    public function testJoin()
    {
        $query = (new Query)
            ->table('one')
            ->join('two', 'two.id', '=', 'one.two_id')
            ->innerJoin('three', 'three.one_id', '>', 'one.id')
            ->outerJoin(
                [function (Query $query) {
                    $query->from('four');
                }, 'f'],
                function (Query $query) {
                    $query
                        ->on('f.foo', '>', 'one.foo')
                        ->orOn('f.bar', '<', 'one.bar')
                        ->orWhere('f.baz', 'hello');
                }
            )
            ->leftJoin(['five', 'ff'], [
                ['ff.id', 'one.id'],
                ['ff.author', 'one.author']
            ])
            ->rightJoin('six', 'six.date', 'one.date')
            ->crossJoin('seven');

        $this->assertAttributeEquals([
            new Join('INNER', 'two', null, [
                new ColumnsCriterion('two.id', '=', 'one.two_id', 'AND'),
            ]),
            new Join('INNER', 'three', null, [
                new ColumnsCriterion('three.one_id', '>', 'one.id', 'AND'),
            ]),
            new Join('OUTER', (new Query)->from('four'), 'f', [
                new ColumnsCriterion('f.foo', '>', 'one.foo', 'AND'),
                new ColumnsCriterion('f.bar', '<', 'one.bar', 'OR'),
                new ValueCriterion('f.baz', '=', 'hello', 'OR'),
            ]),
            new Join('LEFT', 'five', 'ff', [
                new ColumnsCriterion('ff.id', '=', 'one.id', 'AND'),
                new ColumnsCriterion('ff.author', '=', 'one.author', 'AND'),
            ]),
            new Join('RIGHT', 'six', null, [
                new ColumnsCriterion('six.date', '=', 'one.date', 'AND'),
            ]),
            new Join('CROSS', 'seven', null, []),
        ], 'join', $query);

        // Wrong arguments
        $this->assertException(InvalidArgumentException::class, function () {
            (new Query)->join(new \stdClass);
        }, function (InvalidArgumentException $exception) {
            $this->assertContains('stdClass', $exception->getMessage());
        });
        $this->assertException(InvalidArgumentException::class, function () {
            (new Query)->join(['table', new \stdClass]);
        }, function (InvalidArgumentException $exception) {
            $this->assertContains('stdClass', $exception->getMessage());
        });
    }
}
