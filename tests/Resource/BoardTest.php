<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Resource;

use Modufolio\Panel\Resource\Board;
use PHPUnit\Framework\TestCase;

/** The declaration the resource writes, and the view the listing serves from it. */
final class BoardTest extends TestCase
{
    public function testABoardBecomesTheViewTheListingServes(): void
    {
        $view = Board::make('status', 'Kanban')
            ->columns(['todo' => 'To do', 'done' => 'Done'], ['done' => 'green'])
            ->position('position')
            ->card('title', 'due_date')
            ->limit(20)
            ->quickMove()
            ->icon('kanban')
            ->view();

        self::assertTrue($view->isBoard());
        self::assertSame('board', $view->key());
        self::assertSame('status', $view->groupBy());
        self::assertSame('position', $view->positionField());
        self::assertSame(['todo', 'done'], $view->columnValues());
        self::assertSame('green', $view->columnDefinitions()[1]['color']);
        self::assertSame(20, $view->columnLimit());
        self::assertTrue($view->offersQuickMove());
        self::assertSame('Kanban', $view->toArray()['label']);
        self::assertSame(['title', 'due_date'], [$view->toArray()['cardTitle'], ...$view->toArray()['cardFields']]);
    }

    public function testEveryCallReturnsANewBoard(): void
    {
        $base  = Board::make('status');
        $wider = $base->limit(10);

        self::assertNotSame($base, $wider);
        self::assertSame(50, $base->view()->columnLimit());
    }
}
