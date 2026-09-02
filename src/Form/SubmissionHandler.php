<?php

declare(strict_types=1);

namespace Modufolio\Panel\Form;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Modufolio\Appkit\Toolkit\Str;
use Modufolio\Panel\Blueprint\Defaults;
use Modufolio\Panel\Blueprint\FieldAccess;
use Modufolio\Panel\Blueprint\FieldValidator;
use Modufolio\Panel\Resource\PanelResource;
use Modufolio\Panel\Table\RelationOptions;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * A form submission, from the request body to a persisted record.
 *
 * The resource's form declaration drives every step: the same field list
 * that rendered the form decides what is read from the body, what a user may
 * write, what a `when` keeps off screen, what is valid, which identifiers
 * name related records, and which setter each value reaches. Nothing here is
 * per resource; a resource that needs more graduates to a hand-written
 * controller and keeps the declaration.
 *
 * Errors come back keyed by field, dotted for a repeater's rows
 * (`cast.2.actor_id`) so the client can pin a message where the value was
 * typed. An empty array means the record was written.
 */
final class SubmissionHandler
{
    public function __construct(
        private readonly FormResolver $forms,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * Validate the submission against the declared fields, map it onto the
     * entity and persist it.
     *
     * @param  array<string, mixed> $body the parsed request body
     * @return array<string, string> errors by field key, empty on success
     */
    public function handle(PanelResource $resource, object $entity, array $body, ?object $user = null): array
    {
        $fields = $this->forms->fieldsFor($resource);

        // A field this user may not change is not merely disabled in the
        // form: the submission is dropped here, because a disabled input is
        // a suggestion and the request is what actually arrives.
        $readonly = $resource->readonlyFields($entity, $user);

        if ($readonly !== []) {
            $fields = array_values(array_filter(
                $fields,
                static fn (array $field): bool => !in_array((string) ($field['key'] ?? ''), $readonly, true),
            ));
        }

        $values = $this->coerceValues($fields, $body);

        // Three passes before anything is validated, in this order because
        // each depends on the last:
        //
        //  1. drop what this user may not write — the form disabled those
        //     inputs; a request is not a form, and this is the guard;
        //  2. fill blanks from the declared defaults, resolving `@now` /
        //     `@today` now rather than at blueprint build — under a worker
        //     runtime a literal would freeze the process's boot time into
        //     every record it writes;
        //  3. drop what a `when` says is not on screen — after defaults, so
        //     a hidden field cannot arrive by way of its own default either.
        $values = FieldAccess::stripDenied($this->forms->accessFor($resource), $values, $user, $entity);
        $values = Defaults::resolve($fields, $values);
        $values = FieldValidator::stripHidden($fields, $values);

        $errors = FieldValidator::validate(self::scalarFields($fields), $values);

        foreach (self::repeaterFields($fields) as $field) {
            $rows    = self::rowsIn($values, (string) $field['key']);
            $errors += self::validateRows($field, $rows);
            $errors += $this->duplicateRowErrors($entity, $field, $rows);
        }

        if ($errors === []) {
            $errors = $this->resolveRelations($fields, $values);
        }

        // A repeater row may reference something too — a cast entry points
        // at an actor. Resolved per row, with the row index in the error key.
        if ($errors === []) {
            foreach (self::repeaterFields($fields) as $field) {
                $key       = (string) $field['key'];
                $subFields = FormResolver::subFields($field);
                $resolved  = [];

                foreach (self::rowsIn($values, $key) as $index => $row) {
                    foreach ($this->resolveRelations($subFields, $row) as $subKey => $message) {
                        $errors["{$key}.{$index}.{$subKey}"] = $message;
                    }

                    $resolved[] = $row;
                }

                $values[$key] = $resolved;
            }
        }

        if ($errors === []) {
            $errors = $this->uniquenessErrors($entity, $fields, $values);
        }

        if ($errors !== []) {
            return $errors;
        }

        // Collections go through their association, never a setter: child
        // rows (repeater) and resolved to-many relations (multiselect, whose
        // identifiers resolveRelations() turned into entity lists).
        $repeaterRows = [];

        foreach (self::repeaterFields($fields) as $field) {
            $key                = (string) $field['key'];
            $repeaterRows[$key] = [$field, self::rowsIn($values, $key)];
            unset($values[$key]);
        }

        $collectionRelations = [];

        foreach ($fields as $field) {
            $key = (string) ($field['key'] ?? '');

            if (($field['relation'] ?? null) instanceof RelationOptions && is_array($values[$key] ?? null)) {
                $collectionRelations[$key] = self::objectsIn($values[$key]);
                unset($values[$key]);
            }
        }

        $this->applyValues($entity, $values, $fields);

        foreach ($repeaterRows as [$field, $rows]) {
            $this->syncHasMany($entity, $field, $rows);
        }

        foreach ($collectionRelations as $key => $related) {
            $this->syncCollectionRelation($entity, $key, $related);
        }

        // The entity's own constraints stay the final authority — the
        // declared rules are the form's contract, the entity's are the
        // domain's, and both must pass.
        foreach ($this->validator->validate($entity) as $violation) {
            $errors[Str::snake($violation->getPropertyPath())] ??= (string) $violation->getMessage();
        }

        if ($errors !== []) {
            return $errors;
        }

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return [];
    }

    /**
     * Add one row to a record's relation without the full form — the
     * drawer's add action.
     *
     * Two shapes, both already described by the declaration: a repeater
     * field creates the child and attaches it after whatever the record
     * already has; a to-many field resolves one identifier and links it.
     *
     * @param  array<string, mixed> $body
     * @return array<string, string> errors by field key, empty on success
     *
     * @throws \InvalidArgumentException when the key is not a field this can add to
     */
    public function append(PanelResource $resource, object $entity, string $key, array $body): array
    {
        $field = $this->forms->field($resource, $key);

        if ($field === null) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a field of this resource.', $key));
        }

        if (($field['type'] ?? null) === 'repeater') {
            $subFields = FormResolver::subFields($field);
            $row       = $this->coerceValues($subFields, $body);
            $errors    = FieldValidator::validate(self::scalarFields($subFields), $row);

            if ($errors === []) {
                $errors = $this->resolveRelations($subFields, $row);
            }

            if ($errors !== []) {
                return $errors;
            }

            // Appended, so it lands after whatever the record already has —
            // the repeater's own ordering rule, applied to one row.
            $this->syncHasMany($entity, $field, [...$this->existingRows($entity, $key), $row]);
        } elseif (($field['relation'] ?? null) instanceof RelationOptions) {
            $values = [$key => $body[$key] ?? null];
            $errors = $this->resolveRelations([$field], $values);

            if ($errors !== []) {
                return $errors;
            }

            $related = $values[$key];

            if (!is_object($related)) {
                return [$key => 'Choose one.'];
            }

            $collection = $this->collectionOf($entity, $key);

            if (!$collection->contains($related)) {
                $collection->add($related);
            }
        } else {
            throw new \InvalidArgumentException(sprintf('"%s" is not a relation this can add to.', $key));
        }

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return [];
    }

