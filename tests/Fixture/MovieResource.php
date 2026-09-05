<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Fixture;

use Modufolio\Panel\Resource\PanelResource;
use Modufolio\Panel\Table\Column;
use Modufolio\Panel\Table\Constraint;
use Modufolio\Panel\Table\Filter;
use Modufolio\Panel\Table\Group;
use Modufolio\Panel\Table\Summary;
use Modufolio\Panel\Table\TableSchema;
use Modufolio\Panel\Tests\Fixture\Entity\Movie;
use Modufolio\Panel\Tests\Fixture\Entity\Studio;
use Modufolio\Panel\Form\Form;

/**
 * A resource declaring one of everything the listing machinery resolves:
 * a relation-backed filter, a ternary, the trashed control, a grouping, one
 * constraint per kind, column summaries, and a guessed form. Not final, so a
 * test can override a single hook — a permission, a scope — and keep the rest.
 */
class MovieResource extends PanelResource
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

    public function form(): ?Form
    {
        return Form::make()->fields([
            'title',
            'synopsis',
            'year',
            'runtime',
            'rating',
            'released',
            'released_on',
            'studio_id',
            'tags',
            'cast',
        ]);
    }

    public function present(array $entities): array
    {
        return array_map(static function (object $movie): array {
            assert($movie instanceof Movie);

            return [
                'id'       => $movie->getId(),
                'uuid'     => $movie->getUuid()->toString(),
                'title'    => $movie->getTitle(),
                'year'     => $movie->getYear(),
                'rating'   => $movie->getRating(),
                'released' => $movie->isReleased(),
                'studio'   => $movie->getStudio()?->getName(),
            ];
        }, $entities);
    }

    public function table(): TableSchema
    {
        return TableSchema::make()
            ->bulkActions()
            ->columns([
                Column::make('title')->linksToRecord()->summarize(Summary::count('Movies')),
                Column::make('year')->summarize([Summary::min(), Summary::max()]),
                Column::make('rating')->summarize(Summary::average()),
                Column::make('studio')->notSortable(),
            ])
            ->filters([
                Filter::select('studio')->relationship(Studio::class, 'name', 'uuid'),
                Filter::ternary('released'),
                Filter::trashed(),
            ])
            ->groups([
                Group::make('year'),
            ])
            ->constraints([
                Constraint::text('title'),
                Constraint::number('year'),
                Constraint::boolean('released'),
                Constraint::date('released_on', 'releasedOn'),
            ]);
    }
}
