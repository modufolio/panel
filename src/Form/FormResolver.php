<?php

declare(strict_types=1);

namespace Modufolio\Panel\Form;

use Doctrine\ORM\EntityManagerInterface;
use Modufolio\Panel\Blueprint\FormFieldGuesser;
use Modufolio\Panel\Resource\PanelResource;
use Modufolio\Panel\Table\RelationOptions;

/**
 * Which form a resource has.
 *
 * `form()` is a list of entries — guessed from Doctrine's metadata,
 * pinned to a type, or declared outright — and FormFieldGuesser turns it
 * into the definitions the client renders. Memoised per class, because the
 * guess walks metadata and one request reads the same form several times —
 * to render it, to validate against it, to label a relation for the drawer.
 * Who may read or write each field is not part of the form: the resource's
 * {@see \Modufolio\Panel\Resource\Permissions} answers that per request.
 */
final class FormResolver
{
    /** @var array<class-string, list<array<string, mixed>>> */
    private array $forms = [];

    /**
     * @param class-string|null $mediaEntityClass The application's media-library
     *   entity, if it has one: a to-one association pointing at it is guessed
     *   as an image field rather than a lookup.
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ?string $mediaEntityClass = null,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function fieldsFor(PanelResource $resource): array
    {
        return $this->forms[$resource::class] ??= $this->build($resource);
    }

    /**
     * The top-level declaration behind one form key, or null.
     *
     * @return array<string, mixed>|null
     */
    public function field(PanelResource $resource, string $key): ?array
    {
        foreach ($this->fieldsFor($resource) as $field) {
            if ((string) ($field['key'] ?? '') === $key) {
                return $field;
            }
        }

        return null;
    }

    /**
     * The relation behind one form key, or null when the key names no
     * relation the resource declares.
     *
     * `cast.actor_id` walks into the repeater's own declaration; a plain key
     * stays at the top level. Either way the path must terminate on a field
     * declared as a relation — nothing else is reachable by name, so no
     * request can address an entity class, a column or a table.
     */
    public function relationFor(PanelResource $resource, string $key): ?RelationOptions
    {
        $fields = $this->fieldsFor($resource);

        foreach (explode('.', $key) as $segment) {
            $match = null;

            foreach ($fields as $field) {
                if ((string) ($field['key'] ?? '') === $segment) {
                    $match = $field;
                    break;
                }
            }

            if ($match === null) {
                return null;
            }

            $relation = $match['relation'] ?? null;

            if ($relation instanceof RelationOptions) {
                return $relation;
            }

            $fields = self::subFields($match);
        }

        return null;
    }

    /**
     * A repeater's row declarations, or none.
     *
     * @param  array<string, mixed> $field
     * @return list<array<string, mixed>>
     */
    public static function subFields(array $field): array
    {
        $rows = [];

        foreach (is_array($field['fields'] ?? null) ? $field['fields'] : [] as $row) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    private function build(PanelResource $resource): array
    {
        return (new FormFieldGuesser($this->entityManager, $this->mediaEntityClass))->guess($resource) ?? [];
    }
}
