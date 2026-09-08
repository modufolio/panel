<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Resource;

use Modufolio\Panel\Blueprint\Separator;
use Modufolio\Panel\Form\Field;
use Modufolio\Panel\Resource\DrawerTab;
use Modufolio\Panel\Table\Column;
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
        $tab = DrawerTab::record('details')->toArray(['title' => 'Kick-off', 'contact_id' => 'abc']);

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

        [$tab] = DrawerTab::collect([DrawerTab::record('details')], $record, $form);

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

        [$tab] = DrawerTab::collect([DrawerTab::record('details')], ['title' => 't'], $form);

        self::assertSame(['title' => 'Title'], $tab['fields'], 'Leading, trailing and orphaned separators are dropped.');
    }

    public function testAnExplicitFieldListMayCarrySeparators(): void
    {
        $tab = DrawerTab::record('details')->fields(['title', Separator::Line, 'year' => 'Released'])->toArray([]);

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
            [DrawerTab::group('communication')->sections(DrawerTab::relation('meetings'))],
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
        $tab = DrawerTab::record('details')
            ->fields(['when' => 'When', 'contact' => 'Contact'])
            ->toArray([]);

        self::assertSame(['when' => 'When', 'contact' => 'Contact'], $tab['fields']);
    }

    /** A bare list means "these keys, humanised by the client". */
    /** A Field in the list carries the drawer's own label and width, independent of the form's. */
    public function testAFieldEntryCarriesItsLabelAndWidth(): void
    {
        $tab = DrawerTab::record('details')->fields([
            'first_name',
            'email' => 'E-mail',
            Field::make('phone')->label('Phone'),
            Field::make('note')->width('full'),
            'organization' => ['width' => 'full', 'label' => 'Company'],
            Field::make('city')->width('1/2'),
        ]);

        self::assertSame([
            'first_name'   => null,
            'email'        => 'E-mail',
            'phone'        => 'Phone',
            'note'         => ['label' => null, 'wide' => true],
            'organization' => ['label' => 'Company', 'wide' => true],
            'city'         => null,
        ], $tab->toArray([])['fields'], 'Only `full` spans the row; a half is the grid\'s own column.');
    }

    public function testAFieldOptionTheDrawerCannotUseIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Drawer field "note": only `label`, `width`, `rows`, `pickable` and `pickLabel` mean something in a drawer, not `help`.');

        DrawerTab::record('details')->fields([Field::make('note')->help('Internal')]);
    }

    /** A field spanning rows: a thumbnail sitting square beside the fields that follow it. */
    public function testAFieldCanClaimRows(): void
    {
        $tab = DrawerTab::record('details')->fields([
            'cover' => ['rows' => 3],
            'title',
            'poster' => ['label' => 'Poster', 'width' => 'full', 'rows' => 2],
        ]);

        self::assertSame([
            'cover'  => ['label' => null, 'wide' => false, 'rows' => 3],
            'title'  => null,
            'poster' => ['label' => 'Poster', 'wide' => true, 'rows' => 2],
        ], $tab->toArray([])['fields']);

        $collected = DrawerTab::collect([$tab], [], [], ['cover' => 'Cover']);

        self::assertSame(['label' => 'Cover', 'wide' => false, 'rows' => 3], $collected[0]['fields']['cover'], 'A spanning entry without a label takes the shared one, like a wide entry does.');
    }

    public function testRowsOutsideTheGridAreRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Drawer field "cover": `rows` spans 2 to 4 grid rows; one row is the default and needs no saying.');

        DrawerTab::record('details')->fields(['cover' => ['rows' => 1]]);
    }

    /**
     * `pickable` carries the form field its picker writes to, and an
     * optional `pickLabel` for the empty state's wording — left unset when
     * not given, since the client already falls back to "Choose image" and
     * declaring the default here too would be one more place to drift.
     */
    public function testAFieldCanBePickable(): void
    {
        $tab = DrawerTab::record('details')->fields([
            'cover' => ['rows' => 3, 'pickable' => 'cover_media_id'],
            'badge' => ['pickable' => 'badge_media_id', 'pickLabel' => 'Upload badge'],
        ]);

        self::assertSame([
            'cover' => ['label' => null, 'wide' => false, 'rows' => 3, 'pickTarget' => 'cover_media_id'],
            'badge' => ['label' => null, 'wide' => false, 'pickTarget' => 'badge_media_id', 'pickLabel' => 'Upload badge'],
        ], $tab->toArray([])['fields']);
    }

    /** The empty state's wording means nothing without a picker to show it on. */
    public function testAPickLabelWithoutPickableIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Drawer field "cover": `pickLabel` names the empty state\'s wording, which only means something alongside `pickable`.');

        DrawerTab::record('details')->fields(['cover' => ['pickLabel' => 'Upload cover']]);
    }

    /** A wide entry with no label of its own is labelled from the shared fields like a bare key. */
    public function testCollectLabelsAWideEntryFromTheSharedFields(): void
    {
        $tabs = [DrawerTab::record('details')->fields([Field::make('note')->width('full'), 'phone'])];

        $collected = DrawerTab::collect($tabs, [], [], ['note' => 'Notes', 'phone' => 'Phone']);

        self::assertSame(['note' => ['label' => 'Notes', 'wide' => true], 'phone' => 'Phone'], $collected[0]['fields']);
    }

    /** One argument, the key: the label is humanised from it and a relation reads from it, until told otherwise. */
    public function testARelationTabMayShowItsRowsAsATable(): void
    {
        $tab = DrawerTab::relation('tasks')
            ->columns([
                Column::make('title'),
                Column::make('completed')->type('boolean')->label('Done'),
                Column::make('due_date')->type('date'),
            ])
            ->toArray(['tasks' => [['id' => 1, 'title' => 'Charge batteries', 'completed' => false, 'due_date' => '2026-09-10']]]);

        self::assertSame(['title', 'completed', 'due_date'], array_column($tab['columns'], 'key'));
        self::assertSame(['Title', 'Done', 'Due date'], array_column($tab['columns'], 'label'));
        self::assertSame('boolean', $tab['columns'][1]['type']);
        self::assertFalse($tab['columns'][0]['sortable'], 'Nothing to sort against inside a drawer.');
        self::assertSame(1, $tab['badge']);

        self::assertNull(DrawerTab::relation('tasks')->toArray([])['columns'], 'Absent until declared: the list stays the default.');
    }

    public function testARelationTableRefusesWhatADrawerCannotHonour(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('links to the record');

        DrawerTab::relation('tasks')->columns([Column::make('title')->linksToRecord()]);
    }

    public function testATabIsNamedByItsKeyUntilToldOtherwise(): void
    {
        self::assertSame('Connected contacts', DrawerTab::relation('connected_contacts')->toArray([])['label']);
        self::assertSame('Who', DrawerTab::relation('connected_contacts')->label('Who')->toArray([])['label']);
        self::assertSame('connected_contacts', DrawerTab::relation('connected_contacts')->toArray([])['source']);
        self::assertSame('tag_list', DrawerTab::relation('tags')->source('tag_list')->toArray([])['source']);
        self::assertSame('Details', DrawerTab::record('details')->toArray([])['label']);
        self::assertSame('Communication', DrawerTab::group('communication')->toArray([])['label']);
    }

    /** A custom tab's badge counts what source() names; without one it counts nothing. */
    public function testACustomTabCountsItsSource(): void
    {
        $record = ['documents' => [['id' => 1], ['id' => 2]]];

        self::assertSame(2, DrawerTab::custom('files')->source('documents')->toArray($record)['badge']);
        self::assertNull(DrawerTab::custom('files')->toArray($record)['badge']);
    }

    public function testAListOfKeysNormalisesToNullLabels(): void
    {
        $tab = DrawerTab::record('details')->fields(['when', 'contact'])->toArray([]);

        self::assertSame(['when' => null, 'contact' => null], $tab['fields']);
    }

    public function testARelationTabReadsItsRowsFromTheRecord(): void
    {
        $tab = DrawerTab::relation('events')
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
        $tab = DrawerTab::relation('events')->toArray([]);

        self::assertNull($tab['badge']);
        self::assertSame('events', $tab['source']);
    }

    public function testAddableAndDeletableAreOffByDefault(): void
    {
        $plain = DrawerTab::relation('events')->toArray([]);

        self::assertFalse($plain['addable']);
        self::assertFalse($plain['deletable']);
    }

    public function testAddableCarriesItsOwnLabel(): void
    {
        $tab = DrawerTab::relation('events')->addable('+ Add Event')->toArray([]);

        self::assertTrue($tab['addable']);
        self::assertSame('+ Add Event', $tab['addLabel']);
    }

    public function testCollectSerialisesEveryTabAgainstOneRecord(): void
    {
        $tabs = DrawerTab::collect(
            [
                DrawerTab::record('details')->fields(['title' => 'Title']),
                DrawerTab::relation('events')->primary('title'),
            ],
            ['title' => 'Arrival', 'events' => [['title' => 'Wedding']]],
        );

        self::assertCount(2, $tabs);
        self::assertSame('details', $tabs[0]['type']);
        self::assertSame(1, $tabs[1]['badge']);
    }

    /**
     * The key is the client's slot name and what a section reference resolves
     * against, so a repeat draws one tab holding the first declaration and
     * silently drops the second's body.
     */
    public function testTwoTabsSharingAKeyAreRefused(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Two drawer tabs share the key "details"');

        DrawerTab::collect([DrawerTab::record('details'), DrawerTab::group('details')], ['title' => 't']);
    }
}
