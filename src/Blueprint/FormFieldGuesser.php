<?php

declare(strict_types = 1);

namespace Modufolio\Panel\Blueprint;

use Modufolio\Panel\Blueprint\EnumOptions;
use Modufolio\Panel\Blueprint\FormType;
use Modufolio\Panel\Blueprint\LabelField;
use Modufolio\Panel\Blueprint\Separator;
use Modufolio\Panel\Field\BelongsToType;
use Modufolio\Panel\Field\DateTimeType;
use Modufolio\Panel\Field\DateType;
use Modufolio\Panel\Field\DecimalType;
use Modufolio\Panel\Field\FieldTypeInterface;
use Modufolio\Panel\Field\HasManyType;
use Modufolio\Panel\Field\ImageType;
use Modufolio\Panel\Field\ManyToManyType;
use Modufolio\Panel\Field\NumberType;
use Modufolio\Panel\Field\SelectType;
use Modufolio\Panel\Field\TextType;
use Modufolio\Panel\Field\TextareaType;
use Modufolio\Panel\Field\ToggleType;
use Modufolio\Panel\Resource\PanelResource;
use Modufolio\Panel\Table\RelationOptions;
use Modufolio\Appkit\Toolkit\Str;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * Derive a resource's form declaration from Doctrine's metadata.
 *
 * A resource's {@see PanelResource::form()} names *which* fields its
 * form edits (plus any options); everything else is inferred from what the
 * ORM already knows — the same discipline the JSON:API
 * package follows, proven end to end by the bookstore experiment's OpenAPI
 * generator:
 *
 *  - `max` rules from the column length,
 *  - `required` from column / join-column nullability,
 *  - the field type from the Doctrine type (integer → number, text → textarea,
 *    boolean → toggle, date → date picker),
 *  - relations from the association mapping: to-one → BelongsTo (with the
 *    "— None —" option following join nullability), owning ManyToMany →
 *    multiselect, mapped-by OneToMany → repeater with the child's own scalar
 *    fields guessed the same way.
 *
 * Declared options always win. An entry that names its `type` is declared
 * outright — the type is taken as written and the key need not be mapped —
 * so a form is one list whether its fields are guessed, pinned or invented.
 * Everything goes through one BlueprintBuilder, so the output shape and the
 * option validation are the same for every entry.
 */
final class FormFieldGuesser
{
    /** Relation labels are guessed from the first of these the target maps. */
    private const LABEL_FIELDS = ['name', 'title', 'label'];

    /**
     * Bookkeeping the guesser never offers for editing: identity, ordering
     * (owned by the repeater sync) and timestamps (owned by the entity).
     */
    private const HIDDEN_FIELDS = ['id', 'uuid', 'position', 'createdAt', 'updatedAt'];

