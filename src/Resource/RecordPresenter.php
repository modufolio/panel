<?php

declare(strict_types=1);

namespace Modufolio\Panel\Resource;

use Modufolio\Appkit\Query\Query;
use Modufolio\Appkit\Toolkit\Str;
use Modufolio\Panel\Table\Column;

/**
 * Rows read off the entity, so a resource need not write a presenter.
 *
 * Each column names what it shows — its key, or a `value('studio.name')`
 * path, or a `text('{{ movie.title }} ({{ movie.year }})')` template — and
 * that is resolved against the entity through appkit's query language, the
 * one Kirby's blueprints use: `movie.studio.name` walks getStudio() and
 * getName(), `released_on` reaches getReleasedOn(). What the presenter used
 * to say in PHP, the column says in its declaration, and the drawer, the
 * export and the board read the same values.
 *
 * Two roots are in scope for a template: `record`, always, and the resource's
 * singular key (`movie`), so a blueprint reads the way a Kirby user expects.
 * A template is developer-authored, exactly as trusted as a PHP class; a
 * request never contributes to one.
 *
 * Values are normalised for the wire: dates as ISO 8601, backed enums as
 * their value, uuids and Stringables as strings, collections as a list of
 * their items' labels, any other object as its name, title or label. A
 * column reading something the entity cannot answer is a declaration bug and
 * says so — rather than a blank cell that looks like data.
 */
final class RecordPresenter
{
    public function __construct(private readonly PanelResource $resource)
    {
    }

    /**
     * One row per entity: the id, then every column's key.
     *
     * @param  list<object> $entities
     * @return list<array<string, mixed>>
     */
    public function rows(array $entities): array
    {
        $columns = $this->resource->table()?->declaredColumns() ?? [];

        return array_map(fn (object $entity): array => $this->row($entity, $columns), $entities);
    }

    /**
     * The drawer's record: the row, plus every form key and every declared
     * field the columns do not already cover — what the form edits is what
     * the drawer can show.
     *
     * @return array<string, mixed>
     */
    public function record(object $entity): array
    {
        $record = $this->row($entity, $this->resource->table()?->declaredColumns() ?? []);

        $keys = [
            ...($this->resource->form()?->keys() ?? []),
            ...array_keys($this->resource->fieldDefinitions()),
        ];

        foreach ($keys as $key) {
            if (!array_key_exists($key, $record)) {
                $record[$key] = self::normalise($this->formValue($entity, $key));
            }
        }

        return $record;
    }

    /**
     * @param  list<Column> $columns
     * @return array<string, mixed>
     */
    private function row(object $entity, array $columns): array
    {
        $row = ['id' => $this->identity($entity)];

        foreach ($columns as $column) {
            $template = $column->template();

            $row[$column->key()] = $template !== null
                ? Str::template($template, $this->roots($entity), ['fallback' => ''])
                : self::normalise($this->value($entity, $column));
        }

        return $row;
    }

    private function value(object $entity, Column $column): mixed
    {
        try {
            return $this->read($entity, $column->field());
        } catch (\BadMethodCallException $e) {
            throw new \LogicException(sprintf(
                'Column "%s" on %s reads "%s", but %s cannot answer it (%s). Point it at an accessor with ->value(), '
                . 'render it with ->text(), or override present().',
                $column->key(),
                $this->resource::class,
                $column->field(),
                $entity::class,
                $e->getMessage(),
            ), 0, $e);
        }
    }

    /**
     * What a form field reads back. `director_id` edits the `director`
     * association — the same convention the guesser and the write path use —
     * so its value is the related record's public id, which is what the
     * lookup control round-trips.
     */
    private function formValue(object $entity, string $key): mixed
    {
        if (str_ends_with($key, '_id')) {
            $related = $this->read($entity, substr($key, 0, -3), allowMissing: true);

            if (is_object($related)) {
                return $this->identity($related);
            }
        }

        return $this->read($entity, $key, allowMissing: true);
    }

    /**
     * Walk a dotted path one segment at a time, so a null along the way is a
     * null value rather than an error — a movie without a studio has no
     * studio name — while a segment nothing can answer still throws.
     */
    private function read(object $entity, string $path, bool $allowMissing = false): mixed
    {
        $value = $entity;

        foreach (explode('.', $path) as $segment) {
            if ($value === null) {
                return null;
            }

            try {
                $value = Query::factory('value.' . $segment)->resolve(['value' => $value]);
            } catch (\BadMethodCallException $e) {
                if ($allowMissing) {
                    return null;
                }

                throw $e;
            }
        }

        return $value;
    }

    /** @return array<string, object> */
    private function roots(object $entity): array
    {
        return ['record' => $entity, $this->resource->drawerType() => $entity];
    }

    /** The public id: the uuid where there is one, else the identifier. */
    private function identity(object $entity): string|int|null
    {
        if (method_exists($entity, 'getUuid')) {
            $uuid = $entity->getUuid();

            if (is_string($uuid) || $uuid instanceof \Stringable) {
                return (string) $uuid;
            }
        }

        if (method_exists($entity, 'getId')) {
            $id = $entity->getId();

            return is_int($id) || is_string($id) ? $id : null;
        }

        return null;
    }

    /** A value as the client receives it. */
    public static function normalise(mixed $value): mixed
    {
        if ($value === null || is_scalar($value)) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }

        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if ($value instanceof \UnitEnum) {
            return $value->name;
        }

        if ($value instanceof \Stringable) {
            return (string) $value;
        }

        // A plain array keeps its keys — a set's stored object, a JSON
        // column; a collection becomes a list of what its items show as.
        if (is_array($value)) {
            return array_map(self::normalise(...), $value);
        }

        if ($value instanceof \Traversable) {
            $items = [];

            foreach ($value as $item) {
                $items[] = self::normalise($item);
            }

            return $items;
        }

        if (is_object($value)) {
            foreach (['getName', 'getTitle', 'getLabel', 'name', 'title', 'label'] as $getter) {
                if (method_exists($value, $getter)) {
                    return self::normalise($value->{$getter}());
                }
            }

            throw new \LogicException(sprintf(
                '%s has no name, title or label to show; read a field of it with a dotted path (`relation.field`).',
                $value::class,
            ));
        }

        return $value;
    }
}
