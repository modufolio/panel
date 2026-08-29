<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Resource;

use Modufolio\Panel\Resource\DrawerTab;
use PHPUnit\Framework\TestCase;

/**
 * Drawer tabs describe a record's sections to the client.
 *
 * The details tab in particular decides what a drawer *shows*: declaring no
 * fields means "print everything the presenter returned", which is how a
 * drawer once listed `contact_id` and `has_passed` as if they were things a
 * reader wanted. Both behaviours are pinned below, because both are correct in
 * their place.
 */
final class DrawerTabTest extends TestCase
{
    public function testADetailsTabShowsEverythingWhenItNamesNoFields(): void
    {
        $tab = DrawerTab::details()->toArray(['title' => 'Kick-off', 'contact_id' => 'abc']);

        self::assertSame('details', $tab['type']);
        self::assertArrayNotHasKey('fields', $tab, 'No `fields` key means the grid prints the record.');
    }

    public function testNamedFieldsAreCarriedInOrder(): void
    {
        $tab = DrawerTab::details()
            ->fields(['when' => 'When', 'contact' => 'Contact'])
            ->toArray([]);

        self::assertSame(['when' => 'When', 'contact' => 'Contact'], $tab['fields']);
    }

    /** A bare list means "these keys, humanised by the client". */
    public function testAListOfKeysNormalisesToNullLabels(): void
    {
        $tab = DrawerTab::details()->fields(['when', 'contact'])->toArray([]);

        self::assertSame(['when' => null, 'contact' => null], $tab['fields']);
    }

    public function testARelationTabReadsItsRowsFromTheRecord(): void
    {
        $tab = DrawerTab::relation('events', 'Events')
            ->primary('title')
            ->secondary('when_label')
            ->empty('No events yet.')
            ->toArray(['events' => [['title' => 'Wedding'], ['title' => 'Test']]]);

        self::assertSame('events', $tab['source']);
        self::assertSame('title', $tab['primary']);
        self::assertSame('when_label', $tab['secondary']);
        self::assertSame('No events yet.', $tab['empty']);
        self::assertSame(2, $tab['badge']);
    }

    /**
     * A relation the record does not carry is empty, not missing — and an
     * empty one shows no badge rather than a zero, which is what the bespoke
     * drawers did by hand before this was declared.
     */
    public function testAnEmptyRelationTabShowsNoBadge(): void
    {
        $tab = DrawerTab::relation('events', 'Events')->toArray([]);

        self::assertNull($tab['badge']);
        self::assertSame('events', $tab['source']);
    }

    public function testAddableAndDeletableAreOffByDefault(): void
    {
        $plain = DrawerTab::relation('events', 'Events')->toArray([]);

        self::assertFalse($plain['addable']);
        self::assertFalse($plain['deletable']);
    }

    public function testAddableCarriesItsOwnLabel(): void
    {
        $tab = DrawerTab::relation('events', 'Events')->addable('+ Add Event')->toArray([]);

        self::assertTrue($tab['addable']);
        self::assertSame('+ Add Event', $tab['addLabel']);
    }

    public function testCollectSerialisesEveryTabAgainstOneRecord(): void
    {
        $tabs = DrawerTab::collect(
            [
                DrawerTab::details()->fields(['title' => 'Title']),
                DrawerTab::relation('events', 'Events')->primary('title'),
            ],
            ['title' => 'Arrival', 'events' => [['title' => 'Wedding']]],
        );

        self::assertCount(2, $tabs);
        self::assertSame('details', $tabs[0]['type']);
        self::assertSame(1, $tabs[1]['badge']);
    }
}
