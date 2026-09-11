<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Table;

use Modufolio\Panel\Table\ColorRule;
use Modufolio\Panel\Table\Column;
use PHPUnit\Framework\TestCase;

final class ColorRuleTest extends TestCase
{
    public function testARuleIsDeclarativeEnoughToCrossTheWire(): void
    {
        $rule = ColorRule::below(0, 'danger')->icon('warn');

        $this->assertSame(
            ['operator' => 'lt', 'value' => 0, 'color' => 'danger', 'icon' => 'warn'],
            $rule->toArray(),
        );
        $this->assertSame('{"operator":"lt","value":0,"color":"danger","icon":"warn"}', json_encode($rule->toArray()));
    }

    public function testTheNamedConstructorsMapToTheSharedOperatorVocabulary(): void
    {
        $this->assertSame('lt', ColorRule::below(1, 'danger')->toArray()['operator']);
        $this->assertSame('lte', ColorRule::atMost(1, 'danger')->toArray()['operator']);
        $this->assertSame('gt', ColorRule::above(1, 'success')->toArray()['operator']);
        $this->assertSame('gte', ColorRule::atLeast(1, 'success')->toArray()['operator']);
        $this->assertSame('equals', ColorRule::equals('draft', 'gray')->toArray()['operator']);
        $this->assertSame([1, 5], ColorRule::between(1, 5, 'warning')->toArray()['value']);
    }

    public function testAnEmptyRuleCarriesNoValueAtAll(): void
    {
        // Not `value: null` — the key is absent, so the client's "is there a
        // bound?" question has one answer rather than two.
        $this->assertSame(['operator' => 'empty', 'color' => 'gray'], ColorRule::empty('gray')->toArray());
    }

    public function testAnUnknownOperatorIsRefusedWhereItIsWritten(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown colour rule operator "approximately"');

        ColorRule::when('approximately', 3, 'danger');
    }

    public function testBetweenInsistsOnTwoBounds(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ColorRule::when('between', 5, 'warning');
    }

    public function testAColumnCarriesItsRulesInOrder(): void
    {
        $column = Column::make('balance')
            ->colorWhen(ColorRule::below(0, 'danger'))
            ->colorWhen(ColorRule::atLeast(1000, 'success'));

        $this->assertSame(
            [
                ['operator' => 'lt', 'value' => 0, 'color' => 'danger'],
                ['operator' => 'gte', 'value' => 1000, 'color' => 'success'],
            ],
            $column->toArray(sortable: true)['colorRules'],
        );
    }

    public function testAColumnWithoutRulesSendsNoKey(): void
    {
        $this->assertArrayNotHasKey('colorRules', Column::make('balance')->toArray(sortable: true));
    }
}
