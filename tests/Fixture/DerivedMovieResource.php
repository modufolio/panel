<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Fixture;

use Modufolio\Panel\Form\Form;
use Modufolio\Panel\Resource\PanelResource;
use Modufolio\Panel\Table\Column;
use Modufolio\Panel\Table\Filter;
use Modufolio\Panel\Table\TableSchema;
use Modufolio\Panel\Tests\Fixture\Entity\Movie;

/**
 * The movie resource with no list query class and no presenter: the query and
 * the rows are read off the table. Searchable title and studio name (a to-one path, so a
 * join), a default order, one column that opts out of sorting, and the
 * soft-delete scope from the entity's `deletedAt`.
 */
class DerivedMovieResource extends PanelResource
{
    public function key(): string
    {
        return 'movies';
    }

    public function entityClass(): string
    {
        return Movie::class;
    }

    /** No present(): the rows are read off the entity through the columns. */
    public function form(): Form
    {
        return Form::make()->fields(['title', 'synopsis', 'released_on']);
    }

    public function table(): TableSchema
    {
        return TableSchema::make()
            ->defaultSort('year', 'DESC')
            ->columns([
                Column::make('title')->searchable()->linksToRecord(),
                Column::make('year'),
                Column::make('rating')->notSortable(),
                Column::make('studio')->value('studio.name')->searchable(),
            ])
            ->filters([Filter::trashed()]);
    }
}
