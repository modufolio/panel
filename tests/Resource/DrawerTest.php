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
        $details   = DrawerTab::details();
        $attendees = DrawerTab::relation('attendees', 'Attendees');

        $drawer = Drawer::make()->tabs([$details, $attendees]);

        self::assertSame([$details, $attendees], $drawer->declaredTabs());
        self::assertSame([], Drawer::make()->declaredTabs());
    }

    /** A listed key with no label of its own takes the resource's; one with a label keeps it. */
    public function testCollectLabelsListedKeysFromTheSharedFields(): void
    {
        $tabs = [DrawerTab::details()->fields(['title', 'starts_at', 'contact' => 'Who'])];

        $collected = DrawerTab::collect($tabs, ['title' => 'Gala'], [], ['starts_at' => 'When', 'contact' => 'Contact']);

        self::assertSame(
            ['title' => null, 'starts_at' => 'When', 'contact' => 'Who'],
            $collected[0]['fields'],
        );
    }
}
