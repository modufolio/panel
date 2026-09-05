<?php

declare(strict_types=1);

namespace Modufolio\Panel\Form;

use Modufolio\Panel\Field\BelongsToType;
use Modufolio\Panel\Field\BuilderType;
use Modufolio\Panel\Field\ColorType;
use Modufolio\Panel\Field\ComputedType;
use Modufolio\Panel\Field\DataType;
use Modufolio\Panel\Field\DateTimeType;
use Modufolio\Panel\Field\DateType;
use Modufolio\Panel\Field\DecimalType;
use Modufolio\Panel\Field\EmailType;
use Modufolio\Panel\Field\EmbedType;
use Modufolio\Panel\Field\FieldTypeInterface;
use Modufolio\Panel\Field\HasManyType;
use Modufolio\Panel\Field\HiddenType;
use Modufolio\Panel\Field\ImageType;
use Modufolio\Panel\Field\ManyToManyType;
use Modufolio\Panel\Field\NumberType;
use Modufolio\Panel\Field\SelectType;
use Modufolio\Panel\Field\SetType;
use Modufolio\Panel\Field\StructureType;
use Modufolio\Panel\Field\TagsType;
use Modufolio\Panel\Field\TextareaType;
use Modufolio\Panel\Field\TextType;
use Modufolio\Panel\Field\ToggleType;
use Modufolio\Panel\Field\UrlType;
use Modufolio\Panel\Table\RelationOptions;

/**
 * One field, said once.
 *
 * The same options a `key => [options]` entry carries, with autocomplete: a
 * `Field` *is* that array, built by name. It appears in two places —
 *
 *  - {@see \Modufolio\Panel\Resource\PanelResource::fields()}, where a key's
 *    label, type and options are declared for every part that shows it; the
 *    table's columns, the drawer's key list and the form's entries look a
 *    bare key up here and take what they find;
 *  - a {@see Form}'s entries, for what the form alone needs of it: its width,
 *    its place in the sequence, a rule.
 *
 * Two levels of precedence, and no more: what a part says about a key wins
 * over what `fields()` says, which wins over what Doctrine's mapping says.
 * Every option is validated where the form is built, so a typo is refused by
 * name rather than ignored.
 */
final class Field
{
    /** @var array<string, mixed> */
    private array $options = [];

    private function __construct(private readonly string $key)
    {
    }

    public static function make(string $key): self
    {
        return new self($key);
    }

    public function key(): string
    {
        return $this->key;
    }

    /**
     * The options as a form entry carries them — `type` included when one
     * was declared, so a Field and a `key => [options]` array are the same
     * thing to everything downstream.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->options;
    }

    // ── Type ─────────────────────────────────────────────────────────────────

    /**
     * Declare the type outright, ahead of the column and of a `#[FormType]`
     * attribute. Without one, the type is guessed from Doctrine's mapping.
     *
     * @param class-string<FieldTypeInterface> $type
     */
    public function type(string $type): self
    {
        return $this->with('type', $type);
    }

    public function text(): self { return $this->type(TextType::class); }
    public function textarea(): self { return $this->type(TextareaType::class); }
    public function number(): self { return $this->type(NumberType::class); }
    public function decimal(): self { return $this->type(DecimalType::class); }
    public function toggle(): self { return $this->type(ToggleType::class); }
    public function select(): self { return $this->type(SelectType::class); }
    public function date(): self { return $this->type(DateType::class); }
    public function dateTime(): self { return $this->type(DateTimeType::class); }
    public function email(): self { return $this->type(EmailType::class); }
    public function url(): self { return $this->type(UrlType::class); }
    public function color(): self { return $this->type(ColorType::class); }
    public function tags(): self { return $this->type(TagsType::class); }
    public function image(): self { return $this->type(ImageType::class); }
    public function hidden(): self { return $this->type(HiddenType::class); }
    public function data(): self { return $this->type(DataType::class); }
    public function belongsTo(): self { return $this->type(BelongsToType::class); }
    public function hasMany(): self { return $this->type(HasManyType::class); }
    public function manyToMany(): self { return $this->type(ManyToManyType::class); }
    public function structure(): self { return $this->type(StructureType::class); }
    public function builder(): self { return $this->type(BuilderType::class); }

    /** A value with no column behind it, read from the named accessor on the record. */
    public function computed(string $accessor): self
    {
        return $this->type(ComputedType::class)->with('accessor', $accessor);
    }

    /**
     * Sub-fields stored as one object. A compound field in the Symfony sense:
     * the sub-fields are a fragment mounted under this key, and address as
     * `{key}.{sub}` everywhere.
     *
     * @param array<int|string, string|array<string, mixed>|self> $fields
     */
    public function set(array $fields): self
    {
        return $this->type(SetType::class)->fields($fields);
    }

    /**
     * Sub-fields over an embedded object, addressed as `{key}.{sub}`.
     *
     * @param array<int|string, string|array<string, mixed>|self> $fields
     */
    public function embed(array $fields): self
    {
        return $this->type(EmbedType::class)->fields($fields);
    }

    // ── Presentation ─────────────────────────────────────────────────────────

    public function label(string $label): self { return $this->with('label', $label); }
    public function help(string $help): self { return $this->with('help', $help); }
    public function placeholder(string $placeholder): self { return $this->with('placeholder', $placeholder); }
    public function prefix(string $prefix): self { return $this->with('prefix', $prefix); }
    public function postfix(string $postfix): self { return $this->with('postfix', $postfix); }
    public function group(string $group): self { return $this->with('group', $group); }

    /** One of '1/4', '1/3', '1/2', '2/3', '3/4', 'full' — the twelve-column grid. */
    public function width(string $width): self { return $this->with('width', $width); }

    // ── Behaviour ────────────────────────────────────────────────────────────

    public function required(bool $required = true): self { return $this->with('required', $required); }
    public function disabled(bool $disabled = true): self { return $this->with('disabled', $disabled); }
    public function readonly(bool $readonly = true): self { return $this->with('readonly', $readonly); }
    public function autofocus(bool $autofocus = true): self { return $this->with('autofocus', $autofocus); }

    /** A literal, or `@now` / `@today`, resolved when the record is written. */
    public function default(mixed $default): self { return $this->with('default', $default); }

    // ── Data ─────────────────────────────────────────────────────────────────

    /** @param array<int|string, mixed>|class-string<\BackedEnum> $options */
    public function options(array|string $options): self { return $this->with('options', $options); }

    /** @param array<string, mixed> $rules */
    public function rules(array $rules): self { return $this->with('rules', $rules); }

    /** @param array<string, mixed> $props */
    public function props(array $props): self { return $this->with('props', $props); }

    /** @param array<int|string, mixed> $condition */
    public function when(array $condition): self { return $this->with('when', $condition); }

    /** @param array<int|string, mixed> $condition */
    public function requiredWhen(array $condition): self { return $this->with('requiredWhen', $condition); }

    public function accessor(string $accessor): self { return $this->with('accessor', $accessor); }

    public function relation(RelationOptions $relation): self { return $this->with('relation', $relation); }

    /**
     * Sub-field declarations for a repeater, a set or an embed — the same
     * entry shapes a form takes, nested.
     *
     * @param array<int|string, string|array<string, mixed>|self> $fields
     */
    public function fields(array $fields): self
    {
        return $this->with('fields', Form::normalizeOptions($fields));
    }

    private function with(string $option, mixed $value): self
    {
        $clone = clone $this;
        $clone->options[$option] = $value;

        return $clone;
    }
}
