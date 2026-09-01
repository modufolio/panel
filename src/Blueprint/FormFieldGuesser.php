<?php

declare(strict_types = 1);

namespace Modufolio\Panel\Blueprint;

use Modufolio\Panel\Field\BelongsToType;
use Modufolio\Panel\Field\DateType;
use Modufolio\Panel\Field\DecimalType;
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
 * A resource that implements {@see PanelResource::formFieldKeys()} names only
 * *which* fields its form edits (plus any overrides); everything else is
 * inferred from what the ORM already knows — the same discipline the JSON:API
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
 * Declared overrides always win, and everything is assembled through the same
 * BlueprintBuilder a hand-written formFields() uses, so the output shape — and
 * the option validation — is identical. A resource can still write formFields()
 * by hand; that takes precedence entirely.
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
        return $this->guessForm($resource)?->fields;
    }

    /**
     * The same guess, with the per-field access callables kept.
     *
     * `guess()` returns only what can be serialised to the client, which is
     * why a declared `access` used to end here: the builder collected it and
     * the return value had nowhere to carry it. Callers that enforce access
     * want both halves, so they ask for the definition rather than the fields.
     */
    public function guessForm(PanelResource $resource): ?FormDefinition
    {
        $keys = $resource->formFieldKeys();

        if ($keys === null) {
            return null;
        }

        $meta    = $this->em->getClassMetadata($resource->entityClass());
        $builder = new BlueprintBuilder();

        foreach ($this->normalizeKeys($keys) as $key => $overrides) {
            [$type, $options] = $this->guessField($meta, $key, $overrides);

            $builder->add($key, $type, $options);
        }

        return new FormDefinition($builder->fields(), $builder->access());
    }

    /**
     * Accept both plain entries and key => overrides:
     * `['title', 'genre' => ['options' => …]]`.
     *
     * @param array<int|string, string|array<string, mixed>> $keys
     *
     * @return array<string, array<string, mixed>>
     */
    private function normalizeKeys(array $keys): array
    {
        $normalized = [];

        foreach ($keys as $key => $value) {
            if (is_int($key)) {
                if (!is_string($value)) {
                    throw new \InvalidArgumentException(
                        'formFieldKeys(): a plain entry must be a field name; use `key => [overrides]` to pass options.',
                    );
                }

                $normalized[$value] = [];
            } else {
                $normalized[$key] = is_array($value) ? $value : [];
            }
        }

        return $normalized;
    }

    /**
     * @param ClassMetadata<object> $meta
     * @param array<string, mixed>  $overrides
     *
     * @return array{class-string, array<string, mixed>}
     */
    private function guessField(ClassMetadata $meta, string $key, array $overrides): array
    {
        $property = $this->resolveProperty($meta, $key);

        if ($property === null) {
            throw new \InvalidArgumentException(sprintf(
                'formFieldKeys() names "%s", but %s maps no such field or association.',
                $key,
                $meta->getName(),
            ));
        }

        if ($meta->hasAssociation($property)) {
            return $this->guessAssociation($meta, $property, $overrides);
        }

        return $this->guessScalar($meta, $property, $overrides);
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
     *
     * @return array{class-string, array<string, mixed>}
     */
    private function guessScalar(ClassMetadata $meta, string $field, array $overrides): array
    {
        // Options provided means "choose among these" — a select, whatever the
        // column type underneath.
        $type = isset($overrides['options']) ? SelectType::class : match ((string) $meta->getTypeOfField($field)) {
            'text' => TextareaType::class,
            'integer', 'smallint', 'bigint' => NumberType::class,
            'decimal', 'float' => DecimalType::class,
            'boolean' => ToggleType::class,
            'date', 'date_immutable', 'datetime', 'datetime_immutable' => DateType::class,
            default => TextType::class,
        };

        $options = $overrides;

        $options['required'] ??= !$meta->isNullable($field)
            || $this->isConstrainedRequired($meta->getName(), $field);

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
     *
     * @return array{class-string, array<string, mixed>}
     */
    private function guessAssociation(ClassMetadata $meta, string $property, array $overrides): array
    {
        $mapping = $meta->getAssociationMapping($property);
        $target  = $mapping['targetEntity'];

        if ($meta->isSingleValuedAssociation($property)) {
            $options = $overrides;
            $options['label']    ??= ucwords(str_replace('_', ' ', $property));
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
                return [ImageType::class, $options];
            }

            return [BelongsToType::class, $options];
        }

        // Owning ManyToMany → multiselect of existing records.
        if (($mapping['type'] & ClassMetadata::MANY_TO_MANY) !== 0) {
            $options = $overrides;
            $options['relation'] ??= new RelationOptions($target, $this->guessLabelField($target), 'uuid');

            return [ManyToManyType::class, $options];
        }

        // Mapped-by OneToMany → repeater over the child's own scalar fields.
        $options = $overrides;
        $options['fields'] ??= $this->guessSubFields($target, $meta->getName());

        return [HasManyType::class, $options];
    }

    /**
     * The child side of a repeater: its own fields, minus bookkeeping, guessed
     * with the same rules the top level uses — including to-one associations,
     * which become BelongsTo selects.
     *
     * @param class-string $childClass
     *
     * @return list<array<string, mixed>>
     */
    private function guessSubFields(string $childClass, string $parentClass): array
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
                && $childMeta->getAssociationMapping($name)['targetEntity'] !== $parentClass,
        ));

        // A row's fields share its width when there are few enough to stay
        // readable side by side — two halves, three thirds. Beyond that the
        // columns get too narrow for a label, so each field takes its own
        // row. The grid is twelve columns, which is why four quarters is
        // expressible but four is where a repeater row starts feeling like a
        // spreadsheet.
        $width = match (count($editable) + count($associations)) {
            2       => '1/2',
            3       => '1/3',
            default => 'full',
        };

        $builder = new BlueprintBuilder();

        foreach ($associations as $association) {
            [$type, $options] = $this->guessAssociation($childMeta, $association, []);

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
            $options['label'] ??= ucfirst(strtolower(trim(preg_replace('/(?<!^)[A-Z]/', ' $0', $field) ?? $field)));

            $builder->add(Str::snake($field), $type, [...$options, 'width' => $width]);
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

    /** @param class-string $target */
    private function guessLabelField(string $target): string
    {
        $targetMeta = $this->em->getClassMetadata($target);

        foreach (self::LABEL_FIELDS as $candidate) {
            if ($targetMeta->hasField($candidate)) {
                return $candidate;
            }
        }

        throw new \InvalidArgumentException(sprintf(
            '%s maps none of [%s]; declare the relation explicitly with a RelationOptions naming its label field.',
            $target,
            implode(', ', self::LABEL_FIELDS),
        ));
    }
}
