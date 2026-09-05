<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Database;

use Modufolio\Panel\Blueprint\FormFieldGuesser;
use Modufolio\Panel\Blueprint\Separator;
use Modufolio\Panel\Field\ComputedType;
use Modufolio\Panel\Field\TextareaType;
use Modufolio\Panel\Resource\PanelResource;
use Modufolio\Panel\Table\RelationOptions;
use Modufolio\Panel\Tests\Case\DoctrineTestCase;
use Modufolio\Panel\Tests\Fixture\Entity\Actor;
use Modufolio\Panel\Tests\Fixture\Entity\CastMember;
use Modufolio\Panel\Tests\Fixture\Entity\Credit;
use Modufolio\Panel\Tests\Fixture\Entity\Distributor;
use Modufolio\Panel\Tests\Fixture\Entity\Movie;
use Modufolio\Panel\Tests\Fixture\Entity\Studio;
use Modufolio\Panel\Tests\Fixture\Entity\Tag;
use Modufolio\Panel\Tests\Fixture\MovieResource;
use Modufolio\Panel\Tests\Fixture\StubListQuery;
use Modufolio\Panel\Form\Field;
use Modufolio\Panel\Form\Form;

/**
 * The guesser reads a form off Doctrine's metadata, so its tests need real
 * metadata: the Movie fixture carries one column of every kind it
 * distinguishes and one association of every shape.
 *
 * What is pinned here is the *contract* between a `form()` declaration and
 * the field definitions that reach the client — a change to any mapping rule
 * changes every generated form at once, which is why each rule gets its own
 * test rather than one comparison of the whole array.
 */
final class FormFieldGuesserTest extends DoctrineTestCase
{
    private function guesser(): FormFieldGuesser
    {
        return new FormFieldGuesser(self::em());
    }

    /**
     * A resource that exists only to name its entity and its keys, so a test
     * can hand the guesser exactly the declaration it wants to exercise.
     *
     * @param class-string                                            $entityClass
     * @param array<int|string, string|Separator|array<string, mixed>>|null $keys
     */
    private function resourceFor(string $entityClass, ?array $keys): PanelResource
    {
        return new class ($entityClass, $keys) extends PanelResource {
            /**
             * @param class-string                                        $entityClass
             * @param array<int|string, string|Separator|array<string, mixed>>|null $keys
             */
            public function __construct(
                private readonly string $entityClass,
                private readonly ?array $keys,
            ) {
            }

            public function key(): string
            {
                return 'things';
            }

            public function entityClass(): string
            {
                return $this->entityClass;
            }

            public function listQueryClass(): string
            {
                return StubListQuery::class;
            }

            public function form(): ?Form
            {
                return $this->keys === null ? null : Form::make()->fields($this->keys);
            }

            public function present(array $entities): array
            {
                return [];
            }
        };
    }

    /**
     * Guess the Movie form with the given keys and return the fields by key.
     *
     * @param array<int|string, string|Separator|array<string, mixed>> $keys
     *
     * @return array<string, array<string, mixed>>
     */
    private function guessMovie(array $keys): array
    {
        $fields = $this->guesser()->guess($this->resourceFor(Movie::class, $keys));

        self::assertNotNull($fields);

        return $this->byKey($fields);
    }

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
     * One nested array of a field definition (`rules`, `props`, `fields`),
     * or an empty array when the builder dropped it for being empty.
     *
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
     * The sub-fields of a repeater, by key.
     *
     * @param array<string, mixed> $field
     *
     * @return array<string, array<string, mixed>>
     */
    private function subFields(array $field): array
    {
        $subFields = [];

        foreach ($this->section($field, 'fields') as $subField) {
            self::assertIsArray($subField);
            $key = $subField['key'] ?? null;
            self::assertIsString($key);

            $subFields[$key] = $subField;
        }

        return $subFields;
    }

    // ── Column type → field type ─────────────────────────────────────────────

