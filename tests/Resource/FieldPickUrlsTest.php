<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Resource;

use Modufolio\Appkit\Security\User\UserInterface;
use Modufolio\Panel\Resource\FieldPickUrls;
use Modufolio\Panel\Resource\Permissions;
use Modufolio\Panel\Tests\Fixture\DerivedMovieResource;
use Modufolio\Panel\Tests\Fixture\Entity\Movie;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;

/**
 * Where a pickable field's picker posts, stamped onto the fields that can
 * take a selection.
 *
 * The frame describes the record; the URL belongs to the record's own
 * resource — a field on a frame stacked over another resource must post to
 * that resource's endpoint, the same reasoning {@see RelationAddUrls} already
 * carries for an addable list.
 */
final class FieldPickUrlsTest extends TestCase
{
    /**
     * @param  list<array<string, mixed>> $tabs
     * @return list<array<string, mixed>>
     */
    private function stamp(array $tabs, UrlGeneratorInterface $urls, ?Permissions $permissions = null): array
    {
        $resource = $permissions === null ? new DerivedMovieResource() : new class ($permissions) extends DerivedMovieResource {
            public function __construct(private readonly Permissions $stubPermissions)
            {
            }

            public function permissions(): Permissions
            {
                return $this->stubPermissions;
            }
        };

        return (new FieldPickUrls($urls))->stamp($tabs, $resource, new Movie(), null);
    }

    private function generator(?string $generated = '/panel/movies/u-1/relations/cover_media_id'): UrlGeneratorInterface
    {
        return new class ($generated) implements UrlGeneratorInterface {
            public function __construct(private readonly ?string $generated) {}

            /** @param array<string, mixed> $parameters */
            public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
            {
                if ($this->generated === null) {
                    throw new RouteNotFoundException($name);
                }

                return $this->generated . '?' . http_build_query(['route' => $name, ...$parameters]);
            }

            public function setContext(RequestContext $context): void {}

            public function getContext(): RequestContext
            {
                return new RequestContext();
            }
        };
    }

    /** @return list<array<string, mixed>> */
    private function tabs(): array
    {
        return [[
            'key'    => 'details',
            'type'   => 'details',
            'fields' => [
                'cover' => ['label' => null, 'wide' => false, 'rows' => 3, 'pickTarget' => 'cover_media_id'],
                'title' => 'Title',
            ],
        ]];
    }

    public function testItStampsAPickableFieldWithTheRecordsOwnEndpoint(): void
    {
        $tabs = $this->stamp($this->tabs(), $this->generator());

        self::assertStringContainsString('route=movies_relation_store', $tabs[0]['fields']['cover']['pickUrl']);
        self::assertStringContainsString('field=cover_media_id', $tabs[0]['fields']['cover']['pickUrl']);
        self::assertSame('cover_media_id', $tabs[0]['fields']['cover']['pickTarget']);
    }

    public function testItLeavesAFieldWithNoPickTargetAlone(): void
    {
        $tabs = $this->stamp($this->tabs(), $this->generator());

        self::assertSame('Title', $tabs[0]['fields']['title']);
    }

    /**
     * A resource that generates no form routes has no endpoint to post to.
     * Withdrawing the offer is the point: a picker that 404s is worse than no
     * picker at all.
     */
    public function testAFieldWithNoRouteCarriesNoPickUrl(): void
    {
        $tabs = $this->stamp($this->tabs(), $this->generator(null));

        self::assertArrayNotHasKey('pickUrl', $tabs[0]['fields']['cover']);
        self::assertArrayNotHasKey('pickTarget', $tabs[0]['fields']['cover']);
    }

    public function testAFieldIsNotStampedWhenTheViewerMayNotEditTheRecord(): void
    {
        $permissions = new class () extends Permissions {
            public function edit(?object $record, ?UserInterface $user): bool
            {
                return false;
            }
        };

        $tabs = $this->stamp($this->tabs(), $this->generator(), $permissions);

        self::assertArrayNotHasKey('pickUrl', $tabs[0]['fields']['cover']);
    }

    public function testAFieldIsNotStampedWhenTheViewerMayNotWriteTheTarget(): void
    {
        $permissions = new class () extends Permissions {
            public function writable(string $field, ?UserInterface $user, ?object $record = null): bool
            {
                return $field !== 'cover_media_id';
            }
        };

        $tabs = $this->stamp($this->tabs(), $this->generator(), $permissions);

        self::assertArrayNotHasKey('pickUrl', $tabs[0]['fields']['cover']);
    }
}
