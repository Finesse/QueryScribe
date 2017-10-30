<?php

namespace Finesse\QueryScribe\Tests;

use Finesse\QueryScribe\Exceptions\InvalidArgumentException;
use Finesse\QueryScribe\Query;
use Finesse\QueryScribe\QueryBricks\Criteria\CriteriaCriterion;
use Finesse\QueryScribe\QueryBricks\Criteria\RawCriterion;
use Finesse\QueryScribe\QueryBricks\Criteria\ValueCriterion;
use Finesse\QueryScribe\QueryBricks\Criterion;
use Finesse\QueryScribe\Raw;

/**
 * Tests the WhereTrait trait
 *
 * @author Surgie
 */
class WhereTraitTest extends TestCase
{
    /**
     * Tests the where and orWhere methods
     */
    public function testWhere()
    {
        // Ordinary
        $query = (new Query('pre_'))->where('table.foo', '>', 'bar');
        $this->assertCount(1, $query->where);
        $this->assertInstanceOf(ValueCriterion::class, $query->where[0]);
        $this->assertAttributes(['column' => 'pre_table.foo', 'rule' => '>', 'value' => 'bar', 'appendRule' => Criterion::APPEND_RULE_AND], $query->where[0]);

        // Or where
        $query = (new Query('pre_'))->orWhere('foo', '<', 'bar');
        $this->assertCount(1, $query->where);
        $this->assertInstanceOf(ValueCriterion::class, $query->where[0]);
        $this->assertAttributes(['column' => 'foo', 'rule' => '<', 'value' => 'bar', 'appendRule' => Criterion::APPEND_RULE_OR], $query->where[0]);

        // Omit the rule
        $query = (new Query('pre_'))->where('table.foo', 'bar');
        $this->assertCount(1, $query->where);
        $this->assertInstanceOf(ValueCriterion::class, $query->where[0]);
        $this->assertAttributes(['column' => 'pre_table.foo', 'rule' => '=', 'value' => 'bar'], $query->where[0]);

        // Grouped criteria (by callback)
        $query = (new Query('pre_'))->from('foo', 'f')->where(function (Query $query) {
            $this->assertAttributes(['from' => 'pre_foo', 'fromAlias' => 'f'], $query);
            $query->where('table.column1', 'value1')->orWhere('table.column2', 'value2');
        });
        $this->assertCount(1, $query->where);
        $this->assertInstanceOf(CriteriaCriterion::class, $query->where[0]);
        $this->assertCount(2, $query->where[0]->criteria);
        $this->assertFalse($query->where[0]->not);
        $this->assertInstanceOf(ValueCriterion::class, $query->where[0]->criteria[0]);
        $this->assertAttributes(['column' => 'pre_table.column1', 'rule' => '=', 'value' => 'value1', 'appendRule' => Criterion::APPEND_RULE_AND], $query->where[0]->criteria[0]);
        $this->assertInstanceOf(ValueCriterion::class, $query->where[0]->criteria[1]);
        $this->assertAttributes(['column' => 'pre_table.column2', 'rule' => '=', 'value' => 'value2', 'appendRule' => Criterion::APPEND_RULE_OR], $query->where[0]->criteria[1]);

        // Grouped criteria (by array)
        $query = (new Query('pre_'))->where([
            ['table.column1', 'value1'],
            ['table.column2', '!=', 'value2']
        ]);
        $this->assertCount(1, $query->where);
        $this->assertInstanceOf(CriteriaCriterion::class, $query->where[0]);
        $this->assertCount(2, $query->where[0]->criteria);
        $this->assertFalse($query->where[0]->not);
        $this->assertInstanceOf(ValueCriterion::class, $query->where[0]->criteria[0]);
        $this->assertAttributes(['column' => 'pre_table.column1', 'rule' => '=', 'value' => 'value1', 'appendRule' => Criterion::APPEND_RULE_AND], $query->where[0]->criteria[0]);
        $this->assertInstanceOf(ValueCriterion::class, $query->where[0]->criteria[1]);
        $this->assertAttributes(['column' => 'pre_table.column2', 'rule' => '!=', 'value' => 'value2', 'appendRule' => Criterion::APPEND_RULE_AND], $query->where[0]->criteria[1]);

        // Raw clause
        $query = (new Query('pre_'))->where(new Raw('date + ? = NOW()', [10]));
        $this->assertCount(1, $query->where);
        $this->assertInstanceOf(RawCriterion::class, $query->where[0]);
        $this->assertStatement('date + ? = NOW()', [10], $query->where[0]->raw);

        // Wrong arguments set
        $this->assertException(InvalidArgumentException::class, function () {
            (new Query())->orWhere(new \stdClass());
        });

        // Ordinary with complex values
        $query = (new Query('pre_'))->where(
            function (Query $query) {
                $query->count()->from('bar');
            },
            '>',
            new Raw('NOW()')
        );
        $this->assertCount(1, $query->where);
        $this->assertInstanceOf(ValueCriterion::class, $query->where[0]);
        $this->assertInstanceOf(Query::class, $query->where[0]->column);
        $this->assertEquals('>', $query->where[0]->rule);
        $this->assertInstanceOf(Raw::class, $query->where[0]->value);

        // Wrong rule value
        $this->assertException(InvalidArgumentException::class, function () {
            (new Query())->where(new Raw(''), new Raw(''), new Raw(''));
        });
    }
}
