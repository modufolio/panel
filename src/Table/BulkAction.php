<?php

declare(strict_types=1);

namespace Modufolio\Panel\Table;

use Modufolio\Panel\Blueprint\BlueprintBuilder;
use Modufolio\Panel\Field\TextType;
use Modufolio\Panel\Form\Field;
use Modufolio\Panel\Support\Label;
/**
 * One action offered on a selection of rows.
 *
 * The selection travels in the body, so a bulk action is always a POST of
 * `{ids: [...]}` to its own endpoint — the shape every bulk route in this
 * panel already has. Declaring it here is what removed the identical
 * `confirm('Delete N …?')` from each listing that had one.
 *
 * `make()` is the escape hatch: no endpoint, dispatched to the handler the
 * page registers under that name.
 */
final class BulkAction
{
    /** POST the selected ids to `url`. */
    public const BEHAVIOUR_POST = 'post';

    /** Dispatched to the page's handler for this action's name. */
    public const BEHAVIOUR_HANDLER = 'handler';

    private string $label;

    private ?string $icon = null;

    private ?string $color = null;

    private ?string $variant = null;

    private ?string $url = null;

    private bool $confirm = false;

    /** @var list<Field> Asked for in a dialog before the action runs; posted with it. */
    private array $fields = [];

    private string $method = 'post';

    private ?string $submitLabel = null;

    private ?string $confirmMessage = null;

    private function __construct(
        private readonly string $name,
        private readonly string $behaviour,
    ) {
        $this->label = Label::sentence($name);
    }

    /**
     * Delete the selection.
     *
     * Confirms by default: unlike a single delete there is no preview
     * endpoint to describe twenty records' consequences, so the confirmation
     * is the only thing standing between a mis-click and the lot.
     */
    public static function delete(string $url, string $label = 'Delete Selected'): self
    {
        return (new self('delete', self::BEHAVIOUR_POST))
            ->label($label)
            ->icon('trash')
            ->color('danger')
            ->variant('outlined')
            ->url($url)
            ->confirm('This will delete {count} selected record(s). This cannot be undone.');
    }

    /** An arbitrary endpoint taking `{ids: [...]}`. */
    public static function post(string $name, string $url): self
    {
        return (new self($name, self::BEHAVIOUR_POST))->url($url);
    }

    /** An action the page services itself, dispatched by name. */
    public static function make(string $name): self
    {
        return new self($name, self::BEHAVIOUR_HANDLER);
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function icon(string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    public function color(string $color): self
    {
        $this->color = $color;

        return $this;
    }

    /** Button variant: `solid` (default), `outlined`, … */
    public function variant(string $variant): self
    {
        $this->variant = $variant;

        return $this;
    }

    public function url(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    /**
     * Confirm before acting. `{count}` in the message is replaced with the
     * number of selected rows.
     */
    public function confirm(?string $message = null): self
    {
        $this->confirm        = true;
        $this->confirmMessage = $message;

        return $this;
    }

    /**
     * What the dialog asks for, declared like a form's fields; the values
     * travel in the body under their keys. The same builder the forms use,
     * so every field type and option is available.
     *
     * @param list<Field> $fields
     */
    public function fields(array $fields): self
    {
        $this->fields = $fields;

        return $this;
    }

    /** The HTTP method the dialog submits with; post by default. */
    public function method(string $method): self
    {
        $method = strtolower($method);

        if (!in_array($method, ['post', 'put', 'patch', 'delete'], true)) {
            throw new \InvalidArgumentException(sprintf('Action "%s": method must be post, put, patch or delete, "%s" given.', $this->name, $method));
        }

        $this->method = $method;

        return $this;
    }

    public function submitLabel(string $label): self
    {
        $this->submitLabel = $label;

        return $this;
    }

    /**
     * The dialog's fields as the client renders them: each Field's options
     * through the blueprint builder, exactly as a resource form's would be.
     *
     * @return list<array<string, mixed>>|null
     */
    private function fieldSpecs(): ?array
    {
        if ($this->fields === []) {
            return null;
        }

        $builder = new BlueprintBuilder();

        foreach ($this->fields as $field) {
            $options = $field->toArray();
            $type    = $options['type'] ?? TextType::class;
            unset($options['type']);

            $builder->add($field->key(), is_string($type) ? $type : TextType::class, $options);
        }

        return $builder->fields();
    }

    public function name(): string
    {
        return $this->name;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'name'           => $this->name,
            'behaviour'      => $this->behaviour,
            'label'          => $this->label,
            'icon'           => $this->icon,
            'color'          => $this->color,
            'variant'        => $this->variant,
            'url'            => $this->url,
            'confirm'        => $this->confirm ?: null,
            'confirmMessage' => $this->confirmMessage,
            'fields'         => $this->fieldSpecs(),
            'method'         => $this->fields === [] && $this->method === 'post' ? null : $this->method,
            'submitLabel'    => $this->submitLabel,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
