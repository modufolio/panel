<?php

declare(strict_types = 1);

namespace Modufolio\Panel\Blueprint;

use Modufolio\Panel\Field\FieldTypeInterface;
use Symfony\Component\OptionsResolver\Exception\ExceptionInterface as OptionsException;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Collects a blueprint's fields, in the order they are added.
 *
 * The counterpart to Symfony's FormBuilderInterface (see
 * App\Form\ContactFormType): `add()` names a key, a type class and the options
 * that differ from the type's own. Options passed here win over the type's
 * defaults, so `EmailType` supplies the email rule and the input type, and a
 * field can still override either.
 *
 * Options are resolved through OptionsResolver, the same component
 * ContactFormType configures. A misspelled option is therefore an error at
 * build time rather than a setting that silently does nothing.
 */
final class BlueprintBuilder
{
    /**
     * Options that describe the control itself and must reach the component as
     * props. Without this they would sit at the top level of the definition,
     * where the panel's field renderers do not look, and quietly do nothing.
     */
    private const CONTROL_OPTIONS = ['placeholder', 'disabled', 'readonly', 'required', 'autofocus'];

    /** @var list<array<string, mixed>> */
    private array $fields = [];

    /**
     * @param class-string<FieldTypeInterface> $type
     * @param array<string, mixed>             $options
     */
    public function add(string $key, string $type, array $options = []): self
    {
        if (!is_a($type, FieldTypeInterface::class, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Field "%s": %s is not a %s.',
                $key,
                $type,
                FieldTypeInterface::class,
            ));
        }

        $defaults = $type::defaults();

        try {
            $resolved = $this->resolver()->resolve($options);
        } catch (OptionsException $e) {
            throw new \InvalidArgumentException(
                sprintf('Field "%s" (%s): %s', $key, $type, $e->getMessage()),
                previous: $e,
            );
        }

        $field = [
            'key'   => $key,
            'type'  => $type::component(),
            'label' => $resolved['label'] ?? self::humanize($key),
            // props and rules merge key-by-key rather than being replaced, so
            // adding one rule to an EmailType does not silently drop the email
            // check the type brought with it.
            'props' => array_merge(
                $defaults['props'] ?? [],
                $resolved['props'] ?? [],
                self::controlProps($resolved),
            ),
            'rules' => array_merge($defaults['rules'] ?? [], $resolved['rules'] ?? []),
        ];

        foreach (['help', 'width', 'options', 'when', 'default', 'relation', 'fields'] as $option) {
            $value = $resolved[$option] ?? $defaults[$option] ?? null;

            if ($value !== null) {
                $field[$option] = $value;
            }
        }

        // `required` is also a top-level flag, because the panel renders the
        // asterisk from it rather than from the props.
        if (($resolved['required'] ?? false) === true) {
            $field['required'] = true;
            $field['rules'] = ['required' => true] + $field['rules'];
        }

        $this->fields[] = $field;

        return $this;
    }

    /**
     * Field definitions, in declaration order.
     *
     * Empty `props`/`rules` are dropped so the payload sent to the panel — and
     * anything comparing definitions in a test — stays free of noise.
     *
     * @return list<array<string, mixed>>
     */
    public function fields(): array
    {
        return array_map(
            static fn (array $field): array => array_filter(
                $field,
                static fn (mixed $value, string $key): bool => !in_array($key, ['props', 'rules'], true) || $value !== [],
                ARRAY_FILTER_USE_BOTH,
            ),
            $this->fields,
        );
    }

    /** @param array<string, mixed> $resolved */
    private static function controlProps(array $resolved): array
    {
        $props = [];

        foreach (self::CONTROL_OPTIONS as $option) {
            if (($resolved[$option] ?? null) !== null) {
                $props[$option] = $resolved[$option];
            }
        }

        return $props;
    }

    private function resolver(): OptionsResolver
    {
        $resolver = new OptionsResolver();

        $resolver->setDefined([
            // Presentation
            'label', 'help', 'width', 'placeholder',
            // Behaviour
            'required', 'disabled', 'readonly', 'autofocus', 'default',
            // Data
            'options', 'rules', 'props',
            // Conditional visibility, evaluated in the panel against the form
            'when',
            // A BelongsTo field's related entity, held as data (RelationOptions)
            // and resolved to a flat option list where the EntityManager lives.
            'relation',
            // A HasMany field's sub-field declarations — built with a nested
            // BlueprintBuilder; the child entity itself is never named here,
            // Doctrine's association mapping answers that.
            'fields',
        ]);

        $resolver->setAllowedTypes('label', 'string');
        $resolver->setAllowedTypes('help', 'string');
        $resolver->setAllowedTypes('width', 'string');
        $resolver->setAllowedTypes('placeholder', 'string');
        $resolver->setAllowedTypes('required', 'bool');
        $resolver->setAllowedTypes('disabled', 'bool');
        $resolver->setAllowedTypes('readonly', 'bool');
        $resolver->setAllowedTypes('autofocus', 'bool');
        $resolver->setAllowedTypes('options', 'array');
        $resolver->setAllowedTypes('rules', 'array');
        $resolver->setAllowedTypes('props', 'array');
        $resolver->setAllowedTypes('when', 'array');
        $resolver->setAllowedTypes('relation', \Modufolio\Panel\Table\RelationOptions::class);
        $resolver->setAllowedTypes('fields', 'array');

        // The panel lays fields out on a twelve-column grid.
        $resolver->setAllowedValues('width', ['1/4', '1/3', '1/2', '2/3', '3/4', 'full']);

        return $resolver;
    }

    private static function humanize(string $key): string
    {
        return ucwords(str_replace('_', ' ', $key));
    }
}
