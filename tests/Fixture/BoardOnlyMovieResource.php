<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Fixture;

use Modufolio\Panel\Resource\PanelResource;
use Modufolio\Panel\Resource\ResourceView;
use Modufolio\Panel\Table\TableSchema;
use Modufolio\Panel\Tests\Fixture\Entity\Movie;

/**
 * A resource with a board and deliberately no `formFieldKeys()`.
 *
 * Grouped by `released`, which every Movie already has — the case a board
 * exists for, and the one where requiring a form would be nonsense.
 */
final class BoardOnlyMovieResource extends PanelResource
{
    public function key(): string
    {
        return 'movies';
    }

    public function entityClass(): string
    {
        return Movie::class;
    }

    public function listQueryClass(): string
    {
        return MovieListQuery::class;
    }

    public function queryAlias(): string
    {
        return 'movie';
    }

    public function views(): array
    {
        return [
            ResourceView::table(),
            ResourceView::board('released')->columns(['1' => 'Released', '0' => 'Unreleased']),
        ];
    }

    public function present(array $entities): array
    {
        return array_map(static function (object $movie): array {
            assert($movie instanceof Movie);

            return [
                'id'    => $movie->getUuid()->toString(),
                'title' => $movie->getTitle(),
            ];
        }, $entities);
    }

    public function tableSchema(): TableSchema
    {
        return TableSchema::make();
    }
}