    /**
     * @param class-string|null $mediaEntityClass The application's media-library
     *   entity, if it has one. A to-one association pointing at it is guessed
     *   as an image field rather than a lookup. Named by the host rather than
     *   hard-coded, because "the media library" is an application's concept and
     *   not every application that uses this panel has one.
     */
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ?string $mediaEntityClass = null,
    ) {
    }

    /**
     * @return list<array<string, mixed>>|null null when the resource declares no keys
     */
    public function guess(PanelResource $resource): ?array
    {
        $form = $resource->form();

        if ($form === null) {
            return null;
        }

        $meta    = $this->em->getClassMetadata($resource->entityClass());
        $shared  = $resource->fieldDefinitions();
        $builder = new BlueprintBuilder();

        foreach ($form->entries() as [$key, $overrides]) {
            if ($key instanceof Separator) {
                $builder->separator($key);

                continue;
            }

            // What fields() says about the key first, what the form entry says
            // on top: two levels, the entry's own always winning.
            $options = [...($shared[$key] ?? []), ...$overrides];

            [$type, $options] = $this->guessField($meta, $key, $options);

            $builder->add($key, $type, $options);
        }

        return $builder->fields();
    }


    /**
     * @param ClassMetadata<object> $meta
     * @param array<string, mixed>  $overrides
     *
     * @return array{class-string, array<string, mixed>}
     */
    private function guessField(ClassMetadata $meta, string $key, array $overrides): array
    {
        // A declared type is the entry's own say, ahead of the column and of a
        // #[FormType] attribute. Taken out of the options here: the builder
        // takes the type as an argument, not an option.
        $pinned = $overrides['type'] ?? null;
        unset($overrides['type']);

        if ($pinned !== null && (!is_string($pinned) || !is_a($pinned, FieldTypeInterface::class, true))) {
            throw new \InvalidArgumentException(sprintf(
                'form(): the `type` of "%s" must be a %s class name, got %s.',
                $key,
                FieldTypeInterface::class,
                is_string($pinned) ? $pinned : get_debug_type($pinned),
            ));
        }

        $property = $this->resolveProperty($meta, $key);

        if ($property === null) {
            if ($pinned !== null) {
                // Nothing to guess from and nothing to guess: a set, an embed,
                // a computed value. The builder still validates the options.
                return [$pinned, $overrides];
            }

            throw new \InvalidArgumentException(sprintf(
                'form() names "%s", but %s maps no such field or association. Give the entry a `type` to declare it outright.',
                $key,
                $meta->getName(),
            ));
        }

        if ($meta->hasAssociation($property)) {
            return $this->guessAssociation($meta, $property, $overrides, $pinned);
        }

        return $this->guessScalar($meta, $property, $overrides, $pinned);
    }

    /**
     * The mapped property a form key refers to, or null when it names nothing.
     *
     * Two conventions to cross, both of which the *write* path already handles
     * — `applyValues()` turns `birth_year` into `setBirthYear()`:
     *
     *  - `director_id` edits the `director` association: the `_id` suffix is
     *    the form's, the property is what the ORM knows.
     *  - `birth_year` edits the `birthYear` property: form keys are snake_case
     *    here (presenters emit them that way), Doctrine properties are camel.
     *
     * The guesser used to compare the literal key only, so any entity with a
     * multi-word property threw the moment it was named.
     *
     * @param ClassMetadata<object> $meta
     */
    private function resolveProperty(ClassMetadata $meta, string $key): ?string
    {
        $candidates = [$key, $this->camelize($key)];

        if (str_ends_with($key, '_id')) {
            $withoutSuffix = substr($key, 0, -3);
            $candidates[]  = $withoutSuffix;
            $candidates[]  = $this->camelize($withoutSuffix);
        }

        foreach ($candidates as $candidate) {
            if ($meta->hasAssociation($candidate) || $meta->hasField($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /** 'birth_year' → 'birthYear' */
    private function camelize(string $key): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $key))));
    }

    /**
     * @param ClassMetadata<object> $meta
     * @param array<string, mixed>  $overrides
     * @param class-string|null     $pinned  the entry's own `type`, ahead of everything below
     *
     * @return array{class-string, array<string, mixed>}
     */
    private function guessScalar(ClassMetadata $meta, string $field, array $overrides, ?string $pinned = null): array
    {
        // The property's own say comes first: a `string` column is only ever
        // a text input to the mapping, so an email or a URL has to be declared
        // beside the column. Failing that, options provided means "choose
        // among these" — a select, whatever the column type underneath.
        // An enumType column is a choice among the cases, whatever its
        // storage type — a select, with the cases as its options unless the
        // resource names others.
        $enum = $meta->getFieldMapping($field)['enumType'] ?? null;

        if (is_string($enum) && is_a($enum, \BackedEnum::class, true)) {
            $overrides['options'] ??= EnumOptions::for($enum);
        }

        $type = $pinned ?? $this->declaredType($meta, $field) ?? (isset($overrides['options']) ? SelectType::class : match ((string) $meta->getTypeOfField($field)) {
            'text' => TextareaType::class,
            'integer', 'smallint', 'bigint' => NumberType::class,
            'decimal', 'float' => DecimalType::class,
            'boolean' => ToggleType::class,
            'date', 'date_immutable' => DateType::class,
            'datetime', 'datetime_immutable', 'datetimetz', 'datetimetz_immutable' => DateTimeType::class,
            default => TextType::class,
        });

        $options = $overrides;

        // A boolean column is never required: a toggle has no blank state, and
        // NOT NULL is satisfied by false. Marking it required only made an
        // unchecked box — or a client that left it out — a validation error.
        if ((string) $meta->getTypeOfField($field) !== 'boolean') {
            $options['required'] ??= !$meta->isNullable($field)
                || $this->isConstrainedRequired($meta->getName(), $field);
        }

        $mapping = $meta->getFieldMapping($field);
        $length  = $mapping['length'] ?? null;

        if ($length !== null && $type !== NumberType::class) {
            // Merged under the declared rules, never over them.
            $options['rules'] = ($overrides['rules'] ?? []) + ['max' => (int) $length];
        }

        // A decimal column knows its own granularity: scale 3 means the
        // finest expressible step is 0.001, and the input advertises exactly
        // that rather than a made-up default. Floats have no such fact, so
        // they accept any step.
        if ($type === DecimalType::class) {
            $scale = (int) ($mapping['scale'] ?? 0);
            $options['props'] = ($overrides['props'] ?? [])
                + ['step' => $scale > 0 ? rtrim(number_format(10 ** -$scale, $scale, '.', ''), '0') : 'any'];
        }

        // A number field inherits the column type's own range, so an
        // out-of-range value is a field error rather than a database one.
        // Bigint covers PHP's int range; nothing useful to add there.
        if ($type === NumberType::class) {
            $bounds = match ((string) $meta->getTypeOfField($field)) {
                'smallint' => ['min' => -32768, 'max' => 32767],
                'integer'  => ['min' => -2147483648, 'max' => 2147483647],
                default    => [],
            };

            if ($bounds !== []) {
                $options['rules'] = ($overrides['rules'] ?? []) + $bounds;
            }
        }

        return [$type, $options];
    }

    /**
     * @param ClassMetadata<object> $meta
     * @param array<string, mixed>  $overrides
     * @param class-string|null     $pinned  the entry's own `type`, ahead of the mapping
     *
     * @return array{class-string, array<string, mixed>}
     */
    private function guessAssociation(ClassMetadata $meta, string $property, array $overrides, ?string $pinned = null): array
    {
        $mapping = $meta->getAssociationMapping($property);
        $target  = $mapping['targetEntity'];

        if ($meta->isSingleValuedAssociation($property)) {
            $options = $overrides;
            // Title case over the property's words, camelCase split too:
            // `connectedContact` reads "Connected Contact", not one word.
            $options['label']    ??= Str::ucwords(self::words($property));
            $options['relation'] ??= new RelationOptions($target, $this->guessLabelField($target), 'uuid');

            // A relation is required when its join column refuses null — or
            // when the property carries a NotNull/NotBlank constraint, which
            // is where requiredness lives for a column that must stay nullable
            // for ON DELETE SET NULL (movies.director being the proof).
            $joinColumns = $mapping['joinColumns'] ?? [];
            $options['required'] ??= !($joinColumns[0]['nullable'] ?? true)
                || $this->isConstrainedRequired($meta->getName(), $property);

            // A to-one association whose target is the media library is a
            // picture, not a lookup — the picker resolves through the same
            // 'relation' option (uuid → the media entity) a BelongsTo would, so
            // the write path needs no special case for it.
            if ($this->mediaEntityClass !== null && $target === $this->mediaEntityClass) {
                return [$pinned ?? $this->declaredType($meta, $property) ?? ImageType::class, $options];
            }

            return [$pinned ?? $this->declaredType($meta, $property) ?? BelongsToType::class, $options];
        }

        // Owning ManyToMany → multiselect of existing records.
        if (($mapping['type'] & ClassMetadata::MANY_TO_MANY) !== 0) {
            $options = $overrides;
            $options['relation'] ??= new RelationOptions($target, $this->guessLabelField($target), 'uuid');

            return [$pinned ?? $this->declaredType($meta, $property) ?? ManyToManyType::class, $options];
        }

        // Mapped-by OneToMany → repeater over the child's own scalar fields.
        //
        // `fields` in the overrides is either a full declaration (a list of
        // specs, handed over verbatim) or a map of sub-field key => overrides,
        // merged onto the guessed row the way top-level overrides are — so a
        // resource can widen one column without writing the whole row out.
        $options = $overrides;
        $declared = $options['fields'] ?? null;

        if (!is_array($declared) || $declared === [] || !array_is_list($declared)) {
            $guessed = $this->guessSubFields($target, $mapping['mappedBy'] ?? null);

            if (is_array($declared) && $declared !== []) {
                foreach ($guessed as $index => $subField) {
                    $key = (string) ($subField['key'] ?? '');

                    if (isset($declared[$key]) && is_array($declared[$key])) {
                        $guessed[$index] = [...$subField, ...$declared[$key]];
                    }
                }
            }

            $options['fields'] = $guessed;
        }

        return [$pinned ?? $this->declaredType($meta, $property) ?? HasManyType::class, $options];
    }

    /**
     * The field type the property declares for itself with {@see FormType},
     * or null to fall back to what the mapping implies.
     *
     * Read off the reflection the metadata already holds: Doctrine's driver
     * only reads its own attributes into the mapping, so this one is invisible
     * to `getFieldMapping()` and has to be asked for by name.
     *
     * @param ClassMetadata<object> $meta
     *
     * @return class-string|null
     */
    private function declaredType(ClassMetadata $meta, string $property): ?string
    {
        $reflection = $meta->getReflectionProperty($property);
        $attributes = $reflection?->getAttributes(FormType::class) ?? [];

        if ($attributes === []) {
            return null;
        }

        return $attributes[0]->newInstance()->type;
    }

    /**
     * The child side of a repeater: its own fields, minus bookkeeping, guessed
     * with the same rules the top level uses — including to-one associations,
     * which become BelongsTo selects.
     *
     * @param class-string $childClass
     * @param string|null  $inverse    the child's property that points back at
     *                                 the parent — the OneToMany's `mappedBy`
     *
     * @return list<array<string, mixed>>
     */
    private function guessSubFields(string $childClass, ?string $inverse): array
    {
        $childMeta = $this->em->getClassMetadata($childClass);

        $editable = array_values(array_filter(
            $childMeta->getFieldNames(),
            static fn (string $field): bool => !in_array($field, self::HIDDEN_FIELDS, true),
        ));

        // A row may point at something as well as describe it — a cast entry
        // names a character *and* references an actor. To-one associations are
        // guessed exactly as they are at the top level, so the sub-field is a
        // BelongsTo select; to-many inside a repeater is not offered, since a
        // list inside a list has no honest editing story.
        $associations = array_values(array_filter(
            $childMeta->getAssociationNames(),
            static fn (string $name): bool => $childMeta->isSingleValuedAssociation($name)
                && !in_array($name, self::HIDDEN_FIELDS, true)
                // The inverse side back to the parent is structural, not
                // editable: the row already belongs to the record being
                // edited, and offering it would let a row be reparented.
                // Named by the mapping, not matched by class: a row may also
                // reference *another* record of the parent's class, and that
                // one is a real field (a contact's connections).
                && $name !== $inverse,
        ));

        // A textarea is a paragraph, not a cell: it takes the whole row and
        // does not count towards how the others share it.
        $paragraphs = array_values(array_filter(
            $editable,
            static fn (string $field): bool => (string) $childMeta->getTypeOfField($field) === 'text',
        ));

        // The remaining fields share the row when there are few enough to
        // stay readable side by side — two halves, three thirds. Beyond that
        // the columns get too narrow for a label, so each field takes its
        // own row. The grid is twelve columns, which is why four quarters is
        // expressible but four is where a repeater row starts feeling like a
        // spreadsheet.
        $width = match (count($editable) - count($paragraphs) + count($associations)) {
            2       => '1/2',
            3       => '1/3',
            default => 'full',
        };

        $builder = new BlueprintBuilder();

        foreach ($associations as $association) {
            [$type, $options] = $this->guessAssociation($childMeta, $association, []);

            // Sentence case, like the row's scalar fields beside it — a row
            // reading "Connected contact / Connection type / Notes" in one
            // register, rather than the top level's title case in another.
            $options['label'] = Str::ucfirst(self::words($association));

            // Same `_id` convention the top level uses: the form edits
            // `actor_id`, the entity keeps `setActor()`.
            $builder->add(Str::snake($association) . '_id', $type, [...$options, 'width' => $width]);
        }

        foreach ($editable as $field) {
            [$type, $options] = $this->guessScalar($childMeta, $field, []);

            // Snake_cased like every other form key: Doctrine reports the
            // *property* (`unitCost`), while presenters, the write path and
            // the top-level guesser all speak snake_case. One-word properties
            // hid the difference until a child grew a two-word one, whose
            // rows then round-tripped as `unitCost` against a presenter
            // sending `unit_cost` — an always-empty field.
            $options['label'] ??= Str::ucfirst(self::words($field));

            $builder->add(Str::snake($field), $type, [
                ...$options,
                'width' => in_array($field, $paragraphs, true) ? 'full' : $width,
            ]);
        }

        return $builder->fields();
    }

    /**
     * Whether the property itself declares it must hold a value.
     *
     * The schema is not the only authority on requiredness: a relation whose
     * FK must stay nullable (ON DELETE SET NULL) expresses "still mandatory"
     * through a NotNull/NotBlank constraint — the same constraints
     * handleFormData() already enforces as the final word.
     *
     * @param class-string $class
     */
    private function isConstrainedRequired(string $class, string $property): bool
    {
        $reflection = new \ReflectionProperty($class, $property);

        foreach ($reflection->getAttributes() as $attribute) {
            if (in_array($attribute->getName(), [
                \Symfony\Component\Validator\Constraints\NotNull::class,
                \Symfony\Component\Validator\Constraints\NotBlank::class,
            ], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * A property name as lowercase words: `connectedContact` and
     * `connected_contact` both give "connected contact". Studly first folds
     * the separators, snake with a space then splits the capitals.
     */
    private static function words(string $property): string
    {
        return Str::snake(Str::studly($property), ' ');
    }

    /** @param class-string $target */
    private function guessLabelField(string $target): string
    {
        $targetMeta = $this->em->getClassMetadata($target);

        // The entity's own say first: a #[LabelField] on a mapped column.
        foreach ($targetMeta->getFieldNames() as $field) {
            if ($targetMeta->getReflectionProperty($field)?->getAttributes(LabelField::class) !== []) {
                return $field;
            }
        }

        foreach (self::LABEL_FIELDS as $candidate) {
            if ($targetMeta->hasField($candidate)) {
                return $candidate;
            }
        }

        throw new \InvalidArgumentException(sprintf(
            '%s maps none of [%s] and marks no column with #[LabelField]; add the attribute, or declare the relation explicitly with a RelationOptions naming its label field.',
            $target,
            implode(', ', self::LABEL_FIELDS),
        ));
    }
}
