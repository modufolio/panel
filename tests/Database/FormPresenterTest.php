<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Database;

use Modufolio\Panel\Field\ComputedType;
use Modufolio\Panel\Field\TextType;
use Modufolio\Panel\Form\FormPresenter;
use Modufolio\Panel\Form\FormResolver;
use Modufolio\Panel\Resource\PanelResource;
use Modufolio\Panel\Resource\Permissions;
use Modufolio\Panel\Table\RelationOptions;
use Modufolio\Panel\Tests\Case\DoctrineTestCase;
use Modufolio\Panel\Tests\Fixture\Entity\Movie;
use Modufolio\Panel\Tests\Fixture\Entity\Studio;
use Modufolio\Panel\Tests\Fixture\Entity\Tag;
use Modufolio\Panel\Tests\Fixture\MovieResource;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Modufolio\Panel\Form\Form;

/**
 * What a form page is sent.
 *
 * The declaration is pure data; this is where it meets the database and the
 * routes. So what is pinned is the translation: a relation becomes either an
 * option list or a search endpoint (and its entity class never travels), a
 * denied field is gone or read-only, a frozen one is disabled, and a computed
 * field is filled from the accessor it names.
 *
 * Routes are built from `MovieResource::class` even when the resource under
 * test is an anonymous subclass: the route loader writes the class name into
 * a config file, and an anonymous class has no name that survives that.
 */
final class FormPresenterTest extends DoctrineTestCase
{
    private function presenter(?UrlGeneratorInterface $urls = null): FormPresenter
    {
        return new FormPresenter(
            new FormResolver(self::em()),
            self::em(),
            $urls ?? $this->urlGenerator(MovieResource::class),
        );
    }

    // ── Fixtures ─────────────────────────────────────────────────────────────

    /** One readable field, one nobody may read, one nobody may write. */
    private function accessResource(): PanelResource
    {
        return new class extends MovieResource {
            public function form(): Form
            {
                return Form::make()->fields([
                    'title',
                    'secret' => ['type' => TextType::class],
                    'locked' => ['type' => TextType::class],
                ]);
            }

            public function permissions(): Permissions
            {
                return new class extends Permissions {
                    public function readable(string $field, ?object $user, ?object $record = null): bool
                    {
                        return $field !== 'secret';
                    }

                    public function writable(string $field, ?object $user, ?object $record = null): bool
                    {
                        return $field !== 'locked';
                    }
                };
            }
        };
    }

    /** A form with a computed field reading the given accessor. */
    private function computedResource(string $accessor): PanelResource
    {
        return new class ($accessor) extends MovieResource {
            public function __construct(private readonly string $accessor)
            {
            }

            public function form(): Form
            {
                return Form::make()->fields([
                    'title',
                    'heading' => ['type' => ComputedType::class, 'accessor' => $this->accessor],
                ]);
            }
        };
    }