    // ── Reading the body ─────────────────────────────────────────────────────

    /**
     * Submitted values for the declared fields only, typed for validation.
     *
     * Number fields become numbers before FieldValidator runs, so min/max
     * compare the value rather than the string length; a blank number means
     * "not provided", never zero. Everything else is trimmed.
     *
     * @param  list<array<string, mixed>> $fields
     * @param  array<string, mixed>       $body
     * @return array<string, mixed>
     */
    private function coerceValues(array $fields, array $body): array
    {
        $values = [];

        foreach ($fields as $field) {
            $key  = (string) ($field['key'] ?? '');
            $raw  = $body[$key] ?? null;
            $type = $field['type'] ?? null;

            if ($type === 'repeater') {
                $raw = $this->coerceRows($field, is_array($raw) ? array_values($raw) : []);
            } elseif ($type === 'multiselect') {
                // A list of identifiers; anything non-string does not survive.
                $raw = array_values(array_filter(
                    is_array($raw) ? $raw : [],
                    static fn (mixed $item): bool => is_string($item) && $item !== '',
                ));
            } elseif ($type === 'toggle') {
                // An unchecked switch sends nothing at all, so a missing value
                // is `false`, not "not provided" — and the column behind it is
                // usually a non-nullable bool, which null would break.
                $raw = filter_var($raw, FILTER_VALIDATE_BOOL);
            } elseif (self::isNumberField($field)) {
                $raw = self::coerceNumber($field, $raw);
            } elseif (is_string($raw)) {
                $raw = trim($raw);
            }

            $values[$key] = $raw;
        }

        return $values;
    }