    /**
     * The component a column renders as follows its Doctrine type. Integer
     * and decimal columns have no component of their own: NumberType and
     * DecimalType both render the text input with `type="number"`, so the
     * distinction lives in props, not in `type`.
     */
    public function testTheFieldTypeFollowsTheColumnType(): void
    {
        $fields = $this->guessMovie(['title', 'synopsis', 'year', 'runtime', 'rating', 'released', 'released_on']);

        self::assertSame('text', $fields['title']['type'], 'string → text');
        self::assertSame('textarea', $fields['synopsis']['type'], 'text → textarea');
        self::assertSame('toggle', $fields['released']['type'], 'boolean → toggle');
        self::assertSame('date', $fields['released_on']['type'], 'date_immutable → date');
        self::assertSame('datetime', $this->guessMovie(['premiere_at'])['premiere_at']['type'], 'datetime_immutable → datetime, a control that can read the time part');

        foreach (['year', 'runtime', 'rating'] as $numeric) {
            self::assertSame('text', $fields[$numeric]['type'], $numeric . ' renders the text input…');
            self::assertSame('number', $this->section($fields[$numeric], 'props')['type'], '…with type="number"');
        }

        self::assertSame(true, $this->section($fields['year'], 'rules')['integer'], 'An integer column carries the integer rule.');
        self::assertArrayNotHasKey('integer', $this->section($fields['rating'], 'rules'), 'A decimal column does not.');
    }

    /**
     * The mapping calls every string column text. A property that knows
     * better says so with #[FormType], and the guesser believes it — while
     * still deriving everything else, the length-derived max included.
     */
    public function testAFormTypeAttributeOnThePropertyNamesTheType(): void
    {
        $fields = $this->guessMovie(['website']);

        // UrlType renders the text input with type="url" and carries the url
        // rule — the type's own defaults, which the attribute path must keep.
        self::assertSame('url', $this->section($fields['website'], 'props')['type']);
        self::assertTrue($this->section($fields['website'], 'rules')['url']);
        self::assertSame(200, $this->section($fields['website'], 'rules')['max'], 'The column length still applies.');
        self::assertArrayNotHasKey('required', $fields['website'], 'A nullable column is still optional.');
    }

    /** The attribute is the property's declaration; a resource's `options` shorthand does not demote it to a select. */
    public function testAFormTypeAttributeWinsOverTheOptionsShorthand(): void
    {
        $fields = $this->guessMovie(['website' => ['options' => ['a' => 'A']]]);

        self::assertSame('text', $fields['website']['type'], 'Not a select.');
        self::assertSame('url', $this->section($fields['website'], 'props')['type']);
    }

    /** A Separator entry in the key list is a break in the guessed form, exactly where it was declared. */
    public function testASeparatorEntryBecomesABreakWhereItWasDeclared(): void
    {
        $fields = $this->guessMovie(['title', Separator::Line, 'year', Separator::Space, 'released']);

        self::assertSame(['title', 'separator_1', 'year', 'separator_2', 'released'], array_keys($fields));
        self::assertSame('separator', $fields['separator_1']['type']);
        self::assertSame('line', $this->section($fields['separator_1'], 'props')['separator']);
        self::assertSame('space', $this->section($fields['separator_2'], 'props')['separator']);
    }

    /** An enumType column is a choice among its cases: a select, labelled by the enum's own getLabel(). */
    public function testAnEnumColumnBecomesASelectOverItsCases(): void
    {
        $fields = $this->guessMovie(['genre']);

        self::assertSame('select', $fields['genre']['type']);
        self::assertSame(
            [['value' => 'drama', 'label' => 'Drama'], ['value' => 'sci_fi', 'label' => 'Science fiction'], ['value' => 'comedy', 'label' => 'Comedy']],
            $fields['genre']['options'],
        );
        self::assertArrayNotHasKey('required', $fields['genre'], 'A nullable enum column is optional.');
    }

    /** The resource may still name its own choices; the cases are only the default. */
    public function testDeclaredOptionsWinOverTheEnumsCases(): void
    {
        $fields = $this->guessMovie(['genre' => ['options' => [['value' => 'drama', 'label' => 'Only drama']]]]);

        self::assertSame([['value' => 'drama', 'label' => 'Only drama']], $fields['genre']['options']);
    }

    public function testAStringColumnsLengthBecomesTheMaxRule(): void
    {
        $fields = $this->guessMovie(['title']);

        self::assertSame(160, $this->section($fields['title'], 'rules')['max']);
    }

