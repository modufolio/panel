<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Resource;

use Modufolio\Panel\Blueprint\Separator;
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

    /**
     * Without a list of its own, a details grid reads the way the form does:
     * the form's order, separators and widths, relations by their presented
     * key — and nothing the form does not name.
     */
    public function testADetailsTabWithoutFieldsFollowsTheForm(): void
    {
        $record = [
            'id'              => 7,
            'created_at'      => '2026-01-01',
            'note'            => 'n',
            // Both the raw id and the presented relation, as presenters do:
            // the grid must show the relation once and the id never.
            'organization_id' => 'uuid-of-cave7',
            'organization'    => ['name' => 'Cave7'],
            'email'        => 'a@b.c',
            'first_name'   => 'Leila',
            'tags'         => ['x', 'y'],
        ];
        $form = [
            ['key' => 'first_name', 'type' => 'text', 'label' => 'First name'],
            ['key' => 'separator_1', 'type' => 'separator', 'props' => ['separator' => 'line']],
            ['key' => 'email', 'type' => 'text', 'label' => 'Email'],
            ['key' => 'organization_id', 'type' => 'belongs-to', 'label' => 'Organization'],
            ['key' => 'separator_2', 'type' => 'separator', 'props' => ['separator' => 'space']],
            ['key' => 'missing', 'type' => 'text', 'label' => 'Not on the record'],
            ['key' => 'tags', 'type' => 'multiselect', 'label' => 'Tags'],
            ['key' => 'note', 'type' => 'textarea', 'label' => 'Note', 'width' => 'full'],
        ];

        [$tab] = DrawerTab::collect([DrawerTab::details()], $record, $form);

        self::assertSame([
            'first_name'   => 'First name',
            'separator_1'  => ['separator' => 'line'],
            'email'        => 'Email',
            'organization' => 'Organization',
            'separator_2'  => ['separator' => 'space'],
            'note'         => ['label' => 'Note', 'wide' => true],
        ], $tab['fields'], 'created_at is on the record but not on the form, so it is not shown.');
    }

    public function testADetailsTabDrawsNoSeparatorAroundNothing(): void
    {
        $form = [
            ['key' => 'separator_1', 'type' => 'separator', 'props' => ['separator' => 'line']],
            ['key' => 'title', 'type' => 'text', 'label' => 'Title'],
            ['key' => 'separator_2', 'type' => 'separator', 'props' => ['separator' => 'line']],
            ['key' => 'separator_3', 'type' => 'separator', 'props' => ['separator' => 'space']],
            ['key' => 'gone', 'type' => 'text', 'label' => 'Gone'],
        ];

        [$tab] = DrawerTab::collect([DrawerTab::details()], ['title' => 't'], $form);

        self::assertSame(['title' => 'Title'], $tab['fields'], 'Leading, trailing and orphaned separators are dropped.');
    }

    public function testAnExplicitFieldListMayCarrySeparators(): void
    {
        $tab = DrawerTab::details()->fields(['title', Separator::Line, 'year' => 'Released'])->toArray([]);

        self::assertSame([
            'title'       => null,
            'separator_1' => ['separator' => 'line'],
            'year'        => 'Released',
        ], $tab['fields']);
    }

    /** A group is sections under a heading: it declares no grid and never derives one from the form. */
    public function testAGroupTabHasNoGridAndDerivesNone(): void
    {
        $form = [['key' => 'title', 'type' => 'text', 'label' => 'Title']];

        [$tab] = DrawerTab::collect(
            [DrawerTab::group('Communication', 'communication')->sections(DrawerTab::relation('meetings', 'Meetings'))],
            ['title' => 't', 'meetings' => [['id' => 1]]],
            $form,
        );

        self::assertSame('details', $tab['type']);
        self::assertFalse($tab['grid']);
        self::assertSame([], $tab['fields']);
        self::assertSame('meetings', $tab['sections'][0]['key']);
        self::assertSame(1, $tab['sections'][0]['badge']);
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
