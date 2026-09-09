<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Resource;

use Modufolio\Panel\Resource\Drawer;
use Modufolio\Panel\Resource\DrawerTab;
use PHPUnit\Framework\TestCase;

final class DrawerTest extends TestCase
{
    public function testADrawerHoldsItsTabsInOrder(): void
    {
        $details   = DrawerTab::record('details');
        $attendees = DrawerTab::relation('attendees');

        $drawer = Drawer::make()->tabs([$details, $attendees]);

        self::assertSame([$details, $attendees], $drawer->declaredTabs());
        self::assertSame([], Drawer::make()->declaredTabs());
    }

    /**
     * A listed key with no label of its own takes the resource's; one with
     * a label keeps it; one nobody labelled is humanised here, so the grid
     * never has to.
     */
    public function testCollectLabelsListedKeysFromTheSharedFields(): void
    {
        $tabs = [DrawerTab::record('details')->fields(['title', 'starts_at', 'contact' => 'Who', 'ticket_count'])];

        $collected = DrawerTab::collect($tabs, ['title' => 'Gala'], [], ['starts_at' => 'When', 'contact' => 'Contact']);

        self::assertSame(
            ['title' => 'Title', 'starts_at' => 'When', 'contact' => 'Who', 'ticket_count' => 'Ticket count'],
            $collected[0]['fields'],
        );
    }
}