    /**
     * A number column's `max` is its range, never a length — the only `max`
     * on `year` is the one the integer bound supplies.
     */
    public function testANumberColumnGetsNoLengthDerivedMax(): void
    {
        $fields = $this->guessMovie(['year', 'rating']);

        self::assertSame(2147483647, $this->section($fields['year'], 'rules')['max']);
        self::assertArrayNotHasKey('max', $this->section($fields['rating'], 'rules'));
    }

    // ── Required ─────────────────────────────────────────────────────────────

    public function testRequiredFollowsColumnNullability(): void
    {
        $fields = $this->guessMovie(['title', 'synopsis']);

        self::assertTrue($fields['title']['required'] ?? false);
        self::assertSame(true, $this->section($fields['title'], 'rules')['required']);
        self::assertSame(true, $this->section($fields['title'], 'props')['required'], 'The control is told as well, for the asterisk.');

        self::assertArrayNotHasKey('required', $fields['synopsis']);
        self::assertSame(false, $this->section($fields['synopsis'], 'props')['required']);
    }

    // ── Number bounds ────────────────────────────────────────────────────────

    /**
     * An out-of-range value should be a field error, not a database one, so
     * a number field inherits its column type's own range.
     */
    public function testIntegerColumnsInheritTheirTypesRange(): void
    {
        $fields = $this->guessMovie(['year', 'runtime']);

        $year = $this->section($fields['year'], 'rules');
        self::assertSame(-2147483648, $year['min']);
        self::assertSame(2147483647, $year['max']);

        $runtime = $this->section($fields['runtime'], 'rules');
        self::assertSame(-32768, $runtime['min']);
        self::assertSame(32767, $runtime['max']);
    }

    public function testDeclaredRulesWinOverTheGuessedBounds(): void
    {
        $fields = $this->guessMovie(['year' => ['rules' => ['min' => 1888]]]);

        $rules = $this->section($fields['year'], 'rules');
        self::assertSame(1888, $rules['min'], 'The declared bound.');
        self::assertSame(2147483647, $rules['max'], 'The guessed one is still merged underneath.');
    }

    public function testADecimalColumnsScaleBecomesTheStep(): void
    {
        $fields = $this->guessMovie(['rating']);

        self::assertSame('0.1', $this->section($fields['rating'], 'props')['step']);
    }

    // ── Key → property ───────────────────────────────────────────────────────

    /**
     * Form keys are snake_case (presenters emit them that way), Doctrine
     * properties are camelCase, and the write path already crosses that gap;
     * the guesser must too, or naming any multi-word property throws.
     */
    public function testASnakeCaseKeyResolvesToItsCamelCaseProperty(): void
    {
        $fields = $this->guessMovie(['released_on']);

        self::assertSame('released_on', $fields['released_on']['key'], 'The key stays the form\'s.');
        self::assertSame('date', $fields['released_on']['type'], 'The type came from the releasedOn mapping.');
        self::assertSame('Released On', $fields['released_on']['label']);
    }

    public function testAnIdSuffixedKeyResolvesToTheAssociation(): void
    {
        $fields = $this->guessMovie(['studio_id']);

        self::assertSame('belongs-to', $fields['studio_id']['type']);
        self::assertSame('Studio', $fields['studio_id']['label'], 'Labelled after the property, not the column.');
    }

    // ── Associations ─────────────────────────────────────────────────────────

    public function testAToOneBecomesABelongsToWithAGuessedLabelField(): void
    {
        $fields = $this->guessMovie(['studio_id']);

        $relation = $fields['studio_id']['relation'] ?? null;
        self::assertInstanceOf(RelationOptions::class, $relation);
        self::assertSame(Studio::class, $relation->entityClass);
        self::assertSame('name', $relation->labelField, 'The first of name/title/label the target maps.');
        self::assertSame('uuid', $relation->valueField, 'Identified by uuid, never by the integer id.');
        self::assertNull($relation->searchable, 'A guessed relation lets the row count decide.');
    }

    /**
     * `movies.studio_id` is nullable so the schema can ON DELETE SET NULL, and
     * the property carries `#[Assert\NotNull]` to say it is mandatory all the
     * same. The form must side with the constraint.
     */
    public function testAToOneIsRequiredWhenAConstraintSaysSoDespiteANullableJoinColumn(): void
    {
        $fields = $this->guessMovie(['studio_id']);

        self::assertTrue($fields['studio_id']['required'] ?? false);
        self::assertSame(true, $this->section($fields['studio_id'], 'rules')['required']);
    }

