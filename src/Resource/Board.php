<?php

declare(strict_types=1);

namespace Modufolio\Panel\Resource;

/**
 * The resource's records as cards in columns, grouped by one field.
 *
 *     Board::make('status')
 *         ->columns(IssueStatus::class)
 *         ->position('position')
 *         ->card('title', 'due_date')
 *         ->quickMove();
 *
 * Declaring one from {@see PanelResource::board()} adds the board beside the
 * table and offers the switcher; the table stays the default. A board is a
 * different *query*, not a different renderer over the table's payload, which
 * is why it is declared on the server. Which cards may move where is the
 * permissions' business: {@see Permissions::move()}.
 *
 * Pure data: the board never touches the database. {@see ResourceView} is
 * how the listing carries it once chosen.
 */
final class Board
{
    private function __construct(private ResourceView $view)
    {
    }

    /**
     * @param string $groupBy a mapped property of the entity — the grouping is
     *                        compiled into the query, so it addresses the
     *                        entity, not the presenter's key
     */
    public static function make(string $groupBy, string $label = 'Board', string $key = ResourceView::BOARD): self
    {
        return new self(ResourceView::board($groupBy, $label, $key));
    }

    /**
     * The columns, in order: an enum class whose cases are the columns, a
     * `value => label` map, or a list of `['value' => …, 'label' => …, 'color' => …]`.
     *
     * @param class-string|array<int|string, mixed> $source
     * @param array<string, string>                 $colors value => colour, for the first two forms
     */
    public function columns(string|array $source, array $colors = []): self
    {
        return new self($this->view->columns($source, $colors));
    }

    /** The property holding a card's place within its column; without one a drop inside a column is not saved. */
    public function position(string $field): self
    {
        return new self($this->view->position($field));
    }

    /** What a card shows: its heading, then the presented keys beneath it. */
    public function card(string $title, string ...$fields): self
    {
        return new self($this->view->card($title, ...$fields));
    }

    /** Cards fetched per column. */
    public function limit(int $cards): self
    {
        return new self($this->view->limit($cards));
    }

    /** A button per card for each column {@see Permissions::move()} allows. */
    public function quickMove(): self
    {
        return new self($this->view->quickMove());
    }

    public function label(string $label): self
    {
        return new self($this->view->label($label));
    }

    public function icon(string $icon): self
    {
        return new self($this->view->icon($icon));
    }

    /** The view the listing serves when this board is chosen. */
    public function view(): ResourceView
    {
        return $this->view;
    }
}