    /**
     * A number input's string, as the value its setter expects.
     *
     * Whether that is int or float is the field's own declaration: a field
     * carrying the `integer` rule refuses fractions anyway, so it coerces to
     * int; a decimal field keeps the fraction — truncating 2.5 to 2 would be
     * a silent data change, not a coercion. A blank means "not provided".
     *
     * @param array<string, mixed> $field
     */
    private static function coerceNumber(array $field, mixed $raw): mixed
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (!is_numeric($raw)) {
            return $raw;
        }

        $rules = is_array($field['rules'] ?? null) ? $field['rules'] : [];

        return ($rules['integer'] ?? false) ? (int) $raw : (float) $raw;
    }

    /** @param array<string, mixed> $field */
    private static function isNumberField(array $field): bool
    {
        $props = is_array($field['props'] ?? null) ? $field['props'] : [];

        return ($props['type'] ?? null) === 'number';
    }

    /**
     * Sanitise a repeater's submitted rows: sequential, arrays only, and each
     * row reduced to its declared sub-keys plus the child identity. Anything
     * else the client sent simply does not survive.
     *
     * @param  array<string, mixed> $field
     * @param  list<mixed>          $rows
     * @return list<array<string, mixed>>
     */
    private function coerceRows(array $field, array $rows): array
    {
        $subFields = FormResolver::subFields($field);
        $clean     = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $cleanRow = [];

            // The child's identity, when the row edits an existing one.
            if (is_string($row['id'] ?? null) && $row['id'] !== '') {
                $cleanRow['id'] = $row['id'];
            }

            foreach ($subFields as $subField) {
                $subKey = (string) ($subField['key'] ?? '');
                $value  = $row[$subKey] ?? null;

                // The same number coercion the top level gets: a quantity
                // reaching a float setter as the string "250" would be a
                // TypeError under strict types, not a saved row.
                if (self::isNumberField($subField)) {
                    $value = self::coerceNumber($subField, $value);
                } elseif (is_string($value)) {
                    $value = trim($value);
                }

                $cleanRow[$subKey] = $value;
            }

            $clean[] = $cleanRow;
        }

        return $clean;
    }

    // ── Validation ───────────────────────────────────────────────────────────

    /**
     * The declared sub-field rules against every row, keyed `field.index.sub`.
     *
     * @param  array<string, mixed>       $field
     * @param  list<array<string, mixed>> $rows
     * @return array<string, string>
     */
    private static function validateRows(array $field, array $rows): array
    {
        $subFields = FormResolver::subFields($field);
        $key       = (string) $field['key'];
        $errors    = [];

        foreach ($rows as $index => $row) {
            foreach (FieldValidator::validate($subFields, $row) as $subKey => $message) {
                $errors["{$key}.{$index}.{$subKey}"] = $message;
            }
        }

        return $errors;
    }

    /**
     * Rows that would violate one of the child table's own unique constraints
     * against each other — a recipe listing flour twice, before the database
     * turns it into a 500.
     *
     * Metadata-driven: the constraints checked are the ones the child entity
     * declares. Only constraints scoped to the parent (those including the
     * back-reference's column) are decidable from the submitted rows alone —
     * a globally unique child column can collide with rows of *other*
     * parents, which only the database can see.
     *
     * @param  array<string, mixed>       $field
     * @param  list<array<string, mixed>> $rows
     * @return array<string, string>
     */
    private function duplicateRowErrors(object $entity, array $field, array $rows): array
    {
        $key      = (string) $field['key'];
        $property = Str::camel($key);
        $meta     = $this->entityManager->getClassMetadata($entity::class);

        if (!$meta->hasAssociation($property)) {
            return [];
        }

        $mapping = $meta->getAssociationMapping($property);

        if (empty($mapping['mappedBy'])) {
            return [];
        }

        $childMeta     = $this->entityManager->getClassMetadata($mapping['targetEntity']);
        $parentMapping = $childMeta->getAssociationMapping($mapping['mappedBy']);
        $parentColumn  = $parentMapping['joinColumns'][0]['name'] ?? null;
        $constraints   = $childMeta->table['uniqueConstraints'] ?? [];

        if ($parentColumn === null || $constraints === []) {
            return [];
        }

        // Column → submitted sub-field key, the reverse of what the guesser
        // did: a scalar column is its snake_cased field, an association's
        // join column is the `{property}_id` the row's select round-trips.
        $columnToSubKey = [];

        foreach ($childMeta->getFieldNames() as $fieldName) {
            $columnToSubKey[$childMeta->getColumnName($fieldName)] = Str::snake($fieldName);
        }

        foreach ($childMeta->getAssociationNames() as $association) {
            if (!$childMeta->isSingleValuedAssociation($association)) {
                continue;
            }

            $column = $childMeta->getAssociationMapping($association)['joinColumns'][0]['name'] ?? null;

            if ($column !== null) {
                $columnToSubKey[$column] = Str::snake($association) . '_id';
            }
        }

        $errors = [];

        foreach ($constraints as $constraint) {
            $all     = is_array($constraint['columns'] ?? null) ? $constraint['columns'] : [];
            $columns = array_diff($all, [$parentColumn]);

            // Parent-scoped constraints only, and only ones whose remaining
            // columns are all editable sub-fields — anything else cannot be
            // decided from the submission.
            if (count($columns) === count($all)) {
                continue;
            }

            $subKeys = [];

            foreach ($columns as $column) {
                if (!isset($columnToSubKey[$column])) {
                    continue 2;
                }

                $subKeys[] = $columnToSubKey[$column];
            }

            if ($subKeys === []) {
                continue;
            }

            $seen = [];

            foreach ($rows as $index => $row) {
                $parts = [];

                foreach ($subKeys as $subKey) {
                    $value = $row[$subKey] ?? null;

                    if ($value === null || $value === '') {
                        // An incomplete row is a required-field problem, not
                        // a duplicate one.
                        continue 2;
                    }

                    $parts[] = is_scalar($value) ? (string) $value : serialize($value);
                }

                $combo = implode("\x1f", $parts);

                if (isset($seen[$combo])) {
                    $errors["{$key}.{$index}.{$subKeys[0]}"] = 'Another row already uses this.';
                } else {
                    $seen[$combo] = true;
                }
            }
        }

        return $errors;
    }

    /**
     * Swap each relation field's submitted identifier for the entity it names.
     *
     * Runs after FieldValidator, so `required` has had its say; here a blank
     * simply becomes null, and a non-blank value must resolve — an identifier
     * that matches nothing is a client lying about the options it was given,
     * and is rejected on the field.
     *
     * @param  list<array<string, mixed>> $fields
     * @param  array<string, mixed>       $values modified in place
     * @return array<string, string> errors by field key
     */
    private function resolveRelations(array $fields, array &$values): array
    {
        $errors = [];

        foreach ($fields as $field) {
            $relation = $field['relation'] ?? null;

            if (!$relation instanceof RelationOptions) {
                continue;
            }

            $key   = (string) ($field['key'] ?? '');
            $value = $values[$key] ?? null;
            $label = (string) ($field['label'] ?? $key);

            if ($value === null || $value === '') {
                $values[$key] = null;

                continue;
            }

            $repository = $this->entityManager->getRepository($relation->entityClass);

            // A to-many relation submits a list of identifiers; all must
            // resolve or the field is rejected as a whole. One IN query, the
            // count telling whether every identifier matched.
            if (is_array($value)) {
                $identifiers = array_values(array_unique($value));
                $resolved    = $repository->findBy([$relation->valueField => $identifiers]);

                if (count($resolved) !== count($identifiers)) {
                    $errors[$key] = sprintf('%s contains an invalid choice.', $label);

                    continue;
                }

                $values[$key] = $resolved;

                continue;
            }

            $related = $repository->findOneBy([$relation->valueField => $value]);

            if ($related === null) {
                $errors[$key] = sprintf('%s is invalid.', $label);

                continue;
            }

            $values[$key] = $related;
        }

        return $errors;
    }

    /**
     * Reject values that would collide with another record on a unique column.
     *
     * Checked before flush rather than caught after it, because a failed
     * flush closes the EntityManager — there would be nothing left to
     * re-render the form with. Which columns are unique comes from Doctrine's
     * metadata; single-column constraints only, and a concurrent insert
     * between this check and the flush still surfaces as the database error
     * it is.
     *
     * @param  list<array<string, mixed>> $fields
     * @param  array<string, mixed>       $values
     * @return array<string, string>
     */
    private function uniquenessErrors(object $entity, array $fields, array $values): array
    {
        $meta   = $this->entityManager->getClassMetadata($entity::class);
        $errors = [];

        foreach (self::scalarFields($fields) as $field) {
            $key = (string) ($field['key'] ?? '');

            if (($field['relation'] ?? null) !== null || !$meta->hasField($key)) {
                continue;
            }

            if (!($meta->getFieldMapping($key)['unique'] ?? false)) {
                continue;
            }

            $value = $values[$key] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            $other = $this->entityManager->getRepository($entity::class)->findOneBy([$key => $value]);

            if ($other !== null && $other !== $entity) {
                $errors[$key] = sprintf(
                    '%s is already in use.',
                    (string) ($field['label'] ?? ucwords(str_replace('_', ' ', $key))),
                );
            }
        }

        return $errors;
    }

    // ── Writing the entity ───────────────────────────────────────────────────

    /**
     * Map validated values onto the entity through its setters.
     *
     * A blank string becomes null when the setter accepts null — an optional
     * synopsis left empty is "no synopsis", not an empty one. A field with no
     * matching setter is skipped rather than failing: presenters may include
     * derived keys the entity does not own. Relation fields follow the `_id`
     * convention: the field is `director_id`, the setter `setDirector`, and
     * by now the value is the entity itself.
     *
     * @param array<string, mixed>       $values
     * @param list<array<string, mixed>> $fields
     */
    private function applyValues(object $entity, array $values, array $fields): void
    {
        $relationKeys = [];

        foreach ($fields as $field) {
            if (($field['relation'] ?? null) instanceof RelationOptions) {
                $relationKeys[(string) ($field['key'] ?? '')] = true;
            }
        }

        foreach ($values as $key => $value) {
            $setterKey = isset($relationKeys[$key]) && str_ends_with($key, '_id')
                ? substr($key, 0, -3)
                : $key;

            $setter = 'set' . Str::studly($setterKey);

            if (!method_exists($entity, $setter)) {
                continue;
            }

            $parameter = (new \ReflectionMethod($entity, $setter))->getParameters()[0] ?? null;

            if ($value === '' && $parameter?->getType()?->allowsNull()) {
                $value = null;
            }

            $entity->{$setter}(self::coerceToSetterType($parameter, $value));
        }
    }

    /**
     * A submitted value as the type its setter declares, where that is a
     * conversion and not a guess. The setter's own signature decides —
     * keying off the field type would convert for a resource whose entity
     * stores the day as a string.
     *
     * Dates: a date field submits 'YYYY-MM-DD' and a date column's setter
     * takes a DateTimeImmutable. Numbers: a number field is coerced to
     * int or float so the declared min/max compare the value, but a decimal
     * column's natural property type is string, and under strict types a
     * float reaching `setRating(?string)` is a TypeError rather than a saved
     * rating — so a number meets a string setter as a string, and a numeric
     * string meets an int or float setter as that number.
     */
    private static function coerceToSetterType(?\ReflectionParameter $parameter, mixed $value): mixed
    {
        $type = $parameter?->getType();
        $name = $type instanceof \ReflectionNamedType ? $type->getName() : null;

        if ($name === null) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return match ($name) {
                'string' => (string) $value,
                'int'    => (int) $value,
                'float'  => (float) $value,
                default  => $value,
            };
        }

        if (!is_string($value) || $value === '') {
            return $value;
        }

        if (($name === 'int' || $name === 'float') && is_numeric($value)) {
            return $name === 'int' ? (int) $value : (float) $value;
        }

        if (!is_a($name, \DateTimeInterface::class, true)) {
            return $value;
        }

        try {
            return $name === \DateTime::class ? new \DateTime($value) : new \DateTimeImmutable($value);
        } catch (\Exception) {
            // Unparseable: hand the string on so the entity's own validation
            // reports it, rather than turning a bad date into a 500 here.
            return $value;
        }
    }

    /**
     * Reconcile a child collection with the submitted rows.
     *
     * Everything structural comes from Doctrine's association mapping — the
     * child class, the inverse side, and (via orphanRemoval) what removal
     * means; the declaration contributed only the sub-fields. Diff identity
     * is the child's uuid when it has one, else its identifier. Submitted id
     * → update in place; no id → new child; existing child absent from the
     * submission → removed, which orphanRemoval turns into a delete. Row
     * order is submitted order, persisted when the child maps `position`.
     *
     * @param array<string, mixed>       $field
     * @param list<array<string, mixed>> $rows
     */
    private function syncHasMany(object $entity, array $field, array $rows): void
    {
        $key      = (string) $field['key'];
        $property = Str::camel($key);
        $meta     = $this->entityManager->getClassMetadata($entity::class);

        if (!$meta->hasAssociation($property)
            || !$meta->isCollectionValuedAssociation($property)
            || empty($meta->getAssociationMapping($property)['mappedBy'])
        ) {
            throw new \LogicException(sprintf(
                'Field "%s" on %s is declared as rows but is not a mapped-by to-many association.',
                $key,
                $entity::class,
            ));
        }

        $mapping       = $meta->getAssociationMapping($property);
        $childClass    = $mapping['targetEntity'];
        $inverseSetter = 'set' . ucfirst((string) $mapping['mappedBy']);
        $childMeta     = $this->entityManager->getClassMetadata($childClass);
        $subFields     = FormResolver::subFields($field);
        $hasPosition   = $childMeta->hasField('position');
        $collection    = $this->collectionOf($entity, $key);

        $existing = [];

        foreach ($collection as $child) {
            $existing[$this->childIdentity($child, $childMeta)] = $child;
        }

        $kept = [];

        foreach ($rows as $index => $row) {
            $id    = isset($row['id']) && is_scalar($row['id']) ? (string) $row['id'] : null;
            $child = $id !== null ? ($existing[$id] ?? null) : null;

            if ($child === null) {
                $child = new $childClass();
                $child->{$inverseSetter}($entity);
                $collection->add($child);
            } else {
                $kept[$id] = true;
            }

            unset($row['id']);
            // The sub-fields travel with the row so a referencing sub-field
            // (`actor_id`) reaches `setActor()`.
            $this->applyValues($child, $row, $subFields);

            if ($hasPosition && method_exists($child, 'setPosition')) {
                $child->setPosition((int) $index);
            }
        }

        foreach ($existing as $id => $child) {
            if (!isset($kept[$id])) {
                $collection->removeElement($child);
            }
        }
    }

    /**
     * Reconcile a to-many relation with the resolved entities: both sides
     * already exist, only the links change.
     *
     * @param list<object> $related
     */
    private function syncCollectionRelation(object $entity, string $key, array $related): void
    {
        $meta = $this->entityManager->getClassMetadata($entity::class);

        if (!$meta->isCollectionValuedAssociation(Str::camel($key))) {
            throw new \LogicException(sprintf(
                'Field "%s" on %s submits a list but is not a to-many association.',
                $key,
                $entity::class,
            ));
        }

        $collection = $this->collectionOf($entity, $key);
        $wanted     = [];

        foreach ($related as $item) {
            $wanted[spl_object_id($item)] = $item;
        }

        foreach ($collection as $current) {
            if (!isset($wanted[spl_object_id($current)])) {
                $collection->removeElement($current);
            }
        }

        foreach ($related as $item) {
            if (!$collection->contains($item)) {
                $collection->add($item);
            }
        }
    }

    /**
     * The rows a record's repeater already holds, in the shape the sync
     * diffs against — identity and nothing else, since the existing children
     * are not being edited.
     *
     * @return list<array<string, mixed>>
     */
    private function existingRows(object $entity, string $key): array
    {
        $rows = [];

        foreach ($this->collectionOf($entity, $key) as $child) {
            $rows[] = ['id' => $this->childIdentity($child, $this->entityManager->getClassMetadata($child::class))];
        }

        return $rows;
    }

    /**
     * The string a repeater row diffs on: the child's uuid when it maps one,
     * else its identifier. A plain id is fine here where it would not be on a
     * route — it is only ever looked up inside this one parent's collection.
     *
     * @param ClassMetadata<object> $meta
     */
    private function childIdentity(object $child, ClassMetadata $meta): string
    {
        if ($meta->hasField('uuid') && method_exists($child, 'getUuid')) {
            $uuid = $child->getUuid();

            return $uuid instanceof \Stringable || is_string($uuid) ? (string) $uuid : '';
        }

        $values = $meta->getIdentifierValues($child);
        $first  = reset($values);

        return is_scalar($first) ? (string) $first : '';
    }

    /**
     * The collection behind a form key, through its getter.
     *
     * @return Collection<int, object>
     */
    private function collectionOf(object $entity, string $key): Collection
    {
        $getter = 'get' . Str::studly($key);

        if (!method_exists($entity, $getter)) {
            throw new \LogicException(sprintf('%s has no %s() to reach the "%s" collection.', $entity::class, $getter, $key));
        }

        $collection = $entity->{$getter}();

        if (!$collection instanceof Collection) {
            throw new \LogicException(sprintf('%s::%s() does not return a collection.', $entity::class, $getter));
        }

        return $collection;
    }

    // ── Shapes ───────────────────────────────────────────────────────────────

    /**
     * @param  list<array<string, mixed>> $fields
     * @return list<array<string, mixed>>
     */
    private static function scalarFields(array $fields): array
    {
        return array_values(array_filter(
            $fields,
            static fn (array $field): bool => ($field['type'] ?? null) !== 'repeater',
        ));
    }

    /**
     * @param  list<array<string, mixed>> $fields
     * @return list<array<string, mixed>>
     */
    private static function repeaterFields(array $fields): array
    {
        return array_values(array_filter(
            $fields,
            static fn (array $field): bool => ($field['type'] ?? null) === 'repeater',
        ));
    }

    /**
     * The rows a value holds for a repeater key, as a list of arrays.
     *
     * @param  array<string, mixed> $values
     * @return list<array<string, mixed>>
     */
    private static function rowsIn(array $values, string $key): array
    {
        $rows = [];

        foreach (is_array($values[$key] ?? null) ? $values[$key] : [] as $row) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @param  array<mixed> $items
     * @return list<object>
     */
    private static function objectsIn(array $items): array
    {
        $objects = [];

        foreach ($items as $item) {
            if (is_object($item)) {
                $objects[] = $item;
            }
        }

        return $objects;
    }
}
