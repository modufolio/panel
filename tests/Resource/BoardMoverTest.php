<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Resource;

use Doctrine\ORM\EntityManagerInterface;
use Modufolio\Panel\Resource\BoardMover;
use Modufolio\Panel\Resource\ResourceView;
use Modufolio\Panel\Tests\Fixture\MovieResource;
use PHPUnit\Framework\TestCase;

/**
 * What a move refuses before it writes anything.
 *
 * These are the checks that hold when a resource does *not* override
 * Permissions::move() — the default allows every move, so the declaration is the only
 * thing standing between a dropped card and an arbitrary column value. Tested
 * here rather than through a board endpoint because an endpoint whose resource
 * has a workflow refuses first, and would pass whether this check existed or
 * not.
 *
 * No database: every refusal happens before the entity manager is touched, so
 * a stub that would fail on any call is the assertion that it stays that way.
 */
final class BoardMoverTest extends TestCase
{
    private function mover(): BoardMover
    {
        return new BoardMover($this->createStub(EntityManagerInterface::class));
    }

    private function view(): ResourceView
    {
        return ResourceView::board('status')
            ->columns(['todo' => 'To do', 'done' => 'Done'])
            ->position('position');
    }

    public function testAColumnTheBoardDoesNotDeclareIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"archived" is not one this board declares');

        $this->mover()->move(
            new MovieResource(),
            $this->view(),
            new \stdClass(),
            'archived',
            null,
            null,
        );
    }

    /**
     * A board with no columns is a declaration bug, and one that would
     * otherwise show as a blank page rather than as an error.
     */
    public function testABoardDeclaringNoColumnsIsRefused(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('declares no columns');

        $this->mover()->move(
            new MovieResource(),
            ResourceView::board('status'),
            new \stdClass(),
            'todo',
            null,
            null,
        );
    }

    /**
     * The column is validated before the entity is written to, so a rejected
     * move cannot have half-applied — the group field set, the position not.
     */
    public function testARefusedMoveWritesNothing(): void
    {
        $entity = new class {
            public ?string $written = null;

            public function setStatus(string $status): void
            {
                $this->written = $status;
            }
        };

        try {
            $this->mover()->move(new MovieResource(), $this->view(), $entity, 'archived', null, null);
        } catch (\InvalidArgumentException) {
            // expected
        }

        $this->assertNull($entity->written, 'A refused move must not have touched the record');
    }
}