    /** No constraint and a nullable join column: optional, as a cast row's actor is. */
    public function testAToOneIsOptionalWhenNothingRequiresIt(): void
    {
        $fields = $this->byKey(
            $this->guesser()->guess($this->resourceFor(CastMember::class, ['actor_id'])) ?? [],
        );

        self::assertArrayNotHasKey('required', $fields['actor_id']);
        self::assertSame(false, $this->section($fields['actor_id'], 'props')['required']);
    }

    /** A non-nullable join column is enough on its own. */
    public function testAToOneIsRequiredWhenItsJoinColumnRefusesNull(): void
    {
        $fields = $this->byKey(
            $this->guesser()->guess($this->resourceFor(CastMember::class, ['movie_id'])) ?? [],
        );

        self::assertTrue($fields['movie_id']['required'] ?? false);
    }

    public function testAnOwningManyToManyBecomesAMultiselect(): void
    {
        $fields = $this->guessMovie(['tags']);

        self::assertSame('multiselect', $fields['tags']['type']);

        $relation = $fields['tags']['relation'] ?? null;
        self::assertInstanceOf(RelationOptions::class, $relation);
        self::assertSame(Tag::class, $relation->entityClass);
        self::assertSame('name', $relation->labelField);
        self::assertSame('uuid', $relation->valueField);
    }

    public function testAMappedByOneToManyBecomesARepeaterOverTheChildsOwnFields(): void
    {
        $fields = $this->guessMovie(['cast']);

        self::assertSame('repeater', $fields['cast']['type']);
        self::assertSame('full', $fields['cast']['width'], 'A repeater always takes the whole row.');

        $subFields = $this->subFields($fields['cast']);

        self::assertSame(['actor_id', 'character'], array_keys($subFields), 'Relations first, then scalars.');
        self::assertSame('text', $subFields['character']['type']);
        self::assertSame(120, $this->section($subFields['character'], 'rules')['max'], 'Guessed with the same rules the top level uses.');
    }

    /**
     * The row already belongs to the record being edited; offering `movie_id`
     * would let a row be reparented. Identity and `position` belong to the
     * repeater sync, not to the person filling in the form.
     */
    /**
     * Distributor maps a `name`, which the convention would pick — but it
     * marks `code` with #[LabelField], and the target's own declaration wins.
     */
    public function testATargetsLabelFieldBeatsTheNamingConvention(): void
    {
        $fields = $this->guessMovie(['distributor_id']);

        $relation = $fields['distributor_id']['relation'] ?? null;
        self::assertInstanceOf(RelationOptions::class, $relation);
        self::assertSame(Distributor::class, $relation->entityClass);
        self::assertSame('code', $relation->labelField);
    }

    /**
     * Remake points at Movie twice: `movie` is the inverse of Movie::$remakes
     * and `original` is another film. Only the one the mapping names as the
     * parent link is structural; the other is a field of the row.
     */
    public function testARepeaterRowKeepsASecondRelationToTheParentsClass(): void
    {
        $fields = $this->guessMovie(['remakes']);
        $keys   = array_column($fields['remakes']['fields'] ?? [], 'key');

        self::assertSame(['original_id', 'note'], $keys);
        self::assertNotContains('movie_id', $keys, 'The mappedBy side is not offered.');
    }

    /**
     * A text column is a paragraph: it takes the whole row and does not count
     * towards how the other fields share it. Remake has one association and
     * one paragraph, so the association is alone in its row.
     */
    public function testATextareaInARepeaterRowTakesTheWholeRow(): void
    {
        $subFields = $this->subFields($this->guessMovie(['remakes'])['remakes']);

        self::assertSame('textarea', $subFields['note']['type']);
        self::assertSame('full', $subFields['note']['width']);
        self::assertSame('full', $subFields['original_id']['width'], 'Alone once the paragraph is set aside.');
    }

