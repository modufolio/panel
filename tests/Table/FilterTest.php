<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Table;

use Modufolio\Panel\Table\Filter;
use PHPUnit\Framework\TestCase;

/**
 * Filters, and the `key` vs `field` distinction.
 *
 * The key is what travels in the query string and what the client keys its
 * form on; the field is the entity property the server orders and filters by.
 * Collapsing the two would tie a public URL to a property rename.
 */
final class FilterTest extends TestCase
{
    public function testTheFieldDefaultsToTheKey(): void
    {
        $filter = Filter::select('status')->toArray();

        self::assertSame('status', $filter['key']);
        self::assertSame('select', $filter['type']);
    }

    public function testAFieldCanDifferFromTheKeyItTravelsUnder(): void
    {
        $filter = Filter::ternary('is_company', 'isCompany')->toArray();

        self::assertSame('is_company', $filter['key']);
    }

    public function testTheLabelIsHumanisedFromTheKeyWhenNotGiven(): void
    {
        self::assertSame('Status', Filter::select('status')->toArray()['label']);
        self::assertSame('Company', Filter::ternary('is_company')->label('Company')->toArray()['label']);
    }

    public function testATernaryCarriesItsOwnWording(): void
    {
        $filter = Filter::ternary('is_company')
            ->trueOption('Companies')
            ->falseOption('People')
            ->toArray();

        self::assertSame('Companies', $filter['trueLabel']);
        self::assertSame('People', $filter['falseLabel']);
    }

    /** The soft-delete scope names itself; the predicate is the query's. */
    public function testTrashedIsPreConfigured(): void
    {
        $filter = Filter::trashed()->toArray();

        self::assertSame('trashed', $filter['key']);
        self::assertSame('trashed', $filter['type']);
    }

    public function testEachConstructorProducesItsOwnType(): void
    {
        self::assertSame('select', Filter::select('a')->toArray()['type']);
        self::assertSame('multiSelect', Filter::multiSelect('b')->toArray()['type']);
        self::assertSame('ternary', Filter::ternary('c')->toArray()['type']);
        self::assertSame('dateRange', Filter::dateRange('d')->toArray()['type']);
    }

    public function testOptionsAreCarriedAsPlainData(): void
    {
        $options = [['label' => 'Active', 'value' => 'active']];

        self::assertSame($options, Filter::select('status')->options($options)->toArray()['options']);
    }

    /**
     * A default is what applies when the request says nothing. It travels,
     * so the client can tell a control showing it from a filter the viewer
     * applied; a filter without one sends no key at all.
     */
    public function testADefaultTravelsWhenDeclared(): void
    {
        self::assertArrayNotHasKey('default', Filter::select('genre')->toArray());
        self::assertSame('open', Filter::select('status')->default('open')->toArray()['default']);
        self::assertSame('with', Filter::trashed()->withDefault('with')->toArray()['default']);
        self::assertNull(Filter::trashed()->defaultValue());
    }
}
