<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Database;

use Modufolio\Panel\Field\TextType;
use Modufolio\Panel\Form\FormResolver;
use Modufolio\Panel\Resource\PanelResource;
use Modufolio\Panel\Table\RelationOptions;
use Modufolio\Panel\Tests\Case\DoctrineTestCase;
use Modufolio\Panel\Tests\Fixture\Entity\Actor;
use Modufolio\Panel\Tests\Fixture\Entity\Studio;
use Modufolio\Panel\Tests\Fixture\MovieResource;
use Modufolio\Panel\Form\Form;

/**
 * Which form a resource has, and what can be reached through it by name.
 *
 * Three resources, one per way of declaring a form: hand-written fields with
 * their access map, keys the guesser fills in from Doctrine's metadata, and
 * nothing at all. The relation lookups are pinned as an allowlist — a dotted
 * path that does not terminate on a declared relation resolves to nothing,
 * because that is what stops a request from naming an arbitrary entity.
 */
final class FormResolverTest extends DoctrineTestCase
{
    private function resolver(): FormResolver
    {
        return new FormResolver(self::em());
    }

    // ── Fixtures ─────────────────────────────────────────────────────────────

    /** A resource declaring its fields outright — types pinned. */
    private function handWrittenResource(): PanelResource
    {
        return new class extends MovieResource {
            public function form(): Form
            {
                return Form::make()->fields([
                    'title'  => ['type' => TextType::class, 'required' => true],
                    'secret' => ['type' => TextType::class],
                ]);
            }
        };
    }

    /** Keys only, one with an override for the guesser to apply. */
    private function keyedResource(): PanelResource
    {
        return new class extends MovieResource {
            public function form(): Form
            {
                return Form::make()->fields([
                    'title',
                    'year' => ['label' => 'Release year'],
                ]);
            }
        };
    }

    /** No form at all. */
    private function formlessResource(): PanelResource
    {
        return new class extends MovieResource {
            public function form(): ?Form
            {
                return null;
            }
        };
    }

    // ── fieldsFor() ──────────────────────────────────────────────────────────

    public function testAHandWrittenFormIsReturnedAsDeclared(): void
    {
        $fields = $this->resolver()->fieldsFor($this->handWrittenResource());

        self::assertSame(['title', 'secret'], array_column($fields, 'key'));
        self::assertSame(['text', 'text'], array_column($fields, 'type'), 'Declared types are taken as written.');
    }

    public function testAKeyedFormIsGuessedWithItsOverridesApplied(): void
    {
        $fields = $this->resolver()->fieldsFor($this->keyedResource());

        self::assertSame(['title', 'year'], array_column($fields, 'key'));
        self::assertSame('Release year', $fields[1]['label'], 'The override survives the guess.');
    }

    public function testAResourceWithoutAFormGetsNoFields(): void
    {
        self::assertSame([], $this->resolver()->fieldsFor($this->formlessResource()));
    }

    /** One request reads the same form several times; the guess runs once. */
    public function testTheFormIsMemoisedPerResourceClass(): void
    {
        $resolver = $this->resolver();
        $resource = new MovieResource();

        $first  = $resolver->fieldsFor($resource);
        $second = $resolver->fieldsFor($resource);

        self::assertSame($first, $second);
        self::assertSame($first, $resolver->fieldsFor(new MovieResource()), 'Keyed by class, not by instance.');
    }

    // ── field() ──────────────────────────────────────────────────────────────

    public function testFieldFindsATopLevelKeyAndNothingElse(): void
    {
        $resolver = $this->resolver();
        $resource = new MovieResource();

        $field = $resolver->field($resource, 'title');

        self::assertNotNull($field);
        self::assertSame('title', $field['key']);
        self::assertSame('text', $field['type']);

        self::assertNull($resolver->field($resource, 'nope'));
        self::assertNull($resolver->field($resource, 'character'), 'A repeater sub-field is not a top-level key.');
        self::assertNull($resolver->field($resource, 'cast.character'), 'field() does not walk dotted paths.');
    }

    // ── relationFor() ────────────────────────────────────────────────────────

    public function testATopLevelBelongsToResolvesToItsRelation(): void
    {
        $relation = $this->resolver()->relationFor(new MovieResource(), 'studio_id');

        self::assertInstanceOf(RelationOptions::class, $relation);
        self::assertSame(Studio::class, $relation->entityClass);
        self::assertSame('name', $relation->labelField);
        self::assertSame('uuid', $relation->valueField);
    }

    public function testADottedPathWalksIntoTheRepeatersOwnDeclaration(): void
    {
        $relation = $this->resolver()->relationFor(new MovieResource(), 'cast.actor_id');

        self::assertInstanceOf(RelationOptions::class, $relation);
        self::assertSame(Actor::class, $relation->entityClass);
        self::assertSame('name', $relation->labelField);
    }

    /**
     * Only a declared relation is reachable by name. A scalar sub-field, an
     * unknown key and an unknown sub-key all resolve to nothing — there is
     * no fallback that could hand a request an entity class it did not
     * declare.
     */
    public function testAnythingThatIsNotADeclaredRelationResolvesToNull(): void
    {
        $resolver = $this->resolver();
        $resource = new MovieResource();

        self::assertNull($resolver->relationFor($resource, 'cast.character'), 'A scalar sub-field is not a relation.');
        self::assertNull($resolver->relationFor($resource, 'nope'));
        self::assertNull($resolver->relationFor($resource, 'cast.nope'));
        self::assertNull($resolver->relationFor($resource, 'cast'), 'The repeater itself is not a relation.');
        self::assertNull($resolver->relationFor($resource, 'title.studio_id'), 'A scalar has no sub-fields to walk into.');
        self::assertNull($resolver->relationFor($resource, 'actor_id'), 'A sub-key is not addressable without its repeater.');
    }

    // ── subFields() ──────────────────────────────────────────────────────────

    public function testSubFieldsKeepsOnlyTheArrayRows(): void
    {
        $field = [
            'key'    => 'cast',
            'fields' => [
                ['key' => 'actor_id'],
                'not a row',
                42,
                null,
                ['key' => 'character'],
            ],
        ];

        self::assertSame(
            [['key' => 'actor_id'], ['key' => 'character']],
            FormResolver::subFields($field),
        );
    }

    public function testSubFieldsIsEmptyWithoutADeclaration(): void
    {
        self::assertSame([], FormResolver::subFields(['key' => 'title']));
        self::assertSame([], FormResolver::subFields(['key' => 'cast', 'fields' => 'oops']));
        self::assertSame([], FormResolver::subFields(['key' => 'cast', 'fields' => []]));
    }
}