    /**
     * `fields` as a key => overrides map adjusts a guessed row rather than
     * replacing it: one column widened, everything else still guessed.
     */
    public function testRepeaterSubFieldOverridesMergeOntoTheGuessedRow(): void
    {
        $subFields = $this->subFields($this->guessMovie([
            'cast' => ['fields' => ['character' => ['width' => 'full', 'label' => 'Role']]],
        ])['cast']);

        self::assertSame('full', $subFields['character']['width']);
        self::assertSame('Role', $subFields['character']['label']);
        self::assertSame('1/2', $subFields['actor_id']['width'], 'The untouched column keeps its guess.');
        self::assertSame('belongs-to', $subFields['actor_id']['type'], 'Still guessed, not declared.');
    }

    public function testARepeaterRowOmitsTheLinkBackToItsParentAndItsBookkeeping(): void
    {
        $subFields = $this->subFields($this->guessMovie(['cast'])['cast']);

        foreach (['movie_id', 'movie', 'position', 'id', 'uuid'] as $hidden) {
            self::assertArrayNotHasKey($hidden, $subFields);
        }
    }

    public function testARepeaterRowsToOneIsABelongsToLikeAnyOther(): void
    {
        $subFields = $this->subFields($this->guessMovie(['cast'])['cast']);

        self::assertSame('belongs-to', $subFields['actor_id']['type']);

        $relation = $subFields['actor_id']['relation'] ?? null;
        self::assertInstanceOf(RelationOptions::class, $relation);
        self::assertSame(Actor::class, $relation->entityClass);
        self::assertSame('name', $relation->labelField);
    }

    public function testRepeaterSubFieldLabelsAreHumanised(): void
    {
        $subFields = $this->subFields($this->guessMovie(['cast'])['cast']);

        self::assertSame('Character', $subFields['character']['label']);
        self::assertSame('Actor', $subFields['actor_id']['label']);
    }

    /**
     * Two fields share a row as halves; the width is decided from the count
     * of everything the row offers, associations included.
     */
    public function testRepeaterSubFieldsShareTheRowWhenThereAreFewOfThem(): void
    {
        $subFields = $this->subFields($this->guessMovie(['cast'])['cast']);

        self::assertSame('1/2', $subFields['character']['width']);
        self::assertSame('1/2', $subFields['actor_id']['width']);
    }

    // ── Overrides ────────────────────────────────────────────────────────────

    /** Options provided means "choose among these", whatever the column type. */
    public function testAnOptionsOverrideTurnsAScalarIntoASelect(): void
    {
        $fields = $this->guessMovie(['year' => ['options' => [1995 => '1995', 2004 => '2004']]]);

        self::assertSame('select', $fields['year']['type']);
        self::assertSame([1995 => '1995', 2004 => '2004'], $fields['year']['options']);
        self::assertArrayNotHasKey('min', $this->section($fields['year'], 'rules'), 'A select carries no number bounds.');
    }

    public function testDeclaredOverridesAlwaysWinOverGuesses(): void
    {
        $fields = $this->guessMovie([
            'title'     => ['label' => 'Name', 'required' => false, 'rules' => ['max' => 20], 'width' => '1/2'],
            'studio_id' => ['relation' => new RelationOptions(Studio::class, 'city', 'id', searchable: true)],
        ]);

        self::assertSame('Name', $fields['title']['label']);
        self::assertArrayNotHasKey('required', $fields['title']);
        self::assertSame(20, $this->section($fields['title'], 'rules')['max']);
        self::assertSame('1/2', $fields['title']['width']);

        $relation = $fields['studio_id']['relation'] ?? null;
        self::assertInstanceOf(RelationOptions::class, $relation);
        self::assertSame('city', $relation->labelField);
        self::assertSame('id', $relation->valueField);
        self::assertTrue($relation->searchable);
    }

    // ── Refusals ─────────────────────────────────────────────────────────────

    /** NOT NULL on a boolean is satisfied by false; a toggle cannot be "left blank". */
    public function testANonNullableBooleanIsNotRequired(): void
    {
        $fields = $this->guessMovie(['released']);

        self::assertArrayNotHasKey('required', $fields['released']);
        self::assertArrayNotHasKey('required', $this->section($fields['released'], 'rules'));
    }