    /** @return list<Tag> persisted in one flush, named so label order differs from insertion order */
    private function persistTags(int $count): array
    {
        $tags = [];

        for ($i = $count; $i >= 1; --$i) {
            $tags[] = (new Tag())->setName(sprintf('Tag %03d', $i));
        }

        $this->persist(...$tags);

        return $tags;
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * @param list<array<string, mixed>> $fields
     *
     * @return array<string, array<string, mixed>>
     */
    private function byKey(array $fields): array
    {
        $byKey = [];

        foreach ($fields as $field) {
            $key = $field['key'] ?? null;
            self::assertIsString($key, 'Every field carries its key.');

            $byKey[$key] = $field;
        }

        return $byKey;
    }

    /**
     * @param array<string, mixed> $field
     *
     * @return array<string, mixed>
     */
    private function section(array $field, string $name): array
    {
        $section = $field[$name] ?? [];
        self::assertIsArray($section);

        return $section;
    }

    /**
     * @param array<string, mixed> $field
     *
     * @return array<string, array<string, mixed>>
     */
    private function subFields(array $field): array
    {
        $subFields = [];

        foreach ($this->section($field, 'fields') as $subField) {
            self::assertIsArray($subField);
            $subFields[] = $subField;
        }

        return $this->byKey($subFields);
    }

    /**
     * @param array<string, mixed> $props
     *
     * @return array<string, mixed>
     */
    private function resourceBlock(array $props): array
    {
        $resource = $props['resource'] ?? null;
        self::assertIsArray($resource);

        return $resource;
    }

    /** @param array<mixed> $value */
    private static function containsKey(array $value, string $needle): bool
    {
        foreach ($value as $key => $item) {
            if ($key === $needle) {
                return true;
            }

            if (is_array($item) && self::containsKey($item, $needle)) {
                return true;
            }
        }

        return false;
    }

    // ── props() ──────────────────────────────────────────────────────────────

    public function testPropsCarryTheResourcesSelfDescriptionAndItsFields(): void
    {
        $props = $this->presenter()->props(new MovieResource());

        self::assertSame(['resource', 'fields'], array_keys($props));

        $resource = $this->resourceBlock($props);
        self::assertSame('movies', $resource['key']);
        self::assertSame('/panel/movies', $resource['baseUrl']);
        self::assertSame('movie', $resource['drawerType']);
        self::assertSame('Movie', $resource['label']);
        self::assertTrue($resource['canDelete'], 'The full route set has a destroy route.');

        $fields = $props['fields'];
        self::assertIsArray($fields);
        self::assertSame(
            ['title', 'synopsis', 'year', 'runtime', 'rating', 'released', 'released_on', 'studio_id', 'tags', 'cast'],
            array_column($fields, 'key'),
        );
    }

    /**
     * canDelete is derived from the routes, not declared: a resource routed
     * for reading only has no destroy route to offer.
     */
    public function testCanDeleteIsFalseWhenNoDestroyRouteExists(): void
    {
        $urls = $this->urlGeneratorFromConfig(
            'function (PanelResourceConfigurator $panel): void { '
            . '$panel->resource(\\' . MovieResource::class . '::class)->only([\'index\', \'show\']); }',
        );

        // A form without relations: the narrow route set has no relation
        // options route either, and a lookup could not be built against it.
        $resource = new class extends MovieResource {
            public function form(): Form
            {
                return Form::make()->fields(['title']);
            }
        };

        $props = $this->presenter($urls)->props($resource);

        self::assertFalse($this->resourceBlock($props)['canDelete']);
        self::assertSame('movies', $this->resourceBlock($props)['key']);
    }

    /** The form posts to the base URL, so a prefixed resource must be sent its prefix. */
    public function testThePrefixedResourceSendsItsPrefixedBaseUrl(): void
    {
        $urls = $this->urlGeneratorFromConfig(
            'function (PanelResourceConfigurator $panel): void { '
            . '$panel->resource(\\' . MovieResource::class . '::class)->prefix(\'/admin\'); }',
        );

        $props = $this->presenter($urls)->props(new MovieResource());

        self::assertSame('/admin/movies', $this->resourceBlock($props)['baseUrl']);
    }

    // ── fields(): relations ──────────────────────────────────────────────────

    /**
     * A to-one is always a lookup: no rows are sent, the control asks the
     * generated endpoint as the user types. Studio has nothing but a name, so
     * the "Create …" row is offered; the join column is constrained NotNull,
     * so there is no way to clear it.
     */
    public function testABelongsToBecomesALookupAgainstTheGeneratedSearchRoute(): void
    {
        $this->persist((new Studio())->setName('Warner Bros.'));

        $studio = $this->byKey($this->presenter()->fields(new MovieResource()))['studio_id'];
        $props  = $this->section($studio, 'props');

        self::assertSame('belongs-to', $studio['type']);
        self::assertSame([], $studio['options'], 'The rows are not sent.');
        self::assertSame('/panel/movies/relations/studio_id', $props['searchUrl']);
        self::assertSame('value', $props['valueKey']);
        self::assertSame('label', $props['labelKey']);
        self::assertFalse($props['clearable'], 'A required relation cannot be cleared.');
        self::assertTrue($props['allowCreate'], 'A studio needs only a name.');
        self::assertArrayNotHasKey('relation', $studio);
    }

    /**
     * The RelationOptions names an entity class, which is nobody's business
     * on the client. Not at the top level, not inside a repeater.
     */
    public function testTheRelationDeclarationNeverTravels(): void
    {
        $presenter = $this->presenter();
        $resource  = new MovieResource();

        foreach ($presenter->fields($resource) as $field) {
            self::assertFalse(self::containsKey($field, 'relation'), sprintf('Field "%s" leaks its relation.', (string) $field['key']));
        }

        foreach ($presenter->resolvedFields($resource) as $field) {
            self::assertFalse(self::containsKey($field, 'relation'), sprintf('Resolved field "%s" leaks its relation.', (string) $field['key']));
        }
    }

    /** A sub-field's endpoint is addressed by its dotted path, never by its bare key. */
    public function testARepeaterSubFieldLookupIsAddressedByItsDottedPath(): void
    {
        $cast    = $this->byKey($this->presenter()->fields(new MovieResource()))['cast'];
        $actorId = $this->subFields($cast)['actor_id'];
        $props   = $this->section($actorId, 'props');

        self::assertSame('repeater', $cast['type']);
        self::assertSame('belongs-to', $actorId['type']);
        self::assertSame([], $actorId['options']);
        self::assertSame('/panel/movies/relations/cast.actor_id', $props['searchUrl']);
        self::assertTrue($props['clearable'], 'The actor column is nullable and unconstrained.');
        self::assertTrue($props['allowCreate'], 'An actor needs only a name.');
        self::assertArrayNotHasKey('relation', $actorId);

        $character = $this->subFields($cast)['character'];
        self::assertSame('text', $character['type']);
        self::assertArrayNotHasKey('options', $character, 'A scalar sub-field is left alone.');
    }

    /**
     * A short many-to-many ships its whole list, ordered by label, and stays
     * the plain multiselect — no endpoint, no search.
     */
    public function testAShortManyToManyIsResolvedToItsOptionsOrderedByLabel(): void
    {
        $tags = $this->persistTags(3);

        $field = $this->byKey($this->presenter()->fields(new MovieResource()))['tags'];

        self::assertSame('multiselect', $field['type']);
        self::assertSame(
            [
                ['value' => $tags[2]->getUuid()->toString(), 'label' => 'Tag 001'],
                ['value' => $tags[1]->getUuid()->toString(), 'label' => 'Tag 002'],
                ['value' => $tags[0]->getUuid()->toString(), 'label' => 'Tag 003'],
            ],
            $field['options'],
        );
        self::assertArrayNotHasKey('searchUrl', $this->section($field, 'props'));
        self::assertArrayNotHasKey('relation', $field);
    }

    /**
     * Past the threshold, the same declaration flips to a searchable
     * control: options empty, an endpoint to ask, and the multiselect type
     * kept so the client renders the same shape.
     */
    public function testAManyToManyAboveTheThresholdBecomesSearchable(): void
    {
        $this->persistTags(RelationOptions::AUTO_SEARCH_THRESHOLD + 1);

        $field = $this->byKey($this->presenter()->fields(new MovieResource()))['tags'];
        $props = $this->section($field, 'props');

        self::assertSame('multiselect', $field['type']);
        self::assertSame([], $field['options'], 'A hundred rows are not sent.');
        self::assertSame('/panel/movies/relations/tags', $props['searchUrl']);
        self::assertSame('value', $props['valueKey']);
        self::assertSame('label', $props['labelKey']);
        self::assertTrue($props['clearable'], 'Tags are optional.');
        self::assertFalse($props['allowCreate'], 'Only a lookup offers creation.');
        self::assertArrayNotHasKey('relation', $field);
    }

    // ── fields(): access ─────────────────────────────────────────────────────

    /**
     * Hidden is not forbidden, on both sides: a read-denied field is never
     * serialised, a write-denied one arrives read-only.
     */
    public function testAccessRemovesUnreadableFieldsAndMarksUnwritableOnesReadonly(): void
    {
        $fields = $this->byKey($this->presenter()->fields($this->accessResource()));

        self::assertSame(['title', 'locked'], array_keys($fields));
        self::assertTrue($this->section($fields['locked'], 'props')['disabled']);
        self::assertArrayNotHasKey('disabled', $this->section($fields['title'], 'props'));
    }

    /** resolvedFields() is the declaration before access: the drawer's addable tab builds from it. */
    public function testResolvedFieldsResolveOptionsWithoutApplyingAccess(): void
    {
        $fields = $this->byKey($this->presenter()->resolvedFields($this->accessResource()));

        self::assertSame(['title', 'secret', 'locked'], array_keys($fields));
        self::assertArrayNotHasKey('disabled', $this->section($fields['locked'], 'props'));

        $studio = $this->byKey($this->presenter()->resolvedFields(new MovieResource()))['studio_id'];
        self::assertSame('/panel/movies/relations/studio_id', $this->section($studio, 'props')['searchUrl']);
    }

    /** A field the permissions freeze for this record renders disabled, and the rule sees the record and the viewer. */
    public function testAFieldNotWritableForTheRecordAndViewerIsReadOnly(): void
    {
        $permissions = new class extends Permissions {
            public ?object $seenRecord = null;
            public ?object $seenUser = null;

            public function writable(string $field, ?object $user, ?object $record = null): bool
            {
                $this->seenRecord = $record;
                $this->seenUser   = $user;

                return $field !== 'title';
            }
        };
        $resource = new class ($permissions) extends MovieResource {
            public function __construct(private readonly Permissions $permissions)
            {
            }

            public function permissions(): Permissions
            {
                return $this->permissions;
            }
        };

        $movie  = (new Movie())->setTitle('Heat');
        $viewer = new \stdClass();

        $fields = $this->byKey($this->presenter()->fields($resource, $movie, $viewer));

        self::assertTrue($this->section($fields['title'], 'props')['disabled']);
        self::assertArrayNotHasKey('disabled', $this->section($fields['year'], 'props'));
        self::assertSame($movie, $permissions->seenRecord);
        self::assertSame($viewer, $permissions->seenUser);
    }

    // ── record() ─────────────────────────────────────────────────────────────

    public function testRecordFillsAComputedFieldFromItsAccessor(): void
    {
        $movie = (new Movie())->setTitle('Heat');

        $record = $this->presenter()->record($this->computedResource('getTitle'), $movie, ['title' => 'Heat', 'year' => 1995]);

        self::assertSame(['title' => 'Heat', 'year' => 1995, 'heading' => 'Heat'], $record);
    }

    /** A named accessor the record does not implement is a declaration bug, and says so. */
    public function testRecordRefusesAnAccessorTheEntityLacks(): void
    {
        $resource = $this->computedResource('getNope');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('declares accessor "getNope" for field "heading", but ' . Movie::class . ' has no such method.');

        $this->presenter()->record($resource, new Movie(), []);
    }

    public function testRecordLeavesAFormWithoutComputedFieldsAlone(): void
    {
        $record = ['title' => 'Heat'];

        self::assertSame($record, $this->presenter()->record(new MovieResource(), new Movie(), $record));
    }

    // ── label() ──────────────────────────────────────────────────────────────

    public function testTheLabelIsTheCapitalisedSingular(): void
    {
        self::assertSame('Movie', FormPresenter::label(new MovieResource()));
    }
}