    /**
     * A `type` on the entry is the field's own say: it wins over the column,
     * while what the column knows — its length, its nullability — still
     * applies. One list, whether a field is guessed or pinned.
     */
    public function testADeclaredTypeWinsOverTheColumnAndKeepsWhatTheColumnKnows(): void
    {
        $fields = $this->guessMovie(['title' => ['type' => TextareaType::class]]);

        self::assertSame(TextareaType::component(), $fields['title']['type']);
        self::assertSame(160, $this->section($fields['title'], 'rules')['max'], 'The column length still applies.');
        self::assertTrue($fields['title']['required'] ?? false, 'A non-nullable column is still required.');
        self::assertArrayNotHasKey('type', $this->section($fields['title'], 'props'), 'The type is not passed on as an option.');
    }

    /** With a `type`, a key the entity does not map is declared outright — a computed value, a set, an embed. */
    public function testAnEntryWithATypeNeedsNoMapping(): void
    {
        $fields = $this->guessMovie([
            'title',
            'days_until' => ['type' => ComputedType::class, 'accessor' => 'daysUntil', 'label' => 'Days until'],
        ]);

        self::assertSame(['title', 'days_until'], array_keys($fields));
        self::assertSame(ComputedType::component(), $fields['days_until']['type']);
        self::assertSame('Days until', $fields['days_until']['label']);
    }

    public function testATypeThatIsNotAFieldTypeIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('the `type` of "title" must be a Modufolio\\Panel\\Field\\FieldTypeInterface class name, got int');

        $this->guessMovie(['title' => ['type' => 42]]);
    }

    public function testNamingAKeyTheEntityDoesNotMapThrowsNamingBoth(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/"director_id"/');
        $this->expectExceptionMessageMatches('/' . preg_quote(Movie::class, '/') . '/');

        $this->guesser()->guess($this->resourceFor(Movie::class, ['title', 'director_id']));
    }

    /**
     * `[['label' => 'Title']]` is a slip for `['title' => ['label' => 'Title']]`
     * — options with no key to hang them on; refused rather than guessed at.
     */
    public function testAPlainEntryThatIsNotAStringThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Form: a plain entry must be a field name');

        $this->guesser()->guess($this->resourceFor(Movie::class, [['label' => 'Title']]));
    }

    /**
     * CastMember maps none of name/title/label, so a relation pointing at it
     * has no label to guess; the message says how to declare one instead.
     */
    public function testAToOneWhoseTargetHasNoGuessableLabelThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/' . preg_quote(CastMember::class, '/') . '.*RelationOptions/');

        $this->guesser()->guess($this->resourceFor(Credit::class, ['cast_member_id']));
    }

    /**
     * fields() says what a key is; the form entry says what the form alone
     * needs. Two levels: the entry wins where both speak, fields() fills the
     * rest, and the mapping fills what neither says.
     */
    public function testAFormEntryTakesWhatFieldsDeclaresAndOverridesIt(): void
    {
        $resource = new class extends MovieResource {
            public function fields(): array
            {
                return [Field::make('year')->label('Release year')->help('Four digits')];
            }

            public function form(): Form
            {
                return Form::make()->fields(['title', 'year' => ['help' => 'The premiere year']]);
            }
        };

        $fields = $this->guesser()->guess($resource);

        self::assertNotNull($fields);
        self::assertSame('Release year', $fields[1]['label'], 'From fields().');
        self::assertSame('The premiere year', $fields[1]['help'], 'The entry wins.');
        $plain = $this->guesser()->guess(new MovieResource()) ?? [];
        self::assertSame($plain[2]['type'], $fields[1]['type'], 'The mapping still says what neither did.');
        self::assertSame($plain[2]['props'], $fields[1]['props']);
    }

    public function testTheFixtureResourceGuessesInDeclarationOrder(): void
    {
        $fields = $this->guesser()->guess(new MovieResource());

        self::assertNotNull($fields);
        self::assertSame(
            ['title', 'synopsis', 'year', 'runtime', 'rating', 'released', 'released_on', 'studio_id', 'tags', 'cast'],
            array_column($fields, 'key'),
        );
    }

    /** No keys means the resource declares no form — and so gets no write routes. */
    public function testAResourceWithoutKeysYieldsNull(): void
    {
        $resource = $this->resourceFor(Movie::class, null);

        self::assertNull($this->guesser()->guess($resource));
    }

    /** An empty list is a declared form with nothing in it, not the absence of one. */
    public function testAnEmptyKeyListYieldsAnEmptyForm(): void
    {
        self::assertSame([], $this->guesser()->guess($this->resourceFor(Movie::class, [])));
    }
}
